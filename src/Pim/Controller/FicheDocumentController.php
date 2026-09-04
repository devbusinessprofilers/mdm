<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Dam\Enum\DocumentAccess;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\DocumentUploadException;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\FicheDetailResolver;
use App\Pim\Service\FicheDocumentManager;
use App\Pim\Service\MediasBlocReponse;
use App\Pim\Service\ProfilDocumentsGamme;
use App\Shared\Form\ActionType;
use App\Shared\Service\PrivateObjectStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Documents des fiches, toutes gammes : dépôt (Lieu, Restaurant), métadonnées,
 * remplacement, publication, suppression et téléchargement. Les noms de
 * formulaires et de jetons restent ceux de chaque gamme (ProfilDocumentsGamme) ;
 * les réponses passent par MediasBlocReponse (JSON en fetch, flash sinon).
 */
#[Route('/referentiel/{gamme}/fiche/{id}/documents', name: 'app_pim_fiche_document_', requirements: ['gamme' => 'lieux|restaurants|activites|services', 'id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class FicheDocumentController extends AbstractController
{
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, string $gamme, string $id, FicheDetailResolver $resolver, FormFactoryInterface $forms, FicheDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $profil = ProfilDocumentsGamme::pour($entite->fiche()->type());
        if (null === $profil->typeDepot || !($entite instanceof Lieu || $entite instanceof Restaurant)) {
            throw $this->createNotFoundException('Pas de dépôt documentaire pour cette gamme.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        // Le formulaire soumis se reconnaît à son nom (un par onglet du volet Médias).
        $formulaires = $profil->formulairesDepot();
        $nom = (string) array_key_last($formulaires);
        foreach (array_keys($formulaires) as $candidat) {
            if ($request->request->has($candidat) || $request->files->has($candidat)) {
                $nom = $candidat;
                break;
            }
        }
        $form = $forms->createNamed($nom, $profil->typeDepot, null, $profil->optionsDepot($entite, $formulaires[$nom]));
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $reponse->repondre($request, $entite->fiche(), 'Le formulaire documentaire est invalide.', '');
        }
        $files = $form->get('documents')->getData();
        $files = is_array($files) ? array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile)) : [];
        if ([] === $files) {
            return $reponse->repondre($request, $entite->fiche(), 'Sélectionnez au moins un document.', '');
        }
        $erreur = null;
        $succes = '';
        try {
            /** @var array{usage: \App\Dam\Enum\DocumentUsage, salle: \App\Pim\Entity\Lieu\Salle|\App\Pim\Entity\Restaurant\RestaurantSalle|null, title: string|null, source: string|null} $data */
            $data = $form->getData() + ['salle' => null];
            $count = $manager->upload($entite, $files, $data);
            $succes = $count.' document(s) ajouté(s).';
        } catch (\DomainException|DocumentUploadException $exception) {
            $erreur = $exception->getMessage();
        }

        return $reponse->repondre($request, $entite->fiche(), $erreur, $succes);
    }

    #[Route('/{resourceId}/modifier', name: 'update', methods: ['POST'])]
    public function update(Request $request, string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, RessourceLieuRepository $resources, FormFactoryInterface $forms, FicheDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $profil = ProfilDocumentsGamme::pour($entite->fiche()->type());
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $document = $resources->findDocumentForFiche($entite->fiche(), $resourceId, $profil->usageImpose) ?? throw $this->createNotFoundException('Document introuvable.');
        $form = $forms->createNamed($profil->nomFormulaire('metadata', $document->id()), $profil->typeMetadata, $profil->donneesMetadata($document), $profil->optionsMetadata($entite));
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) {
            $erreur = 'Le formulaire documentaire est invalide.';
        } else {
            try {
                /** @var array<string, mixed> $data */
                $data = $form->getData();
                $manager->updateMetadata($document, $entite->fiche(), $data);
            } catch (\DomainException $exception) {
                $erreur = $exception->getMessage();
            }
        }

        return $reponse->repondre($request, $entite->fiche(), $erreur, 'Document modifié.');
    }

    #[Route('/{resourceId}/fichier', name: 'replace', methods: ['POST'])]
    public function replace(Request $request, string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, RessourceLieuRepository $resources, FormFactoryInterface $forms, FicheDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $profil = ProfilDocumentsGamme::pour($entite->fiche()->type());
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $document = $resources->findDocumentForFiche($entite->fiche(), $resourceId, $profil->usageImpose) ?? throw $this->createNotFoundException('Document introuvable.');
        $form = $forms->createNamed($profil->nomFormulaire('replace', $document->id()), LieuDocumentReplaceType::class);
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) {
            $erreur = 'Le formulaire de remplacement est invalide.';
        } else {
            $file = $form->get('document')->getData();
            if (!$file instanceof UploadedFile) {
                $erreur = 'Sélectionnez un document.';
            } else {
                try {
                    $manager->replace($document, $entite->fiche(), $file);
                } catch (\DomainException|DocumentUploadException $exception) {
                    $erreur = $exception->getMessage();
                }
            }
        }

        return $reponse->repondre($request, $entite->fiche(), $erreur, 'Fichier remplacé.');
    }

    #[Route('/{resourceId}/publication', name: 'publication', methods: ['POST'])]
    public function publication(Request $request, string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, RessourceLieuRepository $resources, FormFactoryInterface $forms, FicheDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $profil = ProfilDocumentsGamme::pour($entite->fiche()->type());
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $document = $resources->findDocumentForFiche($entite->fiche(), $resourceId, $profil->usageImpose) ?? throw $this->createNotFoundException('Document introuvable.');
        $form = $forms->createNamed($profil->nomFormulaire('publication', $document->id()), ActionType::class, null, ['button_label' => 'Action', 'csrf_token_id' => $profil->jetonCsrf('publication', $document->id())]);
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) {
            $erreur = 'Le formulaire de publication est invalide.';
        } else {
            try {
                $manager->togglePublication($document, $entite->fiche());
            } catch (\DomainException $exception) {
                $erreur = $exception->getMessage();
            }
        }

        return $reponse->repondre($request, $entite->fiche(), $erreur, 'Changement de publication mis en file.');
    }

    #[Route('/{resourceId}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, RessourceLieuRepository $resources, FormFactoryInterface $forms, FicheDocumentManager $manager, MediasBlocReponse $reponse): Response
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $profil = ProfilDocumentsGamme::pour($entite->fiche()->type());
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        $document = $resources->findDocumentForFiche($entite->fiche(), $resourceId, $profil->usageImpose) ?? throw $this->createNotFoundException('Document introuvable.');
        $form = $forms->createNamed($profil->nomFormulaire('delete', $document->id()), ActionType::class, null, ['button_label' => 'Supprimer', 'csrf_token_id' => $profil->jetonCsrf('delete', $document->id())]);
        $form->handleRequest($request);
        $erreur = null;
        if (!$form->isSubmitted() || !$form->isValid()) {
            $erreur = 'Le formulaire de suppression est invalide.';
        } else {
            $manager->delete($document, $entite->fiche());
        }

        return $reponse->repondre($request, $entite->fiche(), $erreur, 'Document supprimé.');
    }

    #[Route('/{resourceId}/download', name: 'download', methods: ['GET'])]
    public function download(string $gamme, string $id, string $resourceId, FicheDetailResolver $resolver, RessourceLieuRepository $resources, MediaAssetRepository $assets, PrivateObjectStorageInterface $storage): RedirectResponse
    {
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $profil = ProfilDocumentsGamme::pour($entite->fiche()->type());
        $document = $resources->findDocumentForFiche($entite->fiche(), $resourceId, $profil->usageImpose) ?? throw $this->createNotFoundException('Document introuvable.');
        if (DocumentAccess::Private === $document->documentAccess()) {
            $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        } else {
            $this->denyAccessUnlessGranted(FicheVoter::EDIT, $entite->fiche());
        }
        $asset = $assets->find($document->damAssetId()) ?? throw $this->createNotFoundException('Fichier DAM introuvable.');

        return $this->redirect($storage->temporaryUrl($asset->originalStorageKey(), new \DateTimeImmutable('+10 minutes')));
    }
}
