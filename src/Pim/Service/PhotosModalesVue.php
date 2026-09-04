<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Service\FichePhotoPresenter;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Form\LieuPhotoMetadataType;
use App\Pim\Form\LieuPhotoReplaceType;
use App\Shared\Service\ParametreProviderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Photos d'une fiche et les formulaires de leur modale de paramètres
 * (métadonnées avec recadrage, remplacement, URL présignée de l'original),
 * toutes gammes. Rendues par FichePhotoController::modales et préchargées
 * en arrière-plan par le contrôleur Stimulus lieu-media. Seul le Lieu
 * rattache ses photos de salle à une salle de réunion depuis la modale.
 */
final readonly class PhotosModalesVue
{
    public function __construct(
        private FichePhotoPresenter $presenter,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private ParametreProviderInterface $parametres,
    ) {
    }

    /** @return array<string, mixed> Variables du gabarit des modales : photos et dimensions minimales du recadrage. */
    public function variables(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        return [
            'photos' => $this->photos($entite),
            'image_min_width' => $this->parametres->int('dam.image_largeur_min'),
            'image_min_height' => $this->parametres->int('dam.image_hauteur_min'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function photos(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        $slug = $entite->fiche()->type()->slug();
        $photos = $this->presenter->photos($entite->fiche());
        foreach ($photos as &$photo) {
            $resource = $photo['resource'];
            $crop = $resource->crop();
            $params = ['gamme' => $slug, 'id' => $entite->id(), 'resourceId' => $resource->id()];
            $options = ['action' => $this->urls->generate('app_pim_fiche_photo_update', $params), 'method' => 'PATCH'];
            $options += $entite instanceof Lieu ? ['salles' => array_values($entite->salles()->toArray())] : ['avec_salles' => false];
            $photo['metadata_form'] = $this->forms->createNamed('photo_metadata_'.$resource->id(), LieuPhotoMetadataType::class, [
                'usage' => $resource->usage(), 'legende' => $resource->legende(), 'source' => $resource->source(),
                'keywords' => $resource->keywords(), 'rights_expires_at' => $resource->rightsExpiresAt(), 'salle_id' => $resource->salle(),
                'crop_x' => $crop['x'] ?? null, 'crop_y' => $crop['y'] ?? null, 'crop_width' => $crop['width'] ?? null,
                'crop_height' => $crop['height'] ?? null, 'rotation' => $resource->rotation(),
            ], $options)->createView();
            $photo['replace_form'] = $this->forms->createNamed('photo_replace_'.$resource->id(), LieuPhotoReplaceType::class, null, [
                'action' => $this->urls->generate('app_pim_fiche_photo_replace', $params), 'method' => 'POST',
            ])->createView();
            $photo['original_url'] = $this->urls->generate('app_pim_fiche_photo_original', $params);
        }
        unset($photo);

        return $photos;
    }
}
