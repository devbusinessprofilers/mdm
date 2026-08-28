<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\FichePhotoPresenter;
use App\Pim\Form\LieuPhotoMetadataType;
use App\Pim\Form\LieuPhotoReplaceType;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\GammeEntiteResolver;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Pim\Service\LieuMediaCsrfGuard;
use App\Pim\Service\LieuPhotoManager;
use App\Shared\Service\ParametreProviderInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Vision\Service\ImageRecognitionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Photos des fiches de gamme (Restaurant, Activité, Service) : mêmes gestes
 * que la fiche Lieu — zone de dépôt, réordonnancement, paramètres en modale —
 * portés par le même gestionnaire (les ressources sont partagées). Seule
 * l'association à une salle reste propre au Lieu.
 */
#[Route('/referentiel/{gamme}/fiche/{id}', name: 'app_pim_gamme_photo_', requirements: ['gamme' => 'restaurants|activites|services', 'id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class GammePhotoController extends AbstractController
{
    #[Route('/photos', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, string $gamme, string $id, GammeEntiteResolver $resolver, LieuMediaCsrfGuard $csrf, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertValid($entite, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $files = $request->files->all('photos');
        if ([] === $files) {
            $single = $request->files->get('photos');
            $files = $single instanceof UploadedFile ? [$single] : [];
        }
        $files = array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));
        try {
            $uploaded = $mutationPolicy->execute(
                $entite->fiche(),
                static fn (): int => $manager->upload($entite, $files),
            );

            return $this->json(['uploaded' => $uploaded], Response::HTTP_CREATED);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/ordre', name: 'order', methods: ['POST'])]
    public function order(Request $request, string $gamme, string $id, GammeEntiteResolver $resolver, LieuMediaCsrfGuard $csrf, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertValid($entite, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $ids = array_values(array_map('strval', $request->getPayload()->all('ids')));
        try {
            $updated = $mutationPolicy->execute(
                $entite->fiche(),
                static fn (): int => $manager->reorder($entite, $ids),
            );

            return $this->json(['updated' => $updated]);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/{resourceId}', name: 'update', methods: ['PATCH'])]
    public function update(Request $request, string $gamme, string $id, string $resourceId, GammeEntiteResolver $resolver, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager, CurrentActorProvider $actor, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertValid($entite, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId);
        if (null === $resource) {
            throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        }
        try {
            $mutationPolicy->execute($entite->fiche(), static function () use ($manager, $resource, $entite, $request, $actor): void {
                $manager->update($resource, $entite, $request->getPayload()->all(), $actor->id());
            });

            return $this->json(['updated' => true]);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/{resourceId}/remplacer', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, string $gamme, string $id, string $resourceId, GammeEntiteResolver $resolver, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertValid($entite, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId);
        if (null === $resource) {
            throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        }
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Sélectionnez une image.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $mutationPolicy->execute($entite->fiche(), static function () use ($manager, $resource, $entite, $file): void {
                $manager->replace($resource, $entite, $file);
            });

            return $this->json(['replaced' => true]);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/{resourceId}/relancer', name: 'retry', methods: ['POST'])]
    public function retry(Request $request, string $gamme, string $id, string $resourceId, GammeEntiteResolver $resolver, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager): JsonResponse
    {
        $entite = $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertValid($entite, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId);
        if (null === $resource) {
            throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        }
        try {
            $manager->retry($resource);

            return $this->json(['queued' => true]);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/{resourceId}/enrichir', name: 'enrich', methods: ['POST'])]
    public function enrich(Request $request, string $gamme, string $id, string $resourceId, GammeEntiteResolver $resolver, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, ImageRecognitionManager $recognitions, CurrentActorProvider $actor): JsonResponse
    {
        $entite = $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertValid($entite, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId);
        if (null === $resource) {
            throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        }
        try {
            return $this->json(['queued' => $recognitions->launchForResource($resource, $actor->id())]);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/{resourceId}', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request, string $gamme, string $id, string $resourceId, GammeEntiteResolver $resolver, LieuMediaCsrfGuard $csrf, RessourceLieuRepository $resources, LieuPhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertValid($entite, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId);
        if (null === $resource) {
            throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        }
        try {
            $mutationPolicy->execute($entite->fiche(), static function () use ($manager, $resource, $entite): void {
                $manager->delete($resource, $entite);
            });

            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Redirige vers une URL présignée fraîche de l'original : la modale de
     * recadrage charge l'image à l'ouverture, jamais au préchargement, pour
     * que le lien ne soit pas expiré quand l'utilisateur clique.
     */
    #[Route('/photos/{resourceId}/original', name: 'original', methods: ['GET'], requirements: ['resourceId' => '[0-9A-HJKMNP-TV-Z]{26}'])]
    public function original(string $gamme, string $id, string $resourceId, GammeEntiteResolver $resolver, RessourceLieuRepository $resources, MediaAssetRepository $assets, PrivateObjectStorageInterface $storage): RedirectResponse
    {
        $entite = $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $entite->fiche());
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId);
        if (null === $resource) {
            throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        }
        $asset = $assets->find($resource->damAssetId()) ?? throw $this->createNotFoundException('Fichier DAM introuvable.');

        return $this->redirect($storage->temporaryUrl($asset->originalStorageKey(), new \DateTimeImmutable('+10 minutes')));
    }

    /**
     * Modales de paramètres des photos, préchargées en arrière-plan par le
     * contrôleur Stimulus lieu-media — même mécanique que la fiche Lieu.
     */
    #[Route('/medias/modales', name: 'modales', methods: ['GET'])]
    public function modales(string $gamme, string $id, GammeEntiteResolver $resolver, FichePhotoPresenter $presenter, FormFactoryInterface $forms, UrlGeneratorInterface $urls, ParametreProviderInterface $parametres): Response
    {
        $entite = $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $entite->fiche());
        $photos = $presenter->photos($entite->fiche());
        foreach ($photos as &$photo) {
            $resource = $photo['resource'];
            $crop = $resource->crop();
            $params = ['gamme' => $gamme, 'id' => $id, 'resourceId' => $resource->id()];
            $photo['metadata_form'] = $forms->createNamed('photo_metadata_'.$resource->id(), LieuPhotoMetadataType::class, [
                'usage' => $resource->usage(), 'legende' => $resource->legende(), 'source' => $resource->source(),
                'keywords' => $resource->keywords(), 'rights_expires_at' => $resource->rightsExpiresAt(),
                'crop_x' => $crop['x'] ?? null, 'crop_y' => $crop['y'] ?? null, 'crop_width' => $crop['width'] ?? null,
                'crop_height' => $crop['height'] ?? null, 'rotation' => $resource->rotation(),
            ], [
                'action' => $urls->generate('app_pim_gamme_photo_update', $params), 'method' => 'PATCH', 'avec_salles' => false,
            ])->createView();
            $photo['replace_form'] = $forms->createNamed('photo_replace_'.$resource->id(), LieuPhotoReplaceType::class, null, [
                'action' => $urls->generate('app_pim_gamme_photo_replace', $params), 'method' => 'POST',
            ])->createView();
            $photo['original_url'] = $urls->generate('app_pim_gamme_photo_original', $params);
        }
        unset($photo);

        return $this->render('pim/_medias_gamme_modales.html.twig', [
            'gamme' => $gamme,
            'entite_id' => $id,
            'photos' => $photos,
            'image_min_width' => $parametres->int('dam.image_largeur_min'),
            'image_min_height' => $parametres->int('dam.image_hauteur_min'),
        ]);
    }
}
