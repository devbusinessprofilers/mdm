<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Entity\User;
use App\Dam\Entity\MediaAsset;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Service\ImageVariantRegistry;
use App\Dam\Service\LieuImageUploader;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lieux/{id}/photos', name: 'app_pim_lieu_photo_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class LieuPhotoController extends AbstractController
{
    private const MAX_PHOTOS = 25;
    private const USAGES = [
        'PHOTO_PRINCIPALE', 'PHOTO_FACADE', 'PHOTO_CHAMBRE', 'PHOTO_RESTAURATION',
        'CONFIG_PHOTO_SALLE', 'PHOTO_DIVERSE', 'CONFIG_PLAN_SALLE',
        'LOISIR_EXTERNE_PHOTO', 'PHOTO',
    ];

    public function __construct(
        private readonly LieuImageUploader $uploader,
        private readonly RessourceLieuRepository $resources,
        private readonly EntityManagerInterface $entityManager,
        private readonly OutboxPublisherInterface $outbox,
    ) {
    }

    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, Lieu $lieu): JsonResponse
    {
        $this->assertCsrf($request, $lieu);
        $files = $request->files->all('photos');
        if ([] === $files) {
            $single = $request->files->get('photos');
            $files = $single instanceof UploadedFile ? [$single] : [];
        }
        $files = array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));
        $photoCount = count(array_filter($lieu->ressources()->toArray(), static fn (RessourceLieu $resource): bool => NatureRessource::Photo === $resource->nature()));
        if ([] === $files || $photoCount + count($files) > self::MAX_PHOTOS) {
            return $this->json(['error' => 'Sélectionnez entre 1 et '.(self::MAX_PHOTOS - $photoCount).' image(s).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $uploaded = [];
        try {
            foreach ($files as $offset => $file) {
                $asset = $this->uploader->upload($file, $lieu);
                $uploaded[] = $asset;
                $resource = new RessourceLieu();
                $resource->changeDamAssetId($asset->id());
                $resource->changeNature(NatureRessource::Photo);
                $resource->changeUsage('PHOTO_DIVERSE');
                $resource->changePosition($photoCount + $offset);
                $lieu->addRessource($resource);
                $this->entityManager->persist($asset);
                $this->outbox->enqueue(new MediaUploaded($asset->id(), $asset->originalStorageKey(), $asset->checksum(), ImageVariantRegistry::names()));
            }
            $this->outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
            $this->entityManager->flush();
        } catch (\DomainException $exception) {
            $this->cleanup($uploaded);

            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            $this->cleanup($uploaded);
            throw $exception;
        }

        return $this->json(['uploaded' => count($uploaded)], Response::HTTP_CREATED);
    }

    #[Route('/ordre', name: 'order', methods: ['POST'])]
    public function order(Request $request, Lieu $lieu): JsonResponse
    {
        $this->assertCsrf($request, $lieu);
        $ids = $request->getPayload()->all('ids');
        $photos = $this->photos($lieu);
        $known = array_column(array_map(static fn (RessourceLieu $resource): array => ['id' => $resource->id()], $photos), 'id');
        if (count($ids) !== count($known) || [] !== array_diff($ids, $known) || [] !== array_diff($known, $ids)) {
            return $this->json(['error' => "L'ordre transmis ne correspond pas aux photos du lieu."], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $byId = [];
        foreach ($photos as $photo) {
            $byId[$photo->id()] = $photo;
        }
        foreach ($ids as $position => $id) {
            $byId[(string) $id]->changePosition($position);
        }
        $this->changed($lieu);

        return $this->json(['updated' => count($ids)]);
    }

    #[Route('/{resourceId}', name: 'update', methods: ['PATCH'])]
    public function update(Request $request, Lieu $lieu, string $resourceId): JsonResponse
    {
        $this->assertCsrf($request, $lieu);
        $resource = $this->resource($lieu, $resourceId);
        $data = $request->getPayload();
        $usage = $data->getString('usage');
        if (!in_array($usage, self::USAGES, true)) {
            return $this->json(['error' => 'Catégorie de photo invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ('PHOTO_PRINCIPALE' === $usage) {
            foreach ($this->photos($lieu) as $other) {
                if ($other !== $resource && 'PHOTO_PRINCIPALE' === $other->usage()) {
                    return $this->json(['error' => 'Une seule photo principale est autorisée par lieu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
        }
        $resource->changeUsage($usage);
        $resource->changeLegende($data->getString('legende'));
        $resource->changeSource($data->getString('source'));
        $salleId = $data->getString('salle_id');
        $salle = null;
        if ('' !== $salleId) {
            foreach ($lieu->salles() as $candidate) {
                if ($candidate->id() === $salleId) {
                    $salle = $candidate;
                    break;
                }
            }
            if (null === $salle) {
                return $this->json(['error' => "La salle n'appartient pas à ce lieu."], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        if ('CONFIG_PHOTO_SALLE' === $usage && null === $salle) {
            return $this->json(['error' => 'Une photo de salle doit être associée à une salle.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ('CONFIG_PHOTO_SALLE' !== $usage) {
            $salle = null;
        }
        $resource->changeSalle($salle);
        if ($data->getBoolean('rights_granted')) {
            $user = $this->getUser();
            $resource->grantRights($user instanceof User ? $user->id() : 'system');
        } else {
            $resource->revokeRights();
        }

        try {
            $cropValues = array_map(static fn (string $key): ?int => '' === (string) $data->get($key, '') ? null : $data->getInt($key), ['crop_x', 'crop_y', 'crop_width', 'crop_height']);
            $rotation = $data->getInt('rotation');
            $transformationChanged = $resource->crop() !== (null === $cropValues[0] ? null : ['x' => $cropValues[0], 'y' => $cropValues[1], 'width' => $cropValues[2], 'height' => $cropValues[3]]) || $resource->rotation() !== $rotation;
            $resource->changeCrop(...$cropValues);
            $resource->changeRotation($rotation);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($transformationChanged) {
            $this->outbox->enqueue(new RegenerateMedia($resource->damAssetId()));
        }
        $this->changed($lieu);

        return $this->json(['updated' => true]);
    }

    #[Route('/{resourceId}/remplacer', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, Lieu $lieu, string $resourceId): JsonResponse
    {
        $this->assertCsrf($request, $lieu);
        $resource = $this->resource($lieu, $resourceId);
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Sélectionnez une image.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $oldId = $resource->damAssetId();
        $asset = $this->uploader->upload($file, $lieu);
        try {
            $this->entityManager->persist($asset);
            $resource->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new MediaUploaded($asset->id(), $asset->originalStorageKey(), $asset->checksum(), ImageVariantRegistry::names()));
            $this->outbox->enqueue(new DeleteMedia($oldId));
            $this->changed($lieu);
        } catch (\Throwable $exception) {
            $this->cleanup([$asset]);
            throw $exception;
        }

        return $this->json(['replaced' => true]);
    }

    #[Route('/{resourceId}/relancer', name: 'retry', methods: ['POST'])]
    public function retry(Request $request, Lieu $lieu, string $resourceId): JsonResponse
    {
        $this->assertCsrf($request, $lieu);
        $resource = $this->resource($lieu, $resourceId);
        $this->outbox->enqueue(new RegenerateMedia($resource->damAssetId()));
        $this->entityManager->flush();

        return $this->json(['queued' => true]);
    }

    #[Route('/{resourceId}', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request, Lieu $lieu, string $resourceId): JsonResponse
    {
        $this->assertCsrf($request, $lieu);
        $resource = $this->resource($lieu, $resourceId);
        $mediaId = $resource->damAssetId();
        $lieu->removeRessource($resource);
        $this->entityManager->remove($resource);
        $this->outbox->enqueue(new DeleteMedia($mediaId));
        $this->changed($lieu);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function assertCsrf(Request $request, Lieu $lieu): void
    {
        $token = $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token'));
        if (!$this->isCsrfTokenValid('lieu-media-'.$lieu->id(), $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    private function resource(Lieu $lieu, string $id): RessourceLieu
    {
        $resource = $this->resources->find($id);
        if (!$resource instanceof RessourceLieu || $resource->lieu() !== $lieu || NatureRessource::Photo !== $resource->nature()) {
            throw $this->createNotFoundException('Photo introuvable pour ce lieu.');
        }

        return $resource;
    }

    /** @return list<RessourceLieu> */
    private function photos(Lieu $lieu): array
    {
        return array_values(array_filter($lieu->ressources()->toArray(), static fn (RessourceLieu $resource): bool => NatureRessource::Photo === $resource->nature()));
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
        foreach ($assets as $asset) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
        }
    }
}
