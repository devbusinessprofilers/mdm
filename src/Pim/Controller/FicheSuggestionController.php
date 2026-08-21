<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\ChampSuggestionService;
use App\Pim\Service\GammeEntiteResolver;
use App\Shared\Service\ParametreProviderInterface;
use App\Vision\Service\OpenAiProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Suggestion IA d'un champ de description depuis l'éditeur de fiche : le
 * contrôleur Stimulus champ-suggestion poste le champ visé, son contenu actuel
 * et la gamme+id de la fiche ; on renvoie en JSON une proposition rédigée que
 * l'éditeur remplace côté client (annulable). Un seul point d'entrée pour toutes
 * les gammes ; la fiche est résolue depuis la gamme du corps.
 */
final class FicheSuggestionController extends AbstractController
{
    #[Route('/referentiel/fiche/suggerer', name: 'app_pim_fiche_suggerer', methods: ['POST'])]
    public function __invoke(
        Request $request,
        LieuRepository $lieux,
        GammeEntiteResolver $resolver,
        ChampSuggestionService $suggestions,
        ParametreProviderInterface $parametres,
        CsrfTokenManagerInterface $csrf,
    ): JsonResponse {
        if (!$parametres->bool('openai.actif')) {
            throw $this->createNotFoundException('Suggestion IA désactivée.');
        }
        if (!$csrf->isTokenValid(new CsrfToken('fiche-suggestion', (string) $request->headers->get('X-CSRF-Token')))) {
            return $this->json(['error' => 'Jeton de sécurité invalide — rechargez la page.'], 403);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Requête illisible.'], 400);
        }
        $gamme = is_string($data['gamme'] ?? null) ? $data['gamme'] : '';
        $id = is_string($data['id'] ?? null) ? $data['id'] : '';
        $champ = is_string($data['champ'] ?? null) ? $data['champ'] : '';
        $valeur = is_string($data['valeur'] ?? null) ? $data['valeur'] : '';

        $entite = 'lieux' === $gamme ? $lieux->find($id) : $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());

        try {
            $suggestion = $suggestions->suggerer($entite->fiche(), $champ, $valeur);
        } catch (OpenAiProviderException $exception) {
            return $this->json(
                ['error' => $exception->retryable
                    ? 'Le service de suggestion est momentanément indisponible — réessayez.'
                    : 'La suggestion a échoué.'],
                $exception->retryable ? 503 : 502,
            );
        }

        return $this->json(['suggestion' => $suggestion]);
    }
}
