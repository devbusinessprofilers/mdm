<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Repository\QualiteRepository;
use App\Pim\Form\AdresseSuggestionFormFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        'formes' => 'Écarts de forme',
        'notifs' => 'Notifications',
        'decisions' => 'Décisions d\'arbitrage',
    ];

    #[Route('/qualite', name: 'app_mdm_qualite', methods: ['GET'])]
    public function __invoke(Request $request, QualiteRepository $qualite, AdresseSuggestionFormFactory $adresseForms): Response
    {
        $onglet = $request->query->getString('onglet');
        if (!array_key_exists($onglet, self::ONGLETS)) {
            $onglet = 'miroir';
        }

        return $this->render('dashboard/qualite.html.twig', [
            'onglets' => self::ONGLETS,
            'onglet_actif' => $onglet,
            'sante' => 'miroir' === $onglet ? $qualite->santeParGamme() : [],
            'suggestions' => 'conflits' === $onglet ? $qualite->suggestionsEnAttente() : [],
            // Mêmes décisions un clic que le bloc « Suggestions en attente »
            // de la fiche, avec retour sur cet écran après le POST.
            'suggestions_adresse' => 'conflits' === $onglet
                ? array_map(static fn (array $ligne): array => $ligne + [
                    'accepter' => null === $ligne['proposition']
                        ? null
                        : $adresseForms->action($ligne['fiche_id'], 'accepter', 'qualite')->createView(),
                    'ignorer' => $adresseForms->action($ligne['fiche_id'], 'ignorer', 'qualite')->createView(),
                ], $qualite->suggestionsAdresse())
                : [],
            'doublons_adresse' => 'conflits' === $onglet ? $qualite->doublonsAdresse() : [],
            'formes' => 'formes' === $onglet ? $qualite->ecartsDeForme() : null,
            'relances' => 'notifs' === $onglet ? $qualite->relances() : [],
            'decisions' => 'decisions' === $onglet ? $qualite->decisions() : [],
        ]);
    }
}
