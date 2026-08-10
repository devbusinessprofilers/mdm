<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Enum\DocumentAccess;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Form\LieuDocumentMetadataType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Form\LieuDocumentUploadType;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\LieuDocumentManager;
use App\Shared\Form\ActionType;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Pim\Service\FicheSectionsCatalogue;
use App\Pim\Enum\TypeFiche;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lieux/{id}/documents', name: 'app_pim_lieu_document_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class LieuDocumentController extends AbstractController
{
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, Lieu $lieu, FormFactoryInterface $forms, LieuDocumentManager $manager, CurrentActorProvider $actor): RedirectResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $form = $forms->createNamed('document_upload', LieuDocumentUploadType::class, null, ['salles' => $lieu->salles()->toArray()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire documentaire est invalide.');

            return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id(), 'section' => FicheSectionsCatalogue::indexBloc(TypeFiche::Lieu, 'medias')]);
        }
        $files = $form->get('documents')->getData();
        $files = is_array($files) ? array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile)) : [];
        if ([] === $files) {
            $this->addFlash('error', 'Sélectionnez au moins un document.');

            return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id(), 'section' => FicheSectionsCatalogue::indexBloc(TypeFiche::Lieu, 'medias')]);
        }
        try {
            /** @var array{usage: \App\Dam\Enum\DocumentUsage, salle: \App\Pim\Entity\Lieu\Salle|null, title: string|null, source: string|null} $data */
            $data = $form->getData();
            $count = $manager->upload($lieu, $files, $data, $actor->id());
            $this->addFlash('success', $count.' document(s) ajouté(s).');
        } catch (\DomainException|\App\Dam\Service\DocumentUploadException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id(), 'section' => FicheSectionsCatalogue::indexBloc(TypeFiche::Lieu, 'medias')]);
    }

    #[Route('/{resourceId}/modifier', name: 'update', methods: ['POST'])]
    public function update(Request $request, Lieu $lieu, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, LieuDocumentManager $manager, CurrentActorProvider $actor): RedirectResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $document = $resources->findDocumentForFiche($lieu->fiche(), $resourceId);
        if (null === $document || $document->lieu() !== $lieu) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('document_metadata_'.$document->id(), LieuDocumentMetadataType::class, [
            'usage' => $document->documentUsage(), 'salle' => $document->salle(), 'title' => $document->legende(),
            'source' => $document->source(), 'keywords' => $document->keywords(), 'rightsExpiresAt' => $document->rightsExpiresAt(),
        ], ['salles' => $lieu->salles()->toArray()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire documentaire est invalide.');
        } else {
            try {
                /** @var array{usage: \App\Dam\Enum\DocumentUsage, salle: \App\Pim\Entity\Lieu\Salle|null, title: string|null, source: string|null, keywords: string|null, rightsExpiresAt: \DateTimeImmutable|null} $data */
                $data = $form->getData();
                $manager->update($document, $lieu, $data, $actor->id());
                $this->addFlash('success', 'Document modifié.');
            } catch (\DomainException $exception) { $this->addFlash('error', $exception->getMessage()); }
        }

        return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id(), 'section' => FicheSectionsCatalogue::indexBloc(TypeFiche::Lieu, 'medias')]);
    }

    #[Route('/{resourceId}/fichier', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, Lieu $lieu, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, LieuDocumentManager $manager): RedirectResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $document = $resources->findDocumentForFiche($lieu->fiche(), $resourceId);
        if (null === $document || $document->lieu() !== $lieu) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('document_replace_'.$document->id(), LieuDocumentReplaceType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire de remplacement est invalide.');
        } else {
            $file = $form->get('document')->getData();
            if (!$file instanceof UploadedFile) {
                $this->addFlash('error', 'Sélectionnez un document.');

                return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id(), 'section' => FicheSectionsCatalogue::indexBloc(TypeFiche::Lieu, 'medias')]);
            }
            try { $manager->replace($document, $lieu, $file); $this->addFlash('success', 'Fichier remplacé.'); }
            catch (\DomainException $exception) { $this->addFlash('error', $exception->getMessage()); }
        }

        return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id(), 'section' => FicheSectionsCatalogue::indexBloc(TypeFiche::Lieu, 'medias')]);
    }

    #[Route('/{resourceId}/publication', name: 'publication', methods: ['POST'])]
    public function publication(Request $request, Lieu $lieu, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, LieuDocumentManager $manager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $document = $resources->findDocumentForFiche($lieu->fiche(), $resourceId);
        if (null === $document || $document->lieu() !== $lieu) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('document_publication_'.$document->id(), ActionType::class, null, ['button_label' => 'Action', 'csrf_token_id' => 'document-publication-'.$document->id()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) { $this->addFlash('error', 'Le formulaire de publication est invalide.'); }
        else {
            try { $manager->togglePublication($document, $lieu); $this->addFlash('success', 'Changement de publication mis en file.'); }
            catch (\DomainException $exception) { $this->addFlash('error', $exception->getMessage()); }
        }

        return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id(), 'section' => FicheSectionsCatalogue::indexBloc(TypeFiche::Lieu, 'medias')]);
    }

    #[Route('/{resourceId}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Lieu $lieu, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, LieuDocumentManager $manager): RedirectResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $document = $resources->findDocumentForFiche($lieu->fiche(), $resourceId);
        if (null === $document || $document->lieu() !== $lieu) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('document_delete_'.$document->id(), ActionType::class, null, ['button_label' => 'Supprimer', 'csrf_token_id' => 'document-delete-'.$document->id()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) { $this->addFlash('error', 'Le formulaire de suppression est invalide.'); }
        else { $manager->delete($document, $lieu); $this->addFlash('success', 'Document supprimé.'); }

        return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id(), 'section' => FicheSectionsCatalogue::indexBloc(TypeFiche::Lieu, 'medias')]);
    }

    #[Route('/{resourceId}/download', name: 'download', methods: ['GET'])]
    public function download(Lieu $lieu, string $resourceId, RessourceLieuRepository $resources, MediaAssetRepository $assets, PrivateObjectStorageInterface $storage): RedirectResponse
    {
        $document = $resources->findDocumentForFiche($lieu->fiche(), $resourceId);
        if (null === $document || $document->lieu() !== $lieu) { throw $this->createNotFoundException('Document introuvable.'); }
        if (DocumentAccess::Private === $document->documentAccess()) { $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR'); }
        else { $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche()); }
        $asset = $assets->find($document->damAssetId()) ?? throw $this->createNotFoundException('Fichier DAM introuvable.');

        return $this->redirect($storage->temporaryUrl($asset->originalStorageKey(), new \DateTimeImmutable('+10 minutes')));
    }
}
