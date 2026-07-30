<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Service\ImageVariantRegistry;
use App\Dam\Service\LieuImageUploader;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\LieuRepository;
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

#[Route('/api/lieux/{id}/medias', name: 'api_external_lieu_media_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class ExternalLieuMediaController extends AbstractController
{
    public function __construct(
        private readonly LieuImageUploader $uploader,
        private readonly LieuRepository $lieux,
        private readonly RessourceLieuRepository $resources,
        private readonly EntityManagerInterface $entityManager,
        private readonly OutboxPublisherInterface $outbox,
    ) {}

    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(string $id, Request $request): JsonResponse
    {
        $lieu = $this->lieu($id);
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) { return $this->json(['error' => 'photo_required'], 422); }
        $asset = $this->uploader->upload($file, $lieu);
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($asset->id());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage($request->request->getString('usage', 'PHOTO_DIVERSE'));
        $legende = $request->request->get('legende');
        $resource->changeLegende(is_string($legende) ? $legende : null);
        $resource->changePosition(count($lieu->ressources()));
        $lieu->addRessource($resource);
        $this->entityManager->persist($asset);
        $this->outbox->enqueue(new MediaUploaded($asset->id(), $asset->originalStorageKey(), $asset->checksum(), ImageVariantRegistry::names()));
        $this->publish($lieu);
        return $this->json(['id' => $resource->id(), 'damAssetId' => $asset->id()], Response::HTTP_CREATED);
    }

    #[Route('/ordre', name: 'order', methods: ['PUT'])]
    public function order(string $id, Request $request): JsonResponse
    {
        $lieu = $this->lieu($id);
        $ids = $request->toArray()['ids'] ?? null;
        if (!is_array($ids)) { return $this->json(['error' => 'ids_required'], 422); }
        foreach (array_values($ids) as $position => $resourceId) {
            $this->resource($lieu, (string) $resourceId)->changePosition($position);
        }
        $this->publish($lieu);
        return $this->json(['ordered' => true]);
    }

    #[Route('/{resourceId}', name: 'metadata', methods: ['PATCH'])]
    public function metadata(string $id, string $resourceId, Request $request): JsonResponse
    {
        $lieu = $this->lieu($id);
        $resource = $this->resource($lieu, $resourceId);
        $data = $request->toArray();
        $allowed = ['usage', 'legende', 'source', 'rightsGranted', 'crop', 'rotation'];
        if ([] !== array_diff(array_keys($data), $allowed)) { return $this->json(['error' => 'unknown_fields'], 422); }
        if (array_key_exists('usage', $data) && is_string($data['usage'])) { $resource->changeUsage($data['usage']); }
        if (array_key_exists('legende', $data) && (is_string($data['legende']) || null === $data['legende'])) { $resource->changeLegende($data['legende']); }
        if (array_key_exists('source', $data) && (is_string($data['source']) || null === $data['source'])) { $resource->changeSource($data['source']); }
        if (array_key_exists('rightsGranted', $data)) { true === $data['rightsGranted'] ? $resource->grantRights('external-site') : $resource->revokeRights(); }
        if (isset($data['crop']) && is_array($data['crop'])) { $resource->changeCrop($data['crop']['x'] ?? null, $data['crop']['y'] ?? null, $data['crop']['width'] ?? null, $data['crop']['height'] ?? null); $this->outbox->enqueue(new RegenerateMedia($resource->damAssetId())); }
        if (isset($data['rotation']) && is_int($data['rotation'])) { $resource->changeRotation($data['rotation']); $this->outbox->enqueue(new RegenerateMedia($resource->damAssetId())); }
        $this->publish($lieu);
        return $this->json(['updated' => true]);
    }

    #[Route('/{resourceId}/fichier', name: 'replace', methods: ['POST'])]
    public function replace(string $id, string $resourceId, Request $request): JsonResponse
    {
        $lieu = $this->lieu($id);
        $resource = $this->resource($lieu, $resourceId);
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) { return $this->json(['error' => 'photo_required'], 422); }
        $oldId = $resource->damAssetId();
        $asset = $this->uploader->upload($file, $lieu);
        $this->entityManager->persist($asset);
        $resource->changeDamAssetId($asset->id());
        $this->outbox->enqueue(new MediaUploaded($asset->id(), $asset->originalStorageKey(), $asset->checksum(), ImageVariantRegistry::names()));
        $this->outbox->enqueue(new DeleteMedia($oldId));
        $this->publish($lieu);
        return $this->json(['damAssetId' => $asset->id()]);
    }

    #[Route('/{resourceId}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, string $resourceId): JsonResponse
    {
        $lieu = $this->lieu($id);
        $resource = $this->resource($lieu, $resourceId);
        $this->outbox->enqueue(new DeleteMedia($resource->damAssetId()));
        $lieu->removeRessource($resource);
        $this->entityManager->remove($resource);
        $this->publish($lieu);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function lieu(string $id): Lieu
    {
        $lieu = $this->lieux->find($id);
        if (!$lieu instanceof Lieu) { throw $this->createNotFoundException('Lieu introuvable.'); }
        return $lieu;
    }

    private function resource(Lieu $lieu, string $id): RessourceLieu
    {
        $resource = $this->resources->find($id);
        if (!$resource instanceof RessourceLieu || $resource->lieu() !== $lieu || NatureRessource::Photo !== $resource->nature()) { throw $this->createNotFoundException('Média introuvable.'); }
        return $resource;
    }

    private function publish(Lieu $lieu): void
    {
        $lieu->fiche()->publishFromExternal();
        $this->outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
        $this->entityManager->flush();
    }
}
