<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Pim\Service\LieuMediaCsrfGuard;
use App\Pim\Service\LieuPhotoManager;
use App\Pim\Service\FicheSectionsCatalogue;
use App\Pim\Enum\TypeFiche;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/referentiel/lieux/fiche/{id}/photos', name: 'app_pim_lieu_photo_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class LieuPhotoController extends AbstractController
{
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, Lieu $lieu, LieuMediaCsrfGuard $csrf, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
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
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
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
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
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

    /**
     * Redirige vers une URL présignée fraîche de l'original : la modale de
     * recadrage charge l'image à l'ouverture, jamais au préchargement, pour
     * que le lien ne soit pas expiré quand l'utilisateur clique.
     */
    #[Route('/{resourceId}/original', name: 'original', methods: ['GET'], requirements: ['resourceId' => '[0-9A-HJKMNP-TV-Z]{26}'])]
    public function original(Lieu $lieu, string $resourceId, RessourceLieuRepository $resources, MediaAssetRepository $assets, PrivateObjectStorageInterface $storage): RedirectResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $lieu->fiche());
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        $asset = $assets->find($resource->damAssetId()) ?? throw $this->createNotFoundException('Fichier DAM introuvable.');

        return $this->redirect($storage->temporaryUrl($asset->originalStorageKey(), new \DateTimeImmutable('+10 minutes')));
    }

    #[Route('/{resourceId}/remplacer', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, Lieu $lieu, string $resourceId, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) { return $this->json(['error' => 'Sélectionnez une image.'], Response::HTTP_UNPROCESSABLE_ENTITY); }
        try {
            $mutationPolicy->execute($lieu->fiche(), static function () use ($manager, $resource, $lieu, $file): void {
                $manager->replace($resource, $lieu, $file);
            });

            return $this->json(['replaced' => true]);
        }
        catch (\DomainException $exception) { return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    #[Route('/{resourceId}/relancer', name: 'retry', methods: ['POST'])]
    public function retry(Request $request, Lieu $lieu, string $resourceId, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager): JsonResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        try {
            $manager->retry($resource);

            return $this->json(['queued' => true]);
        }
        catch (\DomainException $exception) { return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    #[Route('/{resourceId}', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request, Lieu $lieu, string $resourceId, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $csrf->assertValid($lieu, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($lieu->fiche(), $resourceId);
        if (null === $resource || $resource->lieu() !== $lieu) { throw $this->createNotFoundException('Photo introuvable pour ce lieu.'); }
        try {
            $mutationPolicy->execute($lieu->fiche(), static function () use ($manager, $resource, $lieu): void {
                $manager->delete($resource, $lieu);
            });

            return $this->json(null, Response::HTTP_NO_CONTENT);
        }
        catch (\DomainException $exception) { return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }
}
