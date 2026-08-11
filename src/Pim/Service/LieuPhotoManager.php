<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Service\ImageVariantRegistry;
use App\Dam\Service\LieuImageUploader;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\IndexFiche;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class LieuPhotoManager
{
    public const MAX_PHOTOS = 25;
    private const USAGES = ['PHOTO_PRINCIPALE', 'PHOTO_FACADE', 'PHOTO_CHAMBRE', 'PHOTO_RESTAURATION', 'CONFIG_PHOTO_SALLE', 'PHOTO_DIVERSE', 'CONFIG_PLAN_SALLE', 'LOISIR_EXTERNE_PHOTO', 'PHOTO'];

    public function __construct(
        private LieuImageUploader $uploader,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
    ) {}

    /** @return list<RessourceLieu> */
    public function photos(Lieu $lieu): array
    {
        return array_values(array_filter($lieu->ressources()->toArray(), static fn (RessourceLieu $resource): bool => NatureRessource::Photo === $resource->nature()));
    }

    /** @param list<UploadedFile> $files */
    public function upload(Lieu $lieu, array $files): int
    {
        $count = count($this->photos($lieu));
        if ($count >= self::MAX_PHOTOS) { throw new \DomainException('Le nombre maximal de photos est atteint.'); }
        if ([] === $files || $count + count($files) > self::MAX_PHOTOS) { throw new \DomainException('Sélectionnez entre 1 et '.(self::MAX_PHOTOS - $count).' image(s).'); }
        $uploaded = [];
        try {
            foreach ($files as $offset => $file) {
                $asset = $this->uploader->upload($file, $lieu);
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
        } catch (\Throwable $exception) { $this->cleanup($uploaded); throw $exception; }

        return count($uploaded);
    }

    /** @param list<string> $ids */
    public function reorder(Lieu $lieu, array $ids): int
    {
        $photos = $this->photos($lieu);
        $known = array_map(static fn (RessourceLieu $photo): string => $photo->id(), $photos);
        if (count($ids) !== count($known) || [] !== array_diff($ids, $known) || [] !== array_diff($known, $ids)) {
            throw new \DomainException("L'ordre transmis ne correspond pas aux photos du lieu.");
        }
        $byId = [];
        foreach ($photos as $photo) { $byId[$photo->id()] = $photo; }
        foreach ($ids as $position => $id) { $byId[$id]->changePosition($position); }
        $this->changed($lieu);

        return count($ids);
    }

    /** @param array<string, mixed> $data */
    public function update(RessourceLieu $resource, Lieu $lieu, array $data, string $actor): void
    {
        $usage = (string) ($data['usage'] ?? '');
        if (!in_array($usage, self::USAGES, true)) { throw new \DomainException('Catégorie de photo invalide.'); }
        if ('PHOTO_PRINCIPALE' === $usage) {
            foreach ($this->photos($lieu) as $other) {
                if ($other !== $resource && 'PHOTO_PRINCIPALE' === $other->usage()) { throw new \DomainException('Une seule photo principale est autorisée par lieu.'); }
            }
        }
        $salle = null;
        $salleId = (string) ($data['salle_id'] ?? '');
        if ('' !== $salleId) {
            foreach ($lieu->salles() as $candidate) { if ($candidate->id() === $salleId) { $salle = $candidate; break; } }
            if (null === $salle) { throw new \DomainException("La salle n'appartient pas à ce lieu."); }
        }
        if ('CONFIG_PHOTO_SALLE' === $usage && null === $salle) { throw new \DomainException('Une photo de salle doit être associée à une salle.'); }
        if ('CONFIG_PHOTO_SALLE' !== $usage) { $salle = null; }
        $resource->changeUsage($usage);
        $resource->changeLegende((string) ($data['legende'] ?? ''));
        $resource->changeSource((string) ($data['source'] ?? ''));
        $resource->changeKeywords((string) ($data['keywords'] ?? ''));
        $expiration = $data['rights_expires_at'] ?? null;
        if (is_string($expiration) && '' !== $expiration) {
            // Chaîne arbitraire du payload : une date malformée doit finir en 422, pas en 500.
            try { $expiration = new \DateTimeImmutable($expiration); } catch (\Exception) { throw new \DomainException("Date d'expiration des droits invalide."); }
        }
        $resource->changeRightsExpiresAt($expiration instanceof \DateTimeImmutable ? $expiration : null);
        $resource->changeSalle($salle);
        $keys = ['crop_x', 'crop_y', 'crop_width', 'crop_height'];
        $crop = array_map(static fn (string $key): ?int => '' === (string) ($data[$key] ?? '') ? null : (int) $data[$key], $keys);
        $rotation = (int) ($data['rotation'] ?? 0);
        $changed = $resource->crop() !== (null === $crop[0] ? null : ['x' => $crop[0], 'y' => $crop[1], 'width' => $crop[2], 'height' => $crop[3]]) || $resource->rotation() !== $rotation;
        $resource->changeCrop(...$crop);
        $resource->changeRotation($rotation);
        if ($changed) { $this->outbox->enqueue(new RegenerateMedia($resource->damAssetId())); }
        $this->changed($lieu);
    }

    public function replace(RessourceLieu $resource, Lieu $lieu, UploadedFile $file): void
    {
        $old = $resource->damAssetId();
        $asset = $this->uploader->upload($file, $lieu);
        try {
            $this->entityManager->persist($asset);
            $resource->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new MediaUploaded($asset->id(), $asset->originalStorageKey(), $asset->checksum(), ImageVariantRegistry::names()));
            $this->outbox->enqueue(new DeleteMedia($old));
            $this->changed($lieu);
        } catch (\Throwable $exception) { $this->cleanup([$asset]); throw $exception; }
    }

    public function retry(RessourceLieu $resource): void
    {
        $this->outbox->enqueue(new RegenerateMedia($resource->damAssetId()));
        $this->entityManager->flush();
    }

    public function delete(RessourceLieu $resource, Lieu $lieu): void
    {
        $id = $resource->damAssetId();
        $lieu->removeRessource($resource);
        $this->entityManager->remove($resource);
        $this->outbox->enqueue(new DeleteMedia($id));
        $this->changed($lieu);
    }

    private function changed(Lieu $lieu): void
    {
        $lieu->markChanged();
        $this->outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
        $this->entityManager->flush();
    }

    /** @param list<MediaAsset> $assets */
    private function cleanup(array $assets): void
    {
        foreach ($assets as $asset) { try { $this->uploader->delete($asset); } catch (\Throwable) {} }
    }
}
