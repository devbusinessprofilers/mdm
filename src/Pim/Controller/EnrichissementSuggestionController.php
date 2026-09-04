<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\FicheSuggestion;
use App\Pim\Form\EnrichissementSuggestionFormFactory;
use App\Pim\Repository\FicheSuggestionRepository;
use App\Pim\Service\Editeur\EditeurNavigation;
use App\Pim\Service\EnrichissementSuggestionArbitre;
use App\Pim\Service\FicheSectionsCatalogue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Décision en un clic sur une suggestion d'enrichissement générique
 * (FicheSuggestion, Sirene aujourd'hui), depuis le bloc « Suggestions en
 * attente » ou l'écran Qualité. Accepter applique (backfill de champ, ou
 * archivage d'un établissement cessé), Ignorer solde la suggestion.
 */
final class EnrichissementSuggestionController extends AbstractController
{
    #[Route(
        '/referentiel/suggestion/{id}/{decision}',
        name: 'app_mdm_enrichissement_suggestion',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}', 'decision' => 'accepter|ignorer'],
        methods: ['POST'],
    )]
    public function __invoke(
        Request $request,
        string $id,
        string $decision,
        FicheSuggestionRepository $suggestions,
        EnrichissementSuggestionFormFactory $forms,
        EnrichissementSuggestionArbitre $arbitre,
        CurrentActorProvider $actor,
        EditeurNavigation $navigation,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $suggestion = $suggestions->find(\Symfony\Component\Uid\Ulid::fromString($id));
        if (!$suggestion instanceof FicheSuggestion) {
            throw $this->createNotFoundException('Suggestion introuvable.');
        }
        $fiche = $suggestion->fiche();
        $retour = new RedirectResponse('qualite' === $request->query->get('retour')
            ? $this->generateUrl('app_mdm_qualite', ['onglet' => 'conflits'])
            : $navigation->urlSection(
                $fiche->type(),
                $fiche->idString(),
                FicheSectionsCatalogue::indexBloc($fiche->type(), 'suggestions_attente'),
            ));
        $form = $forms->action($suggestion->id(), $decision);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'La décision est invalide (jeton expiré ?). Rechargez la page.');

            return $retour;
        }
        try {
            if ('accepter' === $decision) {
                $arbitre->accepter($suggestion, $actor->id());
                $this->addFlash('success', 'Suggestion appliquée à la fiche.');
            } else {
                $arbitre->ignorer($suggestion, $actor->id());
                $this->addFlash('success', 'Suggestion ignorée : la fiche est inchangée.');
            }
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $retour;
    }
}
