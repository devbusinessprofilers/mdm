<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\AccesSuggere;
use App\Pim\Service\AccesSuggesteur;
use App\Pim\Service\GammeEntiteResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Bouton « Suggérer les accès » du bloc Accès : le contrôleur Stimulus
 * acces-suggestion poste la gamme+id de la fiche et les types déjà saisis ;
 * on renvoie en JSON les entrées trouvées autour de ses coordonnées GPS
 * (référentiels statiques + Geoapify), que le client ajoute comme lignes
 * pré-remplies de la collection — modifiables avant enregistrement. Seules
 * les gammes Lieu et Restaurant portent un bloc Accès ; les distances sont
 * localisées ici (virgule) pour entrer telles quelles dans les champs.
 */
final class FicheAccesSuggestionController extends AbstractController
{
    #[Route('/referentiel/fiche/suggerer-acces', name: 'app_pim_fiche_suggerer_acces', methods: ['POST'])]
    public function __invoke(
        Request $request,
        LieuRepository $lieux,
        GammeEntiteResolver $resolver,
        AccesSuggesteur $suggesteur,
        CsrfTokenManagerInterface $csrf,
    ): JsonResponse {
        if (!$csrf->isTokenValid(new CsrfToken('fiche-acces-suggestion', (string) $request->headers->get('X-CSRF-Token')))) {
            return $this->json(['error' => 'Jeton de sécurité invalide — rechargez la page.'], 403);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Requête illisible.'], 400);
        }
        $gamme = is_string($data['gamme'] ?? null) ? $data['gamme'] : '';
        $id = is_string($data['id'] ?? null) ? $data['id'] : '';
        $exclus = array_values(array_filter((array) ($data['exclus'] ?? []), is_string(...)));
        if (!in_array($gamme, ['lieux', 'restaurants', 'services'], true)) {
            return $this->json(['error' => 'Cette gamme n\'a pas de bloc Accès.'], 400);
        }

        $entite = 'lieux' === $gamme ? $lieux->find($id) : $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());

        try {
            // Le Service n'a que route / parking / gare / aéroport (TypeAccesService).
            $types = 'services' === $gamme ? AccesSuggesteur::TYPES_SERVICE : AccesSuggesteur::TYPES_LIEU;
            $suggestions = $suggesteur->suggerer($entite->fiche(), $exclus, 'lieux' === $gamme, $types);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }

        return $this->json(['acces' => array_map(static fn (AccesSuggere $acces): array => [
            'type' => $acces->type,
            'nom' => $acces->nom,
            // Virgule décimale : le champ Distance passe par le transformer
            // localisé de NumberType, qui attend la notation française.
            'distanceKilometres' => null === $acces->distanceKilometres ? null : str_replace('.', ',', $acces->distanceKilometres),
            'dureeMinutes' => $acces->dureeMinutes,
            'modeTransport' => $acces->modeTransport,
        ], $suggestions)]);
    }
}
