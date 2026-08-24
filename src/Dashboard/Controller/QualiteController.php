<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Dashboard\Form\SuggestionSelectionType;
use App\Dashboard\Message\ComputeDashboardStats;
use App\Dashboard\Message\ComputeFieldFillRates;
use App\Dashboard\Repository\QualiteRepository;
use App\Dashboard\Service\SuggestionsBulkArbitre;
use App\Dashboard\Service\SuggestionsEcran;
use App\Pim\Form\TextDuplicateFormFactory;
use App\Pim\Service\TextDuplicateArbitre;
use App\Shared\Form\ActionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran « Qualité » (temps 1) : le châssis 5 onglets de la maquette, branché
 * sur ce que le back trace déjà. Le comparatif Salesforce / MDM / portail
 * viendra avec l'intégration Salesforce (temps 2).
 */
final class QualiteController extends AbstractController
{
    public const ONGLETS = [
        'miroir' => 'Santé des données',
        'conflits' => 'Conflits à arbitrer',
        'doublons_textes' => 'Doublons de textes',
        'formes' => 'Écarts de forme',
        'notifs' => 'Notifications',
        'decisions' => 'Décisions d\'arbitrage',
    ];

    #[Route('/qualite', name: 'app_mdm_qualite', methods: ['GET'])]
    public function __invoke(Request $request, QualiteRepository $qualite, SuggestionsEcran $suggestionsEcran, TextDuplicateFormFactory $doublonForms): Response
    {
        $onglet = $request->query->getString('onglet');
        if (!array_key_exists($onglet, self::ONGLETS)) {
            $onglet = 'miroir';
        }
        $suggestionsData = 'conflits' === $onglet
            ? $suggestionsEcran->assembler(
                $request->query->getString('src'),
                $request->query->getInt('page', 1),
                $request->query->getString('tri'),
                $request->query->getString('ordre'),
            )
            : [];

        return $this->render('dashboard/qualite.html.twig', array_merge([
            'onglets' => self::ONGLETS,
            'onglet_actif' => $onglet,
            'badges' => $qualite->badges(),
            'sante' => 'miroir' === $onglet ? $qualite->santeParGamme() : [],
            'champs_faibles' => 'miroir' === $onglet ? $qualite->champsFaibles() : [],
            'suggestions' => 'conflits' === $onglet ? $qualite->suggestionsEnAttente() : [],
            'adresses_comptes' => 'conflits' === $onglet ? $qualite->comptesSuggestionsAdresse() : ['avec' => 0, 'sans' => 0],
            'doublons_adresse' => 'conflits' === $onglet ? $qualite->doublonsAdresse() : [],
            // Mêmes décisions un clic que les doublons photos du DAM : confirmer
            // un doublon légitime ou ignorer un faux positif, en restant ici.
            'doublons_textes' => 'doublons_textes' === $onglet
                ? array_map(static fn (array $ligne): array => $ligne + [
                    'confirmer' => $doublonForms->action($ligne['alert_id'], 'confirmer')->createView(),
                    'ignorer' => $doublonForms->action($ligne['alert_id'], 'ignorer')->createView(),
                ], $qualite->doublonsTextes())
                : [],
            'formes' => 'formes' === $onglet ? $qualite->ecartsDeForme() : null,
            'relances' => 'notifs' === $onglet ? $qualite->relances() : [],
            'decisions' => 'decisions' === $onglet ? $qualite->decisions() : [],
            'form_recalcul' => 'miroir' === $onglet
                ? $this->createForm(ActionType::class, null, [
                    'action' => $this->generateUrl('app_mdm_qualite_recalculer'),
                    'button_label' => 'Recalculer les statistiques',
                    'button_attr' => ['data-variant' => 'outline', 'data-size' => 'sm', 'data-full' => '0'],
                    'csrf_token_id' => 'qualite-recalcul',
                ])->createView()
                : null,
        ], $suggestionsData));
    }

    #[Route('/qualite/suggestions/{decision}', name: 'app_mdm_qualite_suggestions', requirements: ['decision' => 'accepter|ignorer'], methods: ['POST'])]
    #[IsGranted('ROLE_BP_VALIDATOR')]
    public function arbitrerSuggestions(string $decision, Request $request, SuggestionsBulkArbitre $arbitre, CurrentActorProvider $actor): Response
    {
        $soumis = $request->request->all()['suggestion_selection'] ?? [];
        $idsSoumis = is_array($soumis) && is_array($soumis['ids'] ?? null) ? $soumis['ids'] : [];
        $choices = [];
        foreach ($idsSoumis as $id) {
            if (is_string($id) && 1 === preg_match('/^(adresse|suggestion):[0-9A-HJKMNP-TV-Z]{26}$/', $id)) {
                $choices[$id] = $id;
            }
        }
        $retour = $this->redirectToRoute('app_mdm_qualite', array_filter([
            'onglet' => 'conflits',
            'src' => $request->query->getString('src') ?: null,
            'page' => $request->query->getInt('page') ?: null,
        ]));
        $form = $this->createForm(SuggestionSelectionType::class, null, ['ids_choices' => $choices]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'La décision est invalide (jeton expiré ?). Rechargez la page.');

            return $retour;
        }
        /** @var array{ids?: list<string>} $data */
        $data = $form->getData();
        $ids = $data['ids'] ?? [];
        if ([] === $ids) {
            $this->addFlash('error', 'Aucune ligne sélectionnée.');

            return $retour;
        }
        $bilan = $arbitre->arbitrer($ids, $decision, $actor->id());
        $verbe = 'accepter' === $decision ? 'appliquée(s)' : 'ignorée(s)';
        $this->addFlash('success', 0 === $bilan['echecs']
            ? sprintf('%d suggestion(s) %s.', $bilan['ok'], $verbe)
            : sprintf('%d suggestion(s) %s, %d ignorée(s) (déjà arbitrée ou sans proposition).', $bilan['ok'], $verbe, $bilan['echecs']));

        return $retour;
    }

    #[Route('/qualite/recalculer', name: 'app_mdm_qualite_recalculer', methods: ['POST'])]
    public function recalculer(Request $request, MessageBusInterface $bus): Response
    {
        $form = $this->createForm(ActionType::class, null, [
            'button_label' => 'Recalculer les statistiques',
            'csrf_token_id' => 'qualite-recalcul',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Les mêmes messages que les passages planifiés (15 min / 04:30) —
            // traités par les workers, le temps d'un rechargement.
            $bus->dispatch(new ComputeDashboardStats());
            $bus->dispatch(new ComputeFieldFillRates());
            $this->addFlash('success', 'Recalcul lancé — les indicateurs se rafraîchissent d\'ici quelques instants.');
        }

        return $this->redirectToRoute('app_mdm_qualite');
    }

    #[Route('/qualite/doublon-texte/{id}/{decision}', name: 'app_mdm_qualite_doublon_texte', requirements: ['decision' => 'confirmer|ignorer'], methods: ['POST'])]
    #[IsGranted('ROLE_BP_VALIDATOR')]
    public function doublonTexte(string $id, string $decision, Request $request, TextDuplicateFormFactory $doublonForms, TextDuplicateArbitre $arbitre, CurrentActorProvider $actor): Response
    {
        // Même nom et même jeton que le formulaire rendu par l'écran Qualité.
        $form = $doublonForms->action($id, $decision);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'La décision est invalide (jeton expiré ?). Rechargez la page.');
        } elseif ($arbitre->decide($id, $decision, $actor->id())) {
            $this->addFlash('success', 'confirmer' === $decision
                ? 'Doublon confirmé — il ne sera plus signalé.'
                : 'Signalement ignoré — la saisie est conservée.');
        }

        return $this->redirectToRoute('app_mdm_qualite', ['onglet' => 'doublons_textes']);
    }
}
