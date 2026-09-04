<?php

declare(strict_types=1);

namespace App\Pim\Service\Editeur;

use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\FichePhotoPresenter;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\LieuPhotoUploadType;
use App\Pim\Service\DocumentsModalesVue;
use App\Pim\Service\LieuAdminViewBuilder;
use App\Pim\Service\PhotoUsageCatalog;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Bloc Médias de l'éditeur (galerie de photos, tuiles et modales des
 * documents, onglets internes actifs par gamme). Le bloc est aussi re-rendu
 * seul par FicheMediasBlocController après chaque action média, sans
 * recharger la page.
 */
final readonly class EditeurMedias
{
    public function __construct(
        private LieuAdminViewBuilder $lieuVue,
        private DocumentsModalesVue $documentsVue,
        private MediaAssetRepository $mediaAssets,
        private FichePhotoPresenter $fichePhotos,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrfTokens,
    ) {
    }

    /** @return array{gamme: string, bloc_url: string, onglets_actifs: array<string, bool>, vars: array<string, mixed>} */
    public function variables(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        $type = $entite->fiche()->type();
        $slug = $type->slug();
        $blocUrl = $this->urls->generate('app_pim_fiche_medias_bloc', ['gamme' => $slug, 'id' => (string) $entite->id()]);
        if ($entite instanceof Lieu) {
            $vue = $this->lieuVue->mediasVars($entite);

            return [
                'gamme' => 'lieu',
                'bloc_url' => $blocUrl,
                'onglets_actifs' => self::ongletsMediasActifs(TypeFiche::Lieu),
                'vars' => [
                    'lieu' => $entite,
                    'creation' => false,
                    'photos' => $vue['photos'],
                    'documents' => $vue['documents'],
                    'salles' => $vue['salles'],
                    'document_upload_forms' => $vue['document_upload_forms'],
                    'media_upload_form' => $vue['media_upload_form'],
                    'media_csrf_token' => $vue['media_csrf_token'],
                    'photo_usages' => self::photoUsagesInline(),
                ],
            ];
        }
        $documentsVue = $this->documentsVue->documents($entite);

        // Même présentation que le lieu : les vignettes réelles des photos
        // (déposées par le portail prestataire) et le poids des documents.
        $assets = [];
        foreach ($this->mediaAssets->findByStringIds(array_map(
            static fn (array $d): string => $d['resource']->damAssetId(),
            $documentsVue,
        )) as $asset) {
            $assets[$asset->id()] = $asset;
        }
        $documents = [];
        foreach ($documentsVue as $document) {
            $documents[] = $document + [
                'asset' => $assets[$document['resource']->damAssetId()] ?? null,
                'onglet' => $document['resource']->documentUsage()?->ongletMedia() ?? 'documents',
            ];
        }

        return [
            'gamme' => $type->value,
            'bloc_url' => $blocUrl,
            'onglets_actifs' => self::ongletsMediasActifs($type),
            'vars' => [
                'photos' => $this->fichePhotos->photos($entite->fiche()),
                // Un Restaurant a ses salles : même catégorie « Salle » et même
                // barre de rattachement que le Lieu dans la galerie.
                'salles' => $entite instanceof Restaurant ? self::sallesParId($entite) : [],
                'documents' => $documents,
                'entite_id' => (string) $entite->id(),
                // Galerie gérée comme le Lieu : dépôt AJAX + modales préchargées.
                'gamme_slug' => $slug,
                'media_upload_form' => $this->forms->createNamed('gamme_photo_upload', LieuPhotoUploadType::class, null, [
                    'action' => $this->urls->generate('app_pim_fiche_photo_upload', ['gamme' => $slug, 'id' => (string) $entite->id()]),
                    'method' => 'POST',
                ])->createView(),
                'media_csrf_token' => $this->csrfTokens->getToken('lieu-media-'.$entite->id())->getValue(),
                'photo_usages' => self::photoUsagesInline(),
            ],
        ];
    }

    /**
     * Onglets internes du volet Médias disponibles par gamme — les onglets
     * sans aucun usage documentaire possible sont grisés dans le shell.
     *
     * @return array<string, bool>
     */
    public static function ongletsMediasActifs(TypeFiche $type): array
    {
        return [
            'photos' => true,
            'plans' => in_array($type, [TypeFiche::Lieu, TypeFiche::Restaurant], true),
            'supports' => true,
            'video' => true,
            'documents' => TypeFiche::Lieu === $type,
        ];
    }

    /**
     * Catégories proposées par le select inline sous chaque vignette.
     * « Salle de réunion » fait apparaître la barre de choix de salle sur la
     * photo ; « Plan de salle » reste réservé à la modale de paramètres.
     *
     * @return array<string, string>
     */
    public static function photoUsagesInline(): array
    {
        return array_diff_key(PhotoUsageCatalog::LABELS, ['CONFIG_PLAN_SALLE' => true]);
    }

    /** @return array<string, string> id de salle => nom, pour la barre de rattachement des photos */
    private static function sallesParId(Restaurant $restaurant): array
    {
        $salles = [];
        foreach ($restaurant->salles() as $salle) {
            $salles[$salle->id()] = $salle->nom();
        }

        return $salles;
    }
}
