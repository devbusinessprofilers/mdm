<?php

declare(strict_types=1);

namespace App\Dam\Controller;

use App\Dam\Enum\DamAnomalyType;
use App\Dam\Enum\RightsValidityStatus;
use App\Dam\Message\ScanDamAnomalies;
use App\Dam\Repository\DamAnomalyRepository;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\DamDashboardProvider;
use App\Dam\Service\ImageVariantRegistry;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Form\ActionType;
use App\Shared\Service\ParametreProviderInterface;
use App\Vision\Enum\EnhancementProvider;
use App\Vision\Form\VisionFormFactory;
use App\Vision\Service\VisionDashboardProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran « Médias » de la maquette : le DAM en onglets. Les onglets adossés à
 * des données réutilisent le contenu de la supervision DAM existante (même
 * provider, mêmes actions) ; les onglets IA (retouche, reconnaissance)
 * s'appuient sur le module Vision et restent visibles — actions désactivées —
 * tant qu'OPENAI_ENABLED vaut 0.
 */
final class MediasController extends AbstractController
{
    public const ONGLETS = [
        'biblio' => 'Bibliothèque',
        'import' => 'Import & retouche',
        'ia' => 'Reconnaissance IA',
        'meta' => 'Métadonnées & types',
        'formats' => 'Formats & CDN',
        'droits' => 'Droits & consentement',
        'doublons' => 'Doublons',
        'sync' => 'Synchronisation PIM',
    ];

    /** Groupes du rail : le DAM proprement dit, puis les fonctions IA. */
    public const GROUPES = [
        'Médias — DAM' => ['biblio', 'meta', 'formats', 'droits', 'doublons', 'sync'],
        'Intelligence artificielle' => ['import', 'ia'],
    ];

    /** Filtre du provider affiché par défaut quand on ouvre l'onglet. */
    private const FILTRE_PAR_DEFAUT = [
        'biblio' => DamDashboardProvider::FILTER_IMAGES,
        'droits' => DamDashboardProvider::FILTER_RIGHTS_MISSING,
        'doublons' => DamDashboardProvider::FILTER_DUPLICATES,
        'sync' => DamDashboardProvider::FILTER_ORPHANS,
    ];

    /** Onglet auquel appartient chaque filtre du provider (navigation interne). */
    private const ONGLET_PAR_FILTRE = [
        DamDashboardProvider::FILTER_IMAGES => 'biblio',
        DamDashboardProvider::FILTER_DOCUMENTS => 'biblio',
        DamDashboardProvider::FILTER_RIGHTS_MISSING => 'droits',
        DamDashboardProvider::FILTER_RIGHTS_EXPIRING => 'droits',
        DamDashboardProvider::FILTER_RIGHTS_EXPIRED => 'droits',
        DamDashboardProvider::FILTER_PUBLISHED_RIGHTS => 'droits',
        DamDashboardProvider::FILTER_PUBLISHED_NO_PHOTO => 'droits',
        DamDashboardProvider::FILTER_DUPLICATES => 'doublons',
        DamDashboardProvider::FILTER_ORPHANS => 'sync',
        DamDashboardProvider::FILTER_MISSING_RENDITIONS => 'sync',
        DamDashboardProvider::FILTER_FAILED => 'sync',
    ];

    /**
     * Coquille immédiate : rail, en-tête et squelette de tableau. Les agrégats
     * lourds (stockage, régimes, pages d'items et leurs formulaires) sont servis
     * par contenu() dans la frame Turbo — seuls les comptes des badges du rail
     * sont calculés ici.
     */
    #[Route('/medias', name: 'app_mdm_medias', methods: ['GET'])]
    public function __invoke(
        Request $request,
        DamDashboardProvider $provider,
        VisionDashboardProvider $vision,
    ): Response {
        [$onglet, $filtre] = self::navigation($request);

        return $this->render('dam/medias.html.twig', [
            'onglets' => self::ONGLETS,
            'groupes' => self::GROUPES,
            'onglet_actif' => $onglet,
            'contenu_charge' => false,
            'contenu_url' => $this->generateUrl('app_mdm_medias_contenu', array_filter([
                'onglet' => $onglet,
                'filter' => $filtre,
                'type' => $request->query->getString('type'),
                'page' => $request->query->getInt('page', 1),
            ], static fn (string|int $valeur): bool => '' !== $valeur && 1 !== $valeur)),
            'rail_stats' => $provider->railStats(),
            'vision_badges' => $vision->badges(),
            'variantes' => ImageVariantRegistry::all(),
            'scan_form' => 'sync' === $onglet
                ? $this->createForm(ActionType::class, null, [
                    'action' => $this->generateUrl('app_mdm_medias_scanner'),
                    'button_label' => 'Relancer le scan des anomalies',
                    'csrf_token_id' => 'medias-scan',
                ])->createView()
                : null,
        ]);
    }

    /** Contenu de l'onglet actif, chargé par la frame Turbo de la coquille. */
    #[Route('/medias/contenu', name: 'app_mdm_medias_contenu', methods: ['GET'])]
    public function contenu(
        Request $request,
        DamDashboardProvider $provider,
        MediaAssetRepository $assets,
        RessourceLieuRepository $resources,
        DamAnomalyRepository $anomalies,
        VisionDashboardProvider $vision,
        VisionFormFactory $visionForms,
        ParametreProviderInterface $parametres,
    ): Response {
        [$onglet, $filtre] = self::navigation($request);
        // Les stats alimentent les badges du rail sur tous les onglets ; les
        // items ne sont parcourus que par les onglets qui ont une file.
        $vue = $provider->page(
            '' !== $filtre ? $filtre : (self::FILTRE_PAR_DEFAUT[$onglet] ?? DamDashboardProvider::FILTER_IMAGES),
            $request->query->getString('type') ?: null,
            $request->query->getInt('page', 1),
        );

        // Répartition réelle des régimes de droits — le bloc santé de l'onglet.
        $regimes = [];
        if ('droits' === $onglet) {
            foreach (RightsValidityStatus::cases() as $status) {
                $regimes[$status->value] = $resources->countByRightsStatus($status);
            }
        }

        // Onglets IA : listes paginées et formulaires. L'activation pilote
        // seulement les actions — les onglets restent visibles pour expliquer
        // la fonction quand OPENAI_ENABLED vaut 0.
        $iaActive = $parametres->bool('openai.actif');
        $pageVision = $request->query->getInt('page', 1);
        $retouche = null;
        $retoucheAuto = null;
        $reco = null;
        if ('import' === $onglet) {
            $retouche = $vision->retouchePage($pageVision, EnhancementProvider::OpenAi);
            $retouche['items'] = $visionForms->addRetoucheActions($retouche['items'], $retouche['page']);
            $retoucheAuto = $vision->retouchePage($request->query->getInt('page_auto', 1), EnhancementProvider::ImageMagick);
            $retoucheAuto['items'] = $visionForms->addRetoucheActions($retoucheAuto['items'], $retoucheAuto['page'], 'page_auto');
        } elseif ('ia' === $onglet) {
            $reco = $vision->recoPage($pageVision);
            $reco['items'] = $visionForms->addRecoActions($reco['items'], $reco['page']);
        }
        $recoStats = 'ia' === $onglet ? $vision->recoStats() : null;
        $retoucheStats = 'import' === $onglet ? $vision->retoucheStats() : null;

        return $this->render('dam/medias.html.twig', [
            'onglets' => self::ONGLETS,
            'groupes' => self::GROUPES,
            'onglet_actif' => $onglet,
            'contenu_charge' => true,
            'stockage' => 'biblio' === $onglet ? $assets->storageStats() : null,
            'regimes' => $regimes,
            'manquantes' => 'formats' === $onglet ? $anomalies->countOpen(DamAnomalyType::MissingRenditions) : 0,
            'formats' => 'formats' === $onglet ? $assets->renditionStats() : [],
            'variantes' => ImageVariantRegistry::all(),
            // Rendu dans l'en-tête de la coquille, hors frame : inutile ici.
            'scan_form' => null,
            'ia_active' => $iaActive,
            'reco_auto_active' => $iaActive && $parametres->bool('openai.reco_auto_active'),
            'vision_badges' => $vision->badges(),
            'retouche' => $retouche,
            'retouche_auto' => $retoucheAuto,
            'reco' => $reco,
            'lancement_retouche_form' => 'import' === $onglet && $iaActive ? $visionForms->lancementRetouche()->createView() : null,
            'lancement_retouche_auto_form' => 'import' === $onglet ? $visionForms->lancementRetoucheAuto()->createView() : null,
            'lancement_reco_form' => 'ia' === $onglet && $iaActive ? $visionForms->lancementReco()->createView() : null,
            'reco_stats' => $recoStats,
            'retouche_stats' => $retoucheStats,
            'lancement_reco_masse_form' => 'ia' === $onglet && $iaActive ? $visionForms->lancementRecoMasse()->createView() : null,
        ] + $vue);
    }

    #[Route('/medias/scanner', name: 'app_mdm_medias_scanner', methods: ['POST'])]
    public function scanner(Request $request, MessageBusInterface $bus): RedirectResponse
    {
        $form = $this->createForm(ActionType::class, null, ['csrf_token_id' => 'medias-scan']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $bus->dispatch(new ScanDamAnomalies());
            $this->addFlash('success', 'Scan des anomalies planifié.');
        }

        return $this->redirectToRoute('app_mdm_medias', ['onglet' => 'sync']);
    }

    /** @return array{string, string} Onglet actif et filtre demandé (vide si absent). */
    private static function navigation(Request $request): array
    {
        $filtre = $request->query->getString('filter');
        $onglet = $request->query->getString('onglet');
        if ('' !== $filtre && isset(self::ONGLET_PAR_FILTRE[$filtre])) {
            // Un clic sur une carte ou une pagination du contenu réutilisé
            // porte le filtre : l'onglet actif s'en déduit.
            $onglet = self::ONGLET_PAR_FILTRE[$filtre];
        }
        if (!array_key_exists($onglet, self::ONGLETS)) {
            $onglet = 'biblio';
        }

        return [$onglet, $filtre];
    }
}
