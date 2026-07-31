<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Entity\User;
use App\Account\Security\FicheVoter;
use App\Dam\Enum\DocumentAccess;
use App\Dam\Enum\DocumentUsage;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\PublishDocument;
use App\Dam\Message\UnpublishDocument;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\FicheDocumentUploader;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Form\ActiviteDocumentMetadataType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Form\ActionType;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[
    Route(
        '/admin/activites/{id}/documents',
        name: 'app_pim_activite_document_',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
    ),
]
final class ActiviteDocumentController extends AbstractController
{
    public function __construct(
        private readonly RessourceLieuRepository $resources,
        private readonly MediaAssetRepository $assets,
        private readonly FicheDocumentUploader $uploader,
        private readonly EntityManagerInterface $em,
        private readonly OutboxPublisherInterface $outbox,
        private readonly FormFactoryInterface $forms,
    ) {
    }

    #[Route('/{resourceId}/modifier', name: 'update', methods: ['POST'])]
    public function update(
        Request $request,
        Activite $activite,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $activite->fiche());
        $d = $this->document($activite, $resourceId);
        $form = $this->forms->createNamed(
            'activite_document_metadata_'.$d->id(),
            ActiviteDocumentMetadataType::class,
            [
                'title' => $d->legende(),
                'source' => $d->source(),
                'rightsGranted' => $d->rightsGranted(),
            ],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->fail(
                $activite,
                'Le formulaire documentaire est invalide.',
            );
        }
        $data = $form->getData();
        $d->changeLegende(
            is_string($data['title'] ?? null) ? $data['title'] : null,
        );
        $d->changeSource(
            is_string($data['source'] ?? null) ? $data['source'] : null,
        );
        if (true === ($data['rightsGranted'] ?? false)) {
            $d->grantRights($this->actor());
        } else {
            $d->revokeRights();
            $this->unpublish($d);
        }
        $this->changed($activite);

        return $this->ok($activite, 'Document modifié.');
    }

    #[Route('/{resourceId}/fichier', name: 'replace', methods: ['POST'])]
    public function replace(
        Request $request,
        Activite $activite,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $activite->fiche());
        $d = $this->document($activite, $resourceId);
        $form = $this->forms->createNamed(
            'activite_document_replace_'.$d->id(),
            LieuDocumentReplaceType::class,
        );
        $form->handleRequest($request);
        $file =
            $form->isSubmitted() && $form->isValid()
                ? $form->get('document')->getData()
                : null;
        if (!($file instanceof UploadedFile)) {
            return $this->fail($activite, 'Sélectionnez un document valide.');
        }
        $asset = $this->uploader->upload(
            $file,
            $activite->fiche(),
            DocumentUsage::CommercialSupport,
        );
        $old = $d->damAssetId();
        try {
            $this->unpublish($d);
            $this->em->persist($asset);
            $d->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new DeleteMedia($old));
            $this->changed($activite);
        } catch (\Throwable $e) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
            throw $e;
        }

        return $this->ok($activite, 'Fichier remplacé.');
    }

    #[
        Route(
            '/{resourceId}/publication',
            name: 'publication',
            methods: ['POST'],
        ),
    ]
    public function publication(
        Request $request,
        Activite $activite,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $d = $this->document($activite, $resourceId);
        $form = $this->forms->createNamed(
            'activite_document_publication_'.$d->id(),
            ActionType::class,
            null,
            ['csrf_token_id' => 'activite-document-publication-'.$d->id()],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->fail($activite, 'Action de publication invalide.');
        }
        try {
            if ('published' === $d->publicationStatus()?->value) {
                $this->unpublish($d);
            } else {
                $d->requestPublication();
                $this->outbox->enqueue(new PublishDocument($d->id()));
            }
            $this->changed($activite);
        } catch (\DomainException $e) {
            return $this->fail($activite, $e->getMessage());
        }

        return $this->ok($activite, 'Changement de publication mis en file.');
    }

    #[Route('/{resourceId}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Activite $activite,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $activite->fiche());
        $d = $this->document($activite, $resourceId);
        $form = $this->forms->createNamed(
            'activite_document_delete_'.$d->id(),
            ActionType::class,
            null,
            ['csrf_token_id' => 'activite-document-delete-'.$d->id()],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->fail($activite, 'Action de suppression invalide.');
        }
        $this->unpublish($d);
        $this->outbox->enqueue(new DeleteMedia($d->damAssetId()));
        $activite->removeRessource($d);
        $this->em->remove($d);
        $this->changed($activite);

        return $this->ok($activite, 'Document supprimé.');
    }

    #[Route('/{resourceId}/download', name: 'download', methods: ['GET'])]
    public function download(
        Activite $activite,
        string $resourceId,
        PrivateObjectStorageInterface $storage,
    ): RedirectResponse {
        $d = $this->document($activite, $resourceId);
        if (DocumentAccess::Private === $d->documentAccess()) {
            $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        } else {
            $this->denyAccessUnlessGranted(
                FicheVoter::EDIT,
                $activite->fiche(),
            );
        }
        $asset =
            $this->assets->find($d->damAssetId()) ??
            throw $this->createNotFoundException('Fichier DAM introuvable.');

        return $this->redirect(
            $storage->temporaryUrl(
                $asset->originalStorageKey(),
                new \DateTimeImmutable('+10 minutes'),
            ),
        );
    }

    private function document(Activite $a, string $id): RessourceLieu
    {
        $r = $this->resources->find($id);
        if (
            !($r instanceof RessourceLieu)
            || $r->fiche() !== $a->fiche()
            || NatureRessource::Document !== $r->nature()
            || DocumentUsage::CommercialSupport !== $r->documentUsage()
        ) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        return $r;
    }

    private function unpublish(RessourceLieu $d): void
    {
        $key = $d->requestUnpublication();
        if (null !== $key) {
            $this->outbox->enqueue(new UnpublishDocument($d->id(), $key));
        }
    }

    private function changed(Activite $a): void
    {
        $a->fiche()->markChanged();
        $this->outbox->enqueue(new IndexFiche($a->id()));
        $this->em->flush();
    }

    private function actor(): string
    {
        $u = $this->getUser();

        return $u instanceof User ? $u->id() : 'system';
    }

    private function ok(Activite $a, string $m): RedirectResponse
    {
        $this->addFlash('success', $m);

        return $this->back($a);
    }

    private function fail(Activite $a, string $m): RedirectResponse
    {
        $this->addFlash('error', $m);

        return $this->back($a);
    }

    private function back(Activite $a): RedirectResponse
    {
        return $this->redirectToRoute('app_pim_activite_edit', [
            'id' => $a->id(),
        ]);
    }
}
