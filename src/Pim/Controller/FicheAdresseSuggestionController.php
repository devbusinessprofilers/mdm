<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Entity\Fiche;
use App\Pim\Form\AdresseSuggestionFormFactory;
use App\Pim\Service\AdresseSuggestionArbitre;
use App\Pim\Service\FicheEditeurEcran;
use App\Pim\Service\FicheSectionsCatalogue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Décision en un clic sur la suggestion d'adresse BAN d'une fiche, depuis le
 * bloc « Suggestions en attente » (onglet Informations générales) ou depuis
 * l'écran Qualité (retour=qualite). Accepter applique la proposition,
 * Ignorer garde la saisie ; dans les deux cas l'écart est soldé.
 */
final class FicheAdresseSuggestionController extends AbstractController
{
    #[Route(
        '/referentiel/fiche/{id}/adresse-suggestion/{decision}',
        name: 'app_mdm_fiche_adresse_suggestion',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}', 'decision' => 'accepter|ignorer'],
        methods: ['POST'],
    )]
    public function __invoke(
        Request $request,
        Fiche $fiche,
        string $decision,
        AdresseSuggestionFormFactory $forms,
        AdresseSuggestionArbitre $arbitre,
        FicheEditeurEcran $ecran,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $filtre = $request->query->get('adresses');
        $retour = new RedirectResponse('qualite' === $request->query->get('retour')
            ? $this->generateUrl('app_mdm_qualite', array_filter([
                'onglet' => 'conflits',
                'adresses' => in_array($filtre, ['avec', 'sans'], true) ? $filtre : null,
            ]))
            : $ecran->urlSection(
                $fiche->type(),
                $fiche->idString(),
                FicheSectionsCatalogue::indexBloc($fiche->type(), 'suggestions_attente'),
            ));
        // Même nom et même jeton que les formulaires rendus par les écrans.
        $form = $forms->action($fiche->idString(), $decision);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'La décision est invalide (jeton expiré ?). Rechargez la page.');

            return $retour;
        }
        $request->attributes->set('_audit_source', 'ban');
        try {
            if ('accepter' === $decision) {
                $arbitre->accepter($fiche);
                $this->addFlash('success', 'L\'adresse proposée par la BAN a été appliquée à la fiche.');
            } else {
                $arbitre->ignorer($fiche);
                $this->addFlash('success', 'Suggestion d\'adresse ignorée : la saisie actuelle est conservée.');
            }
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $retour;
    }
}
