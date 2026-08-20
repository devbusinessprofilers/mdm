<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\TypeFiche;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Réponse des actions documentaires des fiches, partagée par les contrôleurs
 * Document de toutes les gammes : le contrôleur medias-bloc soumet les
 * formulaires des modales en fetch et re-rend le bloc seul — réponse JSON
 * quand la requête vient de lui, flash + redirection vers la section Médias
 * sinon (fallback sans JavaScript). Jamais de flash en AJAX, il s'afficherait
 * à la navigation suivante.
 */
final readonly class MediasBlocReponse
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private RequestStack $requests,
    ) {
    }

    public function repondre(Request $request, Fiche $fiche, ?string $erreur, string $succes): Response
    {
        if ($request->isXmlHttpRequest()) {
            return null === $erreur
                ? new JsonResponse(['ok' => true])
                : new JsonResponse(['error' => $erreur], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $session = $this->requests->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(null === $erreur ? 'success' : 'error', $erreur ?? $succes);
        }
        // L'entité et sa fiche partagent le même ULID : l'id de la fiche
        // résout la route de l'éditeur quelle que soit la gamme.
        $section = FicheSectionsCatalogue::indexBloc($fiche->type(), 'medias');
        $url = TypeFiche::Lieu === $fiche->type()
            ? $this->urls->generate('app_mdm_fiche_lieu', ['id' => $fiche->idString(), 'section' => $section])
            : $this->urls->generate('app_mdm_fiche_gamme', [
                'gamme' => FicheEditeurEcran::slug($fiche->type()),
                'id' => $fiche->idString(),
                'section' => $section,
            ]);

        return new RedirectResponse($url);
    }
}
