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
use App\Dam\Service\DocumentUploadException;
use App\Dam\Service\LieuDocumentUploader;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Enum\NatureRessource;
use App\Pim\Form\LieuDocumentMetadataType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Form\LieuDocumentUploadType;
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
        '/admin/lieux/{id}/documents',
        name: 'app_pim_lieu_document_',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
    ),
]
final class LieuDocumentController extends AbstractController
{
    public function __construct(
        private readonly LieuDocumentUploader $uploader,
        private readonly RessourceLieuRepository $resources,
        private readonly MediaAssetRepository $assets,
        private readonly EntityManagerInterface $entityManager,
        private readonly OutboxPublisherInterface $outbox,
        private readonly FormFactoryInterface $forms,
    ) {
    }

    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, Lieu $lieu): RedirectResponse
    {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $form = $this->forms->createNamed(
            'document_upload',
            LieuDocumentUploadType::class,
            null,
            ['salles' => $lieu->salles()->toArray()],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->failure(
                $lieu,
                'Le formulaire documentaire est invalide.',
            );
        }
        /** @var array{usage: DocumentUsage, salle: Salle|null, title: string|null, source: string|null, rightsGranted: bool} $data */
        $data = $form->getData();
        $usage = $data['usage'];
        $salle = $data['salle'];
        if ($usage->requiresRoom() && null === $salle) {
            return $this->failure(
                $lieu,
                'Un plan de salle doit être rattaché à une salle.',
            );
        }
        $files = $form->get('documents')->getData();
        $files = is_array($files)
            ? array_values(
                array_filter(
                    $files,
                    static fn (mixed $file): bool => $file instanceof UploadedFile,
                ),
            )
            : [];
        if ([] === $files) {
            return $this->failure($lieu, 'Sélectionnez au moins un document.');
        }
        if (!$this->withinMaximum($lieu, $usage, $salle, count($files))) {
            return $this->failure(
                $lieu,
                'Le nombre maximal de documents pour cet usage serait dépassé.',
            );
        }
        $uploaded = [];
        try {
            foreach ($files as $file) {
                $asset = $this->uploader->upload($file, $lieu, $usage);
                $uploaded[] = $asset;
                $document = new RessourceLieu();
                $document->configureDocument($usage);
                $document->changeDamAssetId($asset->id());
                $document->changeSalle($salle);
                $document->changeLegende($data['title']);
                $document->changeSource($data['source']);
                if ($data['rightsGranted']) {
                    $document->grantRights($this->actorId());
                }
                $lieu->addRessource($document);
                $this->entityManager->persist($asset);
            }
            $this->changed($lieu);
        } catch (DocumentUploadException|\DomainException $e) {
            foreach ($uploaded as $asset) {
                try {
                    $this->uploader->delete($asset);
                } catch (\Throwable) {
                }
            }

            return $this->failure($lieu, $e->getMessage());
        } catch (\Throwable $e) {
            foreach ($uploaded as $asset) {
                try {
                    $this->uploader->delete($asset);
                } catch (\Throwable) {
                }
            }
            throw $e;
        }
        $this->addFlash(
            'success',
            count($uploaded).' document(s) ajouté(s).',
        );

        return $this->back($lieu);
    }

    #[Route('/{resourceId}/modifier', name: 'update', methods: ['POST'])]
    public function update(
        Request $request,
        Lieu $lieu,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $document = $this->document($lieu, $resourceId);
        $form = $this->forms->createNamed(
            'document_metadata_'.$document->id(),
            LieuDocumentMetadataType::class,
            [
                'usage' => $document->documentUsage(),
                'salle' => $document->salle(),
                'title' => $document->legende(),
                'source' => $document->source(),
                'rightsGranted' => $document->rightsGranted(),
            ],
            ['salles' => $lieu->salles()->toArray()],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->failure(
                $lieu,
                'Le formulaire documentaire est invalide.',
            );
        }
        /** @var array{usage: DocumentUsage, salle: Salle|null, title: string|null, source: string|null, rightsGranted: bool} $data */
        $data = $form->getData();
        $usage = $data['usage'];
        $salle = $data['salle'];
        if ($usage->requiresRoom() && null === $salle) {
            return $this->failure(
                $lieu,
                'Un plan de salle doit être rattaché à une salle.',
            );
        }
        if (!$this->withinMaximum($lieu, $usage, $salle, 1, $document)) {
            return $this->failure(
                $lieu,
                'Le nombre maximal de documents pour cet usage serait dépassé.',
            );
        }
        if ($usage !== $document->documentUsage()) {
            $this->unpublish($document);
        }
        $document->configureDocument($usage);
        $document->changeSalle($salle);
        $document->changeLegende($data['title']);
        $document->changeSource($data['source']);
        if ($data['rightsGranted']) {
            $document->grantRights($this->actorId());
        } else {
            $document->revokeRights();
            $this->unpublish($document);
        }
        $this->changed($lieu);
        $this->addFlash('success', 'Document modifié.');

        return $this->back($lieu);
    }

    #[Route('/{resourceId}/fichier', name: 'replace', methods: ['POST'])]
    public function replace(
        Request $request,
        Lieu $lieu,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $document = $this->document($lieu, $resourceId);
        $form = $this->forms->createNamed(
            'document_replace_'.$document->id(),
            LieuDocumentReplaceType::class,
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->failure(
                $lieu,
                'Le formulaire de remplacement est invalide.',
            );
        }
        $file = $form->get('document')->getData();
        if (!($file instanceof UploadedFile)) {
            return $this->failure($lieu, 'Sélectionnez un document.');
        }
        try {
            $asset = $this->uploader->upload(
                $file,
                $lieu,
                $document->documentUsage() ??
                    throw new \DomainException('Usage invalide.'),
            );
        } catch (\DomainException $e) {
            return $this->failure($lieu, $e->getMessage());
        }
        $oldId = $document->damAssetId();
        try {
            $this->unpublish($document);
            $this->entityManager->persist($asset);
            $document->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new DeleteMedia($oldId));
            $this->changed($lieu);
        } catch (\Throwable $e) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
            throw $e;
        }
        $this->addFlash('success', 'Fichier remplacé.');

        return $this->back($lieu);
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
        Lieu $lieu,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $document = $this->document($lieu, $resourceId);
        $form = $this->forms->createNamed(
            'document_publication_'.$document->id(),
            ActionType::class,
            null,
            [
                'button_label' => 'Action',
                'csrf_token_id' => 'document-publication-'.$document->id(),
            ],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->failure(
                $lieu,
                'Le formulaire de publication est invalide.',
            );
        }
        try {
            if ('published' !== $document->publicationStatus()?->value) {
                $document->requestPublication();
                $this->outbox->enqueue(new PublishDocument($document->id()));
            } else {
                $this->unpublish($document);
            }
            $this->changed($lieu);
        } catch (\DomainException $e) {
            return $this->failure($lieu, $e->getMessage());
        }
        $this->addFlash('success', 'Changement de publication mis en file.');

        return $this->back($lieu);
    }

    #[Route('/{resourceId}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Lieu $lieu,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $document = $this->document($lieu, $resourceId);
        $form = $this->forms->createNamed(
            'document_delete_'.$document->id(),
            ActionType::class,
            null,
            [
                'button_label' => 'Supprimer',
                'csrf_token_id' => 'document-delete-'.$document->id(),
            ],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->failure(
                $lieu,
                'Le formulaire de suppression est invalide.',
            );
        }
        $this->unpublish($document);
        $this->outbox->enqueue(new DeleteMedia($document->damAssetId()));
        $lieu->removeRessource($document);
        $this->entityManager->remove($document);
        $this->changed($lieu);
        $this->addFlash('success', 'Document supprimé.');

        return $this->back($lieu);
    }

    #[Route('/{resourceId}/download', name: 'download', methods: ['GET'])]
    public function download(
        Lieu $lieu,
        string $resourceId,
        PrivateObjectStorageInterface $storage,
    ): RedirectResponse {
        $document = $this->document($lieu, $resourceId);
        if (DocumentAccess::Private === $document->documentAccess()) {
            $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        } else {
            $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        }
        $asset =
            $this->assets->find($document->damAssetId()) ??
            throw $this->createNotFoundException('Fichier DAM introuvable.');

        return $this->redirect(
            $storage->temporaryUrl(
                $asset->originalStorageKey(),
                new \DateTimeImmutable('+10 minutes'),
            ),
        );
    }

    private function document(Lieu $lieu, string $id): RessourceLieu
    {
        $resource = $this->resources->find($id);
        if (
            !($resource instanceof RessourceLieu)
            || $resource->lieu() !== $lieu
            || NatureRessource::Document !== $resource->nature()
        ) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        return $resource;
    }

    private function withinMaximum(
        Lieu $lieu,
        DocumentUsage $usage,
        ?Salle $salle,
        int $added,
        ?RessourceLieu $current = null,
    ): bool {
        if (null === $usage->maximumCount()) {
            return true;
        }
        $count = 0;
        foreach ($lieu->ressources() as $resource) {
            if (
                $resource !== $current
                && $resource->usage() === $usage->value
                && (!$usage->requiresRoom() || $resource->salle() === $salle)
            ) {
                ++$count;
            }
        }

        return $count + $added <= $usage->maximumCount();
    }

    private function unpublish(RessourceLieu $document): void
    {
        $key = $document->requestUnpublication();
        if (null !== $key) {
            $this->outbox->enqueue(
                new UnpublishDocument($document->id(), $key),
            );
        }
    }

    private function changed(Lieu $lieu): void
    {
        $lieu->fiche()->markChanged();
        $this->outbox->enqueue(new IndexFiche($lieu->id()));
        $this->entityManager->flush();
    }

    private function actorId(): string
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->id() : 'system';
    }

    private function failure(Lieu $lieu, string $message): RedirectResponse
    {
        $this->addFlash('error', $message);

        return $this->back($lieu);
    }

    private function back(Lieu $lieu): RedirectResponse
    {
        return $this->redirectToRoute('app_pim_lieu_edit', [
            'id' => $lieu->id(),
        ]);
    }
}
