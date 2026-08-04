<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\LieuMediaCsrfGuard;
use App\Pim\Service\LieuPhotoManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lieux/{id}/photos', name: 'app_pim_lieu_photo_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class LieuPhotoController extends AbstractController
{
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, Lieu $lieu, LieuMediaCsrfGuard $csrf, LieuPhotoManager $manager): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $files = $request->files->all('photos');
        if ([] === $files) { $single = $request->files->get('photos'); $files = $single instanceof UploadedFile ? [$single] : []; }
        $files = array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));
        try { return $this->json(['uploaded' => $manager->upload($lieu, $files)], Response::HTTP_CREATED); }
        catch (\DomainException $exception) { return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    #[Route('/ordre', name: 'order', methods: ['POST'])]
    public function order(Request $request, Lieu $lieu, LieuMediaCsrfGuard $csrf, LieuPhotoManager $manager): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $ids = array_values(array_map('strval', $request->getPayload()->all('ids')));
        try { return $this->json(['updated' => $manager->reorder($lieu, $ids)]); }
        catch (\DomainException $exception) { return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    #[Route('/{resourceId}', name: 'update', methods: ['PATCH'])]
    public function update(Request $request, Lieu $lieu, string $resourceId, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager, CurrentActorProvider $actor): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        try { $manager->update($resource, $lieu, $request->getPayload()->all(), $actor->id()); return $this->json(['updated' => true]); }
        catch (\DomainException $exception) { return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    #[Route('/{resourceId}/remplacer', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, Lieu $lieu, string $resourceId, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) { return $this->json(['error' => 'Sélectionnez une image.'], Response::HTTP_UNPROCESSABLE_ENTITY); }
        $manager->replace($resource, $lieu, $file);

        return $this->json(['replaced' => true]);
    }

    #[Route('/{resourceId}/relancer', name: 'retry', methods: ['POST'])]
    public function retry(Request $request, Lieu $lieu, string $resourceId, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        $manager->retry($resource);

        return $this->json(['queued' => true]);
    }

    #[Route('/{resourceId}', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request, Lieu $lieu, string $resourceId, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        $manager->delete($resource, $lieu);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
