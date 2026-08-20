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

#[Route('/referentiel/lieux/fiche/{id}/documents', name: 'app_pim_lieu_document_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class LieuDocumentController extends AbstractController
{
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, Lieu $lieu, FormFactoryInterface $forms, LieuDocumentManager $manager, CurrentActorProvider $actor, MediasBlocReponse $reponse): Response
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $form = $forms->createNamed('document_upload', LieuDocumentUploadType::class, null, ['salles' => $lieu->salles()->toArray()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $reponse->repondre($request, $lieu->fiche(), 'Le formulaire documentaire est invalide.', '');
        }
        $files = $form->get('documents')->getData();
        $files = is_array($files) ? array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile)) : [];
        if ([] === $files) {
            return $reponse->repondre($request, $lieu->fiche(), 'Sélectionnez au moins un document.', '');
        }
        $erreur = null;
        $succes = '';
        try {
            /** @var array{usage: \App\Dam\Enum\DocumentUsage, salle: \App\Pim\Entity\Lieu\Salle|null, title: string|null, source: string|null} $data */
            $data = $form->getData();
            $count = $manager->upload($lieu, $files, $data, $actor->id());
            $succes = $count.' document(s) ajouté(s).';
        } catch (\DomainException|\App\Dam\Service\DocumentUploadException $exception) {
            $erreur = $exception->getMessage();
        }

        return $reponse->repondre($request, $lieu->fiche(), $erreur, $succes);
    }

    #[Route('/{resourceId}/modifier', name: 'update', methods: ['POST'])]
    public function update(Request $request, Lieu $lieu, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, LieuDocumentManager $manager, CurrentActorProvider $actor, MediasBlocReponse $reponse): Response
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $document = $resources->findDocumentForFiche($lieu->fiche(), $resourceId);
        if (null === $document || $document->lieu() !== $lieu) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('document_metadata_'.$document->id(), LieuDocumentMetadataType::class, [
            'usage' => $document->documentUsage(), 'salle' => $document->salle(), 'title' => $document->legende(),
            'source' => $document->source(), 'keywords' => $document->keywords(), 'rightsExpiresAt' => $document->rightsExpiresAt(),
        ], ['salles' => $lieu->salles()->toArray()]);
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) {
            $erreur = 'Le formulaire documentaire est invalide.';
        } else {
            try {
                /** @var array{usage: \App\Dam\Enum\DocumentUsage, salle: \App\Pim\Entity\Lieu\Salle|null, title: string|null, source: string|null, keywords: string|null, rightsExpiresAt: \DateTimeImmutable|null} $data */
                $data = $form->getData();
                $manager->update($document, $lieu, $data, $actor->id());
            } catch (\DomainException $exception) { $erreur = $exception->getMessage(); }
        }

        return $reponse->repondre($request, $lieu->fiche(), $erreur, 'Document modifié.');
    }

    #[Route('/{resourceId}/fichier', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, Lieu $lieu, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, LieuDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $document = $resources->findDocumentForFiche($lieu->fiche(), $resourceId);
        if (null === $document || $document->lieu() !== $lieu) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('document_replace_'.$document->id(), LieuDocumentReplaceType::class);
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) {
            $erreur = 'Le formulaire de remplacement est invalide.';
        } else {
            $file = $form->get('document')->getData();
            if (!$file instanceof UploadedFile) {
                $erreur = 'Sélectionnez un document.';
            } else {
                try { $manager->replace($document, $lieu, $file); }
                catch (\DomainException $exception) { $erreur = $exception->getMessage(); }
            }
        }

        return $reponse->repondre($request, $lieu->fiche(), $erreur, 'Fichier remplacé.');
    }

    #[Route('/{resourceId}/publication', name: 'publication', methods: ['POST'])]
    public function publication(Request $request, Lieu $lieu, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, LieuDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $document = $resources->findDocumentForFiche($lieu->fiche(), $resourceId);
        if (null === $document || $document->lieu() !== $lieu) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('document_publication_'.$document->id(), ActionType::class, null, ['button_label' => 'Action', 'csrf_token_id' => 'document-publication-'.$document->id()]);
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) { $erreur = 'Le formulaire de publication est invalide.'; }
        else {
            try { $manager->togglePublication($document, $lieu); }
            catch (\DomainException $exception) { $erreur = $exception->getMessage(); }
        }

        return $reponse->repondre($request, $lieu->fiche(), $erreur, 'Changement de publication mis en file.');
    }

    #[Route('/{resourceId}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Lieu $lieu, string $resourceId, RessourceLieuRepository $resources, FormFactoryInterface $forms, LieuDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $document = $resources->findDocumentForFiche($lieu->fiche(), $resourceId);
        if (null === $document || $document->lieu() !== $lieu) { throw $this->createNotFoundException('Document introuvable.'); }
        $form = $forms->createNamed('document_delete_'.$document->id(), ActionType::class, null, ['button_label' => 'Supprimer', 'csrf_token_id' => 'document-delete-'.$document->id()]);
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) { $erreur = 'Le formulaire de suppression est invalide.'; }
        else { $manager->delete($document, $lieu); }

        return $reponse->repondre($request, $lieu->fiche(), $erreur, 'Document supprimé.');
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
