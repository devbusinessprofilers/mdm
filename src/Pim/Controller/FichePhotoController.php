<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\FicheDetailResolver;
use App\Pim\Service\FicheMediaCsrfGuard;
use App\Pim\Service\FichePhotoManager;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Pim\Service\LieuAdminViewBuilder;
use App\Pim\Service\PhotosModalesVue;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Vision\Service\ImageRecognitionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Photos des fiches, toutes gammes : zone de dépôt, réordonnancement,
 * catégorie inline, paramètres en modale, remplacement, relance des rendus,
 * reconnaissance d'image et suppression — en JSON pour le contrôleur Stimulus
 * lieu-media, avec le jeton CSRF du bloc médias. Seules les photos de salle
 * (Lieu, Restaurant) se rattachent à une salle.
 */
#[Route('/referentiel/{gamme}/fiche/{id}', name: 'app_pim_fiche_photo_', requirements: ['gamme' => 'lieux|restaurants|activites|services', 'id' => '[0-9A-HJKMNP-TV-Z]{26}', 'resourceId' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class FichePhotoController extends AbstractController
{
    #[Route('/photos', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, string $gamme, string $id, FicheDetailResolver $resolver, FicheMediaCsrfGuard $csrf, FichePhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertRequest($request, $entite);
        $files = $request->files->all('photos');
        if ([] === $files) {
            $single = $request->files->get('photos');
            $files = $single instanceof UploadedFile ? [$single] : [];
        }
        $files = array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));
        try {
            $uploaded = $mutationPolicy->execute($entite->fiche(), static fn (): int => $manager->upload($entite, $files));

            return $this->json(['uploaded' => $uploaded], Response::HTTP_CREATED);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/ordre', name: 'order', methods: ['POST'])]
    public function order(Request $request, string $gamme, string $id, FicheDetailResolver $resolver, FicheMediaCsrfGuard $csrf, FichePhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertRequest($request, $entite);
        $ids = array_values(array_map('strval', $request->getPayload()->all('ids')));
        try {
            $updated = $mutationPolicy->execute($entite->fiche(), static fn (): int => $manager->reorder($entite, $ids));

            return $this->json(['updated' => $updated]);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/{resourceId}', name: 'update', methods: ['PATCH'])]
    public function update(Request $request, string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, FicheMediaCsrfGuard $csrf, RessourceLieuRepository $resources, FichePhotoManager $manager, CurrentActorProvider $actor, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertRequest($request, $entite);
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId) ?? throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        try {
            $mutationPolicy->execute($entite->fiche(), static function () use ($manager, $resource, $entite, $request, $actor): void {
                $manager->update($resource, $entite, $request->getPayload()->all(), $actor->id());
            });

            return $this->json(['updated' => true]);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Select de catégorie inline de la galerie : seule la catégorie change,
     * les autres métadonnées (légende, source, crop…) restent intactes. La
     * barre de salle envoie salle_id seul : l'usage courant est conservé.
     */
    #[Route('/photos/{resourceId}/categorie', name: 'categorie', methods: ['PATCH'])]
    public function categorie(Request $request, string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, FicheMediaCsrfGuard $csrf, RessourceLieuRepository $resources, FichePhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertRequest($request, $entite);
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId) ?? throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        try {
            $mutationPolicy->execute($entite->fiche(), static function () use ($manager, $resource, $entite, $request): void {
                $payload = $request->getPayload();
                $manager->changeCategorie(
                    $resource,
                    $entite,
                    (string) $payload->get('usage', $resource->usage()),
                    $payload->has('salle_id') ? (string) $payload->get('salle_id') : null,
                );
            });

            return $this->json(['updated' => true]);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/{resourceId}/remplacer', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, FicheMediaCsrfGuard $csrf, RessourceLieuRepository $resources, FichePhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertRequest($request, $entite);
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId) ?? throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
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
    public function retry(Request $request, string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, FicheMediaCsrfGuard $csrf, RessourceLieuRepository $resources, FichePhotoManager $manager): JsonResponse
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertRequest($request, $entite);
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId) ?? throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        try {
            $manager->retry($resource);

            return $this->json(['queued' => true]);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/{resourceId}/enrichir', name: 'enrich', methods: ['POST'])]
    public function enrich(Request $request, string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, FicheMediaCsrfGuard $csrf, RessourceLieuRepository $resources, ImageRecognitionManager $recognitions, CurrentActorProvider $actor): JsonResponse
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertRequest($request, $entite);
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId) ?? throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        try {
            return $this->json(['queued' => $recognitions->launchForResource($resource, $actor->id())]);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/photos/{resourceId}', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request, string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, FicheMediaCsrfGuard $csrf, RessourceLieuRepository $resources, FichePhotoManager $manager, InternalFicheMutationPolicy $mutationPolicy): JsonResponse
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $csrf->assertRequest($request, $entite);
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId) ?? throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
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
    #[Route('/photos/{resourceId}/original', name: 'original', methods: ['GET'])]
    public function original(string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, RessourceLieuRepository $resources, MediaAssetRepository $assets, PrivateObjectStorageInterface $storage): RedirectResponse
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $entite->fiche());
        $resource = $resources->findPhotoForFiche($entite->fiche(), $resourceId) ?? throw $this->createNotFoundException('Photo introuvable pour cette fiche.');
        $asset = $assets->find($resource->damAssetId()) ?? throw $this->createNotFoundException('Fichier DAM introuvable.');

        return $this->redirect($storage->temporaryUrl($asset->originalStorageKey(), new \DateTimeImmutable('+10 minutes')));
    }

    /**
     * Modales de paramètres des photos (et des documents pour le Lieu),
     * préchargées en arrière-plan par le contrôleur Stimulus lieu-media juste
     * après le chargement de la page : la page ne paie plus le rendu d'une
     * modale + ses formulaires par média, et le clic sur une vignette reste
     * instantané.
     */
    #[Route('/medias/modales', name: 'modales', methods: ['GET'])]
    public function modales(string $gamme, string $id, FicheDetailResolver $resolver, PhotosModalesVue $photos, LieuAdminViewBuilder $lieuVue): Response
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        // Même visibilité que la page fiche, où ces modales étaient rendues
        // d'office ; les mutations gardent EDIT + CSRF sur leurs propres routes.
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $entite->fiche());
        if ($entite instanceof Lieu) {
            return $this->render('pim/lieu/_medias_modales.html.twig', $lieuVue->modalesVars($entite));
        }

        return $this->render('pim/_medias_gamme_modales.html.twig', ['gamme' => $gamme, 'entite_id' => $id] + $photos->variables($entite));
    }
}
