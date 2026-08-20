<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Enum\DocumentAccess;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Form\RestaurantDocumentMetadataType;
use App\Pim\Form\RestaurantDocumentUploadType;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\FicheDocumentManager;
use App\Pim\Service\MediasBlocReponse;
use App\Shared\Form\ActionType;
use App\Shared\Service\PrivateObjectStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/referentiel/restaurants/fiche/{id}/documents', name: 'app_pim_restaurant_document_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class RestaurantDocumentController extends AbstractController
{
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, Restaurant $restaurant, FormFactoryInterface $forms, FicheDocumentManager $manager, CurrentActorProvider $actor, MediasBlocReponse $reponse): Response
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $restaurant->fiche());
        $form = $forms->createNamed('restaurant_document_upload', RestaurantDocumentUploadType::class, null, ['salles' => $restaurant->salles()->toArray()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $reponse->repondre($request, $restaurant->fiche(), 'Le formulaire documentaire est invalide.', '');
        }
        $files = $form->get('documents')->getData();
        $files = is_array($files) ? array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile)) : [];
        if ([] === $files) {
            return $reponse->repondre($request, $restaurant->fiche(), 'Sélectionnez au moins un document.', '');
        }
        $erreur = null;
        $succes = '';
        try {
            /** @var array{usage: \App\Dam\Enum\DocumentUsage, salle: \App\Pim\Entity\Restaurant\RestaurantSalle|null, title: string|null, source: string|null} $data */
            $data = $form->getData();
            $count = $manager->upload($restaurant, $files, $data, $actor->id());
            $succes = $count.' document(s) ajouté(s).';
        } catch (\DomainException|\App\Dam\Service\DocumentUploadException $exception) {
            $erreur = $exception->getMessage();
        }

        return $reponse->repondre($request, $restaurant->fiche(), $erreur, $succes);
    }

    #[Route('/{resourceId}/modifier', name: 'update', methods: ['POST'])]
    public function update(
        Request $request,
        Restaurant $restaurant,
        string $resourceId,
        RessourceLieuRepository $resources,
        FormFactoryInterface $forms,
        FicheDocumentManager $manager,
        CurrentActorProvider $actor,
        MediasBlocReponse $reponse,
    ): Response {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $restaurant->fiche());
        $document = $resources->findDocumentForFiche($restaurant->fiche(), $resourceId);
        if (null === $document) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('restaurant_document_metadata_'.$document->id(), RestaurantDocumentMetadataType::class, [
            'title' => $document->legende(), 'source' => $document->source(), 'keywords' => $document->keywords(),
            'rightsExpiresAt' => $document->rightsExpiresAt(), 'salle' => $document->restaurantSalle(),
        ], ['salles' => $restaurant->salles()->toArray()]);
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) {
            $erreur = 'Le formulaire documentaire est invalide.';
        } else {
            try {
                /** @var array<string, mixed> $data */
                $data = $form->getData();
                $manager->updateMetadata($document, $restaurant->fiche(), $data, $actor->id());
            } catch (\DomainException $exception) { $erreur = $exception->getMessage(); }
        }

        return $reponse->repondre($request, $restaurant->fiche(), $erreur, 'Document modifié.');
    }

    #[Route('/{resourceId}/fichier', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, Restaurant $restaurant, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, FicheDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $restaurant->fiche());
        $document = $resources->findDocumentForFiche($restaurant->fiche(), $resourceId);
        if (null === $document) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('restaurant_document_replace_'.$document->id(), LieuDocumentReplaceType::class);
        $form->handleRequest($request);
        $file = $form->isSubmitted() && $form->isValid() ? $form->get('document')->getData() : null;
        $erreur = null;
        if (!$file instanceof UploadedFile) {
            $erreur = 'Sélectionnez un document valide.';
        } else {
            $usage = $document->documentUsage() ?? throw $this->createNotFoundException('Usage documentaire invalide.');
            $manager->replace($document, $restaurant->fiche(), $file, $usage);
        }

        return $reponse->repondre($request, $restaurant->fiche(), $erreur, 'Fichier remplacé.');
    }

    #[Route('/{resourceId}/publication', name: 'publication', methods: ['POST'])]
    public function publication(Request $request, Restaurant $restaurant, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, FicheDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $document = $resources->findDocumentForFiche($restaurant->fiche(), $resourceId);
        if (null === $document) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('restaurant_document_publication_'.$document->id(), ActionType::class, null, ['button_label' => 'Action', 'csrf_token_id' => 'restaurant-document-publication-'.$document->id()]);
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) {
            $erreur = 'Action de publication invalide.';
        } else {
            try { $manager->togglePublication($document, $restaurant->fiche()); }
            catch (\DomainException $exception) { $erreur = $exception->getMessage(); }
        }

        return $reponse->repondre($request, $restaurant->fiche(), $erreur, 'Changement de publication mis en file.');
    }

    #[Route('/{resourceId}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Restaurant $restaurant, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, FicheDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $restaurant->fiche());
        $document = $resources->findDocumentForFiche($restaurant->fiche(), $resourceId);
        if (null === $document) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('restaurant_document_delete_'.$document->id(), ActionType::class, null, ['button_label' => 'Supprimer', 'csrf_token_id' => 'restaurant-document-delete-'.$document->id()]);
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) {
            $erreur = 'Action de suppression invalide.';
        } else {
            $manager->delete($document, $restaurant->fiche());
        }

        return $reponse->repondre($request, $restaurant->fiche(), $erreur, 'Document supprimé.');
    }

    #[Route('/{resourceId}/download', name: 'download', methods: ['GET'])]
    public function download(Restaurant $restaurant, string $resourceId, RessourceLieuRepository $resources, MediaAssetRepository $assets, PrivateObjectStorageInterface $storage): RedirectResponse
    {
        $document = $resources->findDocumentForFiche($restaurant->fiche(), $resourceId);
        if (null === $document) { throw $this->createNotFoundException('Document introuvable.'); }
        if (DocumentAccess::Private === $document->documentAccess()) { $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR'); }
        else { $this->denyAccessUnlessGranted(FicheVoter::EDIT, $restaurant->fiche()); }
        $asset = $assets->find($document->damAssetId()) ?? throw $this->createNotFoundException('Fichier DAM introuvable.');

        return $this->redirect($storage->temporaryUrl($asset->originalStorageKey(), new \DateTimeImmutable('+10 minutes')));
    }

}
