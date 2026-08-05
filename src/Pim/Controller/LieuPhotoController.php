<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\InternalFicheMutationPolicy;
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
    public function upload(Request $request, Lieu $lieu, LieuMediaCsrfGuard $csrf, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $files = $request->files->all('photos');
        if ([] === $files) { $single = $request->files->get('photos'); $files = $single instanceof UploadedFile ? [$single] : []; }
        $files = array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));
        try {
            $uploaded = $mutationPolicy->execute(
                $lieu->fiche(),
                static fn (): int => $manager->upload($lieu, $files),
            );

            return $this->json(['uploaded' => $uploaded], Response::HTTP_CREATED);
        }
        catch (\DomainException $exception) { return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    #[Route('/ordre', name: 'order', methods: ['POST'])]
    public function order(Request $request, Lieu $lieu, LieuMediaCsrfGuard $csrf, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $ids = array_values(array_map('strval', $request->getPayload()->all('ids')));
        try {
            $updated = $mutationPolicy->execute(
                $lieu->fiche(),
                static fn (): int => $manager->reorder($lieu, $ids),
            );

            return $this->json(['updated' => $updated]);
        }
        catch (\DomainException $exception) { return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    #[Route('/{resourceId}', name: 'update', methods: ['PATCH'])]
    public function update(Request $request, Lieu $lieu, string $resourceId, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager, CurrentActorProvider $actor, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        try {
            $mutationPolicy->execute($lieu->fiche(), function () use ($manager, $resource, $lieu, $request, $actor): void {
                $manager->update($resource, $lieu, $request->getPayload()->all(), $actor->id());
            });

            return $this->json(['updated' => true]);
        }
        catch (\DomainException $exception) { return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    #[Route('/{resourceId}/remplacer', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, Lieu $lieu, string $resourceId, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) { return $this->json(['error' => 'Sélectionnez une image.'], Response::HTTP_UNPROCESSABLE_ENTITY); }
        $mutationPolicy->execute($lieu->fiche(), static function () use ($manager, $resource, $lieu, $file): void {
            $manager->replace($resource, $lieu, $file);
        });

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
    public function delete(Request $request, Lieu $lieu, string $resourceId, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        $mutationPolicy->execute($lieu->fiche(), static function () use ($manager, $resource, $lieu): void {
            $manager->delete($resource, $lieu);
        });

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
