<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Enum\DocumentAccess;
use App\Dam\Enum\DocumentUsage;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Form\ActiviteDocumentMetadataType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\FicheDocumentManager;
use App\Shared\Form\ActionType;
use App\Shared\Service\PrivateObjectStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/activites/{id}/documents', name: 'app_pim_activite_document_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class ActiviteDocumentController extends AbstractController
{
    #[Route('/{resourceId}/modifier', name: 'update', methods: ['POST'])]
    public function update(
        Request $request,
        Activite $activite,
        string $resourceId,
        RessourceLieuRepository $resources,
        FormFactoryInterface $forms,
        FicheDocumentManager $manager,
        CurrentActorProvider $actor,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $activite->fiche());
        $document = $resources->findDocumentForFiche($activite->fiche(), $resourceId, DocumentUsage::CommercialSupport);
        if (null === $document) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('activite_document_metadata_'.$document->id(), ActiviteDocumentMetadataType::class, [
            'title' => $document->legende(), 'source' => $document->source(), 'rightsGranted' => $document->rightsGranted(),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire documentaire est invalide.');
        } else {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $manager->updateMetadata($document, $activite->fiche(), $data, $actor->id());
            $this->addFlash('success', 'Document modifié.');
        }

        return $this->redirectToRoute('app_pim_activite_edit', ['id' => $activite->id()]);
    }

    #[Route('/{resourceId}/fichier', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, Activite $activite, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, FicheDocumentManager $manager): RedirectResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $activite->fiche());
        $document = $resources->findDocumentForFiche($activite->fiche(), $resourceId, DocumentUsage::CommercialSupport);
        if (null === $document) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('activite_document_replace_'.$document->id(), LieuDocumentReplaceType::class);
        $form->handleRequest($request);
        $file = $form->isSubmitted() && $form->isValid() ? $form->get('document')->getData() : null;
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Sélectionnez un document valide.');
        } else {
            $manager->replace($document, $activite->fiche(), $file, DocumentUsage::CommercialSupport);
            $this->addFlash('success', 'Fichier remplacé.');
        }

        return $this->redirectToRoute('app_pim_activite_edit', ['id' => $activite->id()]);
    }

    #[Route('/{resourceId}/publication', name: 'publication', methods: ['POST'])]
    public function publication(Request $request, Activite $activite, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, FicheDocumentManager $manager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $document = $resources->findDocumentForFiche($activite->fiche(), $resourceId, DocumentUsage::CommercialSupport);
        if (null === $document) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('activite_document_publication_'.$document->id(), ActionType::class, null, ['csrf_token_id' => 'activite-document-publication-'.$document->id()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Action de publication invalide.');
        } else {
            try {
                $manager->togglePublication($document, $activite->fiche());
                $this->addFlash('success', 'Changement de publication mis en file.');
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->redirectToRoute('app_pim_activite_edit', ['id' => $activite->id()]);
    }

    #[Route('/{resourceId}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Activite $activite, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, FicheDocumentManager $manager): RedirectResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $activite->fiche());
        $document = $resources->findDocumentForFiche($activite->fiche(), $resourceId, DocumentUsage::CommercialSupport);
        if (null === $document) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('activite_document_delete_'.$document->id(), ActionType::class, null, ['csrf_token_id' => 'activite-document-delete-'.$document->id()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Action de suppression invalide.');
        } else {
            $manager->delete($document, $activite->fiche());
            $this->addFlash('success', 'Document supprimé.');
        }

        return $this->redirectToRoute('app_pim_activite_edit', ['id' => $activite->id()]);
    }

    #[Route('/{resourceId}/download', name: 'download', methods: ['GET'])]
    public function download(Activite $activite, string $resourceId, RessourceLieuRepository $resources, MediaAssetRepository $assets, PrivateObjectStorageInterface $storage): RedirectResponse
    {
        $document = $resources->findDocumentForFiche($activite->fiche(), $resourceId, DocumentUsage::CommercialSupport);
        if (null === $document) { throw $this->createNotFoundException('Document introuvable.'); }
        if (DocumentAccess::Private === $document->documentAccess()) { $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR'); }
        else { $this->denyAccessUnlessGranted(FicheVoter::EDIT, $activite->fiche()); }
        $asset = $assets->find($document->damAssetId()) ?? throw $this->createNotFoundException('Fichier DAM introuvable.');

        return $this->redirect($storage->temporaryUrl($asset->originalStorageKey(), new \DateTimeImmutable('+10 minutes')));
    }
}
