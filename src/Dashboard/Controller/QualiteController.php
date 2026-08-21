<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Message\ComputeDashboardStats;
use App\Dashboard\Message\ComputeFieldFillRates;
use App\Account\Service\CurrentActorProvider;
use App\Dashboard\Repository\QualiteRepository;
use App\Pim\Form\AdresseSuggestionFormFactory;
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
    public function __invoke(Request $request, QualiteRepository $qualite, AdresseSuggestionFormFactory $adresseForms, TextDuplicateFormFactory $doublonForms): Response
    {
        $onglet = $request->query->getString('onglet');
        if (!array_key_exists($onglet, self::ONGLETS)) {
            $onglet = 'miroir';
        }
        // Deux files distinctes : les écarts arbitrables en un clic (la BAN
        // propose autre chose) et les adresses sans résultat fiable, à
        // corriger à la main dans la fiche.
        $filtreAdresses = 'sans' === $request->query->get('adresses') ? 'sans' : 'avec';

        return $this->render('dashboard/qualite.html.twig', [
            'onglets' => self::ONGLETS,
            'onglet_actif' => $onglet,
            'badges' => $qualite->badges(),
            'sante' => 'miroir' === $onglet ? $qualite->santeParGamme() : [],
            'champs_faibles' => 'miroir' === $onglet ? $qualite->champsFaibles() : [],
            'suggestions' => 'conflits' === $onglet ? $qualite->suggestionsEnAttente() : [],
            // Mêmes décisions un clic que le bloc « Suggestions en attente »
            // de la fiche, avec retour sur cet écran (filtre conservé).
            'suggestions_adresse' => 'conflits' === $onglet
                ? array_map(static fn (array $ligne): array => $ligne + [
                    'accepter' => null === $ligne['proposition']
                        ? null
                        : $adresseForms->action($ligne['fiche_id'], 'accepter', 'qualite', $filtreAdresses)->createView(),
                    'ignorer' => $adresseForms->action($ligne['fiche_id'], 'ignorer', 'qualite', $filtreAdresses)->createView(),
                ], $qualite->suggestionsAdresse(20, 'avec' === $filtreAdresses))
                : [],
            'adresses_filtre' => $filtreAdresses,
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
        ]);
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
