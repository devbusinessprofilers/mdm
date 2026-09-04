<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Service\FicheImageUploader;
use App\Dam\Service\ImageVariantRegistry;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\IndexFiche;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Photos d'une fiche, toutes gammes : dépôt, ordre, catégorie, métadonnées,
 * remplacement, relance des rendus et suppression. Les photos de salle
 * (Lieu, Restaurant) se rattachent à une salle de la fiche.
 */
final readonly class FichePhotoManager
{
    public function __construct(
        private FicheImageUploader $uploader,
        private PhotoObligations $photoObligations,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private ParametreProviderInterface $parametres,
    ) {
    }

    /** @return list<RessourceLieu> */
    public function photos(Lieu|Restaurant|Activite|ServiceEvenementiel $lieu): array
    {
        return array_values(array_filter($lieu->ressources()->toArray(), static fn (RessourceLieu $resource): bool => NatureRessource::Photo === $resource->nature()));
    }

    /** @param list<UploadedFile> $files */
    public function upload(Lieu|Restaurant|Activite|ServiceEvenementiel $lieu, array $files): int
    {
        $count = count($this->photos($lieu));
        $maximum = $this->photoObligations->maximum($lieu->fiche()->type());
        if ($count >= $maximum) {
            throw new \DomainException('Le nombre maximal de photos est atteint.');
        }
        if ([] === $files || $count + count($files) > $maximum) {
            throw new \DomainException('Sélectionnez entre 1 et '.($maximum - $count).' image(s).');
        }
        $uploaded = [];
        try {
            foreach ($files as $offset => $file) {
                $asset = $this->uploader->upload($file, $lieu->fiche());
                $uploaded[] = $asset;
                $resource = new RessourceLieu();
                $resource->changeDamAssetId($asset->id());
                $resource->changeNature(NatureRessource::Photo);
                $resource->changeUsage('PHOTO_DIVERSE');
                $resource->changePosition($count + $offset);
                $lieu->addRessource($resource);
                $this->entityManager->persist($asset);
                $this->outbox->enqueue(new MediaUploaded($asset->id(), $asset->originalStorageKey(), $asset->checksum(), ImageVariantRegistry::names()));
            }
            $this->changed($lieu);
        } catch (\Throwable $exception) {
            $this->cleanup($uploaded);
            throw $exception;
        }

        return count($uploaded);
    }

    /** @param list<string> $ids */
    public function reorder(Lieu|Restaurant|Activite|ServiceEvenementiel $lieu, array $ids): int
    {
        $photos = $this->photos($lieu);
        $known = array_map(static fn (RessourceLieu $photo): string => $photo->id(), $photos);
        if (count($ids) !== count($known) || [] !== array_diff($ids, $known) || [] !== array_diff($known, $ids)) {
            throw new \DomainException("L'ordre transmis ne correspond pas aux photos du lieu.");
        }
        $byId = [];
        foreach ($photos as $photo) {
            $byId[$photo->id()] = $photo;
        }
        // La principale est la première photo de l'ordre : réordonner suffit.
        foreach ($ids as $position => $id) {
            $byId[$id]->changePosition($position);
        }
        $this->changed($lieu);

        return count($ids);
    }

    /**
     * Change la catégorie de la photo (select inline de la galerie) et, pour
     * une photo de salle, la salle rattachée — sans toucher aux autres
     * métadonnées. Sans salle transmise, la salle courante est conservée,
     * sinon la première salle du lieu sert de rattachement par défaut (la
     * barre posée sur la photo permet ensuite d'en changer).
     */
    public function changeCategorie(RessourceLieu $resource, Lieu|Restaurant|Activite|ServiceEvenementiel $lieu, string $usage, ?string $salleId = null): void
    {
        if (!isset(PhotoUsageCatalog::LABELS[$usage])) {
            throw new \DomainException('Catégorie de photo invalide.');
        }
        $resource->rattacherSalle(PhotoUsageCatalog::SALLE === $usage ? self::salleRattachee($resource, $lieu, (string) $salleId) : null);
        $resource->changeUsage($usage);
        $this->changed($lieu);
    }

    /**
     * Salle d'une photo de salle : celle demandée (qui doit appartenir à la
     * fiche), sinon la salle déjà rattachée, sinon la première de la fiche.
     * Un Lieu a des salles de réunion, un Restaurant ses propres salles ;
     * les autres gammes n'en ont pas.
     */
    private static function salleRattachee(RessourceLieu $resource, Lieu|Restaurant|Activite|ServiceEvenementiel $fiche, string $salleId): Salle|RestaurantSalle
    {
        if (!$fiche instanceof Lieu && !$fiche instanceof Restaurant) {
            throw new \DomainException('Les photos de salle sont réservées aux fiches Lieu et Restaurant.');
        }
        if ('' !== $salleId) {
            foreach ($fiche->salles() as $candidate) {
                if ($candidate->id() === $salleId) {
                    return $candidate;
                }
            }
            throw new \DomainException("La salle n'appartient pas à cette fiche.");
        }
        $courante = $resource->salleRattachee();

        return $courante ?? ($fiche->salles()->first() ?: null)
            ?? throw new \DomainException('Créez d’abord une salle pour y rattacher cette photo.');
    }

    /** @param array<string, mixed> $data */
    public function update(RessourceLieu $resource, Lieu|Restaurant|Activite|ServiceEvenementiel $lieu, array $data, string $actor): void
    {
        $usage = (string) ($data['usage'] ?? '');
        if (!isset(PhotoUsageCatalog::LABELS[$usage])) {
            throw new \DomainException('Catégorie de photo invalide.');
        }
        $salle = PhotoUsageCatalog::SALLE === $usage ? self::salleRattachee($resource, $lieu, (string) ($data['salle_id'] ?? '')) : null;
        $resource->changeUsage($usage);
        $resource->changeLegende((string) ($data['legende'] ?? ''));
        $resource->changeSource((string) ($data['source'] ?? ''));
        $resource->changeKeywords((string) ($data['keywords'] ?? ''));
        $expiration = $data['rights_expires_at'] ?? null;
        if (is_string($expiration) && '' !== $expiration) {
            // Chaîne arbitraire du payload : une date malformée doit finir en 422, pas en 500.
            try {
                $expiration = new \DateTimeImmutable($expiration);
            } catch (\Exception) {
                throw new \DomainException("Date d'expiration des droits invalide.");
            }
        }
        $resource->changeRightsExpiresAt($expiration instanceof \DateTimeImmutable ? $expiration : null);
        $resource->rattacherSalle($salle);
        $keys = ['crop_x', 'crop_y', 'crop_width', 'crop_height'];
        $crop = array_map(static fn (string $key): ?int => '' === (string) ($data[$key] ?? '') ? null : (int) $data[$key], $keys);
        // Le rognage ne doit pas produire une image sous les minima imposés à
        // l'upload — même plancher que celui appliqué par la modale de recadrage.
        if (null !== $crop[2] && $crop[2] < $this->parametres->int('dam.image_largeur_min')) {
            throw new \DomainException(sprintf('La zone recadrée doit faire au moins %d px de large.', $this->parametres->int('dam.image_largeur_min')));
        }
        if (null !== $crop[3] && $crop[3] < $this->parametres->int('dam.image_hauteur_min')) {
            throw new \DomainException(sprintf('La zone recadrée doit faire au moins %d px de haut.', $this->parametres->int('dam.image_hauteur_min')));
        }
        $rotation = (int) ($data['rotation'] ?? 0);
        $changed = $resource->crop() !== (null === $crop[0] ? null : ['x' => $crop[0], 'y' => $crop[1], 'width' => $crop[2], 'height' => $crop[3]]) || $resource->rotation() !== $rotation;
        $resource->changeCrop(...$crop);
        $resource->changeRotation($rotation);
        if ($changed) {
            $this->outbox->enqueue(new RegenerateMedia($resource->damAssetId()));
        }
        $this->changed($lieu);
    }

    public function replace(RessourceLieu $resource, Lieu|Restaurant|Activite|ServiceEvenementiel $lieu, UploadedFile $file): void
    {
        $old = $resource->damAssetId();
        $asset = $this->uploader->upload($file, $lieu->fiche());
        try {
            $this->entityManager->persist($asset);
            $resource->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new MediaUploaded($asset->id(), $asset->originalStorageKey(), $asset->checksum(), ImageVariantRegistry::names()));
            $this->outbox->enqueue(new DeleteMedia($old));
            $this->changed($lieu);
        } catch (\Throwable $exception) {
            $this->cleanup([$asset]);
            throw $exception;
        }
    }

    public function retry(RessourceLieu $resource): void
    {
        $this->outbox->enqueue(new RegenerateMedia($resource->damAssetId()));
        $this->entityManager->flush();
    }

    public function delete(RessourceLieu $resource, Lieu|Restaurant|Activite|ServiceEvenementiel $lieu): void
    {
        $id = $resource->damAssetId();
        $lieu->removeRessource($resource);
        $this->entityManager->remove($resource);
        $this->outbox->enqueue(new DeleteMedia($id));
        $this->changed($lieu);
    }

    private function changed(Lieu|Restaurant|Activite|ServiceEvenementiel $lieu): void
    {
        $lieu->markChanged();
        $this->outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
        $this->entityManager->flush();
    }

    /** @param list<MediaAsset> $assets */
    private function cleanup(array $assets): void
    {
        foreach ($assets as $asset) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
        }
    }
}
