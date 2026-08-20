<?php

declare(strict_types=1);

namespace App\Etl\Controller;

use App\Etl\Message\RefreshFichesSalesforce;
use App\Etl\Service\SalesforceWebhookGuard;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Webhook entrant Salesforce : reçoit les codes produit (Product2.ID__c)
 * modifiés et planifie leur rafraîchissement asynchrone (file etl). Les
 * valeurs ne sont jamais prises dans le payload : le refresher relit l'état
 * chez Salesforce, avec les mêmes règles que le cron quotidien (garde-diff,
 * replanification marketplace). Un payload partiel ou mal ordonné côté
 * Salesforce ne peut donc pas écraser de données.
 */
final class SalesforceWebhookController
{
    /** Aligné sur la taille des lots Salesforce (Flow/Apex : 200 records). */
    private const MAX_CODES = 200;

    #[Route('/api/salesforce/produits', name: 'app_salesforce_webhook_produits', methods: ['POST'])]
    public function __invoke(Request $request, SalesforceWebhookGuard $guard, MessageBusInterface $bus): JsonResponse
    {
        if (!$guard->isEnabled()) {
            // Même politique que /metrics : endpoint invisible tant qu'aucun jeton n'est déployé.
            return new JsonResponse(['type' => 'not_found', 'message' => 'Ressource introuvable.'], Response::HTTP_NOT_FOUND);
        }
        if (!$guard->isAuthorized($request)) {
            return new JsonResponse(['type' => 'invalid_token', 'message' => 'Jeton Bearer manquant ou invalide.'], Response::HTTP_UNAUTHORIZED);
        }
        try {
            $payload = json_decode($request->getContent(), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['type' => 'invalid_payload', 'message' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }
        $bruts = is_array($payload) ? ($payload['codes'] ?? null) : null;
        if (!is_array($bruts) || [] === $bruts) {
            return new JsonResponse(['type' => 'invalid_payload', 'message' => 'Le corps doit contenir une liste « codes » non vide.'], Response::HTTP_BAD_REQUEST);
        }
        if (count($bruts) > self::MAX_CODES) {
            return new JsonResponse(['type' => 'invalid_payload', 'message' => sprintf('Au plus %d codes par appel.', self::MAX_CODES)], Response::HTTP_BAD_REQUEST);
        }
        $codes = [];
        foreach ($bruts as $brut) {
            // ID__c est un champ texte côté Salesforce : les codes arrivent aussi en chaîne.
            $code = is_int($brut) ? $brut : (is_string($brut) && ctype_digit($brut) ? (int) $brut : 0);
            if ($code < 1) {
                return new JsonResponse(['type' => 'invalid_payload', 'message' => sprintf('Code invalide : %s (entier strictement positif attendu).', json_encode($brut))], Response::HTTP_BAD_REQUEST);
            }
            $codes[$code] = true;
        }
        foreach (array_keys($codes) as $code) {
            $bus->dispatch(new RefreshFichesSalesforce($code));
        }

        return new JsonResponse(['acceptes' => count($codes)], Response::HTTP_ACCEPTED);
    }
}
