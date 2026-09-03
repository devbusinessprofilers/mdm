<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\DocumentUsage;
use App\Dam\Enum\MediaKind;
use App\Dam\Enum\MediaStatus;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\PublishDocument;
use App\Dam\Message\UnpublishDocument;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\DocumentUploadException;
use App\Dam\Service\LieuDocumentPresenter;
use App\Dam\Service\LieuDocumentUploader;
use App\Pim\Api\Dto\DocumentPatchInput;
use App\Pim\Api\Dto\DocumentPublicationInput;
use App\Pim\Api\Dto\LieuDocumentResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalDocumentAccess;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProcessorInterface<mixed, LieuDocumentResource|void> */
final readonly class LieuDocumentProcessor implements ProcessorInterface
{
    public function __construct(
        private LieuApiState $state,
        private RessourceLieuRepository $resources,
        private MediaAssetRepository $assets,
        private LieuDocumentUploader $uploader,
        private LieuDocumentPresenter $presenter,
        private ExternalDocumentAccess $access,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private RequestStack $requests,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ?LieuDocumentResource {
        $lieu = $this->state->lieu((string) ($uriVariables['lieuId'] ?? ''));
        $this->state->assertVersion($lieu);
        if (!$operation instanceof HttpOperation) {
            throw new \LogicException('Opération HTTP requise.');
        }
        $method = $operation->getMethod();
        $template = $operation->getUriTemplate() ?? '';

        return $lieu
            ->fiche()
            ->preserveWorkflowDuring(function () use (
                $data,
                $lieu,
                $method,
                $template,
                $uriVariables,
            ): ?LieuDocumentResource {
                if (
                    'POST' === $method
                    && str_ends_with($template, '/documents')
                ) {
                    return $this->create($lieu);
                }
                $document = $this->document(
                    $lieu,
                    (string) ($uriVariables['documentId'] ?? ''),
                );
                if ('PATCH' === $method) {
                    if (!$data instanceof DocumentPatchInput) {
                        throw new ApiProblemException(400, 'invalid_payload', 'Corps JSON invalide.');
                    }

                    return $this->metadata($lieu, $document, $data);
                }
                if (
                    'POST' === $method
                    && str_ends_with($template, '/fichier')
                ) {
                    return $this->replace($lieu, $document);
                }
                if (
                    'POST' === $method
                    && str_ends_with($template, '/publication')
                ) {
                    if (!$data instanceof DocumentPublicationInput) {
                        throw new ApiProblemException(400, 'invalid_payload', 'Corps JSON invalide.');
                    }

                    return $this->publication(
                        $lieu,
                        $document,
                        $data->published,
                    );
                }
                if ('DELETE' === $method) {
                    $this->access->requireWrite($document);
                    $this->queuePublicDeletion($document);
                    $this->outbox->enqueue(
                        new DeleteMedia($document->damAssetId()),
                    );
                    $lieu->removeRessource($document);
                    $this->entityManager->remove($document);
                    $this->changed($lieu);

                    return null;
                }
                throw new \LogicException('Opération documentaire inconnue.');
            });
    }

    private function create(Lieu $lieu): LieuDocumentResource
    {
        $request = $this->requests->getCurrentRequest();
        if (null === $request) {
            throw new \LogicException('Aucune requête HTTP active.');
        }
        $usage = DocumentUsage::tryFrom($request->request->getString('usage'));
        if (null === $usage) {
            throw new ApiProblemException(422, 'invalid_document_usage', 'Usage documentaire invalide.');
        }
        $this->access->requireCreate($usage->access());
        $salle = $this->room(
            $lieu,
            $request->request->getString('salleId'),
            $usage,
        );
        $this->assertMaximum($lieu, $usage, $salle);
        $file = $request->files->get('document');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(422, 'document_required', 'Le champ multipart document est obligatoire.');
        }
        $asset = $this->upload($file, $lieu, $usage);
        try {
            $document = new RessourceLieu();
            $document->configureDocument($usage);
            $document->changeDamAssetId($asset->id());
            $document->changeSalle($salle);
            $document->changeLegende($request->request->getString('title'));
            $document->changeSource($request->request->getString('source'));
            if ($request->request->getBoolean('rightsGranted')) {
                throw new ApiProblemException(403, 'rights_validation_forbidden', 'La validation des droits est réservée aux validateurs internes du PIM.');
            }
            $lieu->addRessource($document);
            $this->entityManager->persist($asset);
            $this->changed($lieu);
        } catch (\Throwable $e) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
            throw $e;
        }

        return $this->presenter->resource($document);
    }

    private function metadata(
        Lieu $lieu,
        RessourceLieu $document,
        DocumentPatchInput $input,
    ): LieuDocumentResource {
        $this->access->requireWrite($document);
        $rightsWereGranted = $document->rightsGranted();
        $payload = json_decode(
            (string) $this->requests->getCurrentRequest()?->getContent(),
            true,
        );
        $payload = is_array($payload) ? $payload : [];
        if (array_key_exists('usage', $payload)) {
            $usage = DocumentUsage::tryFrom((string) $input->usage);
            if (null === $usage) {
                throw new ApiProblemException(422, 'invalid_document_usage', 'Usage documentaire invalide.');
            }
            $this->access->requireCreate($usage->access());
            $salle = $this->room($lieu, (string) $input->salleId, $usage);
            $this->assertMaximum($lieu, $usage, $salle, $document);
            if ($usage !== $document->documentUsage()) {
                $this->queuePublicDeletion($document);
            }
            $document->configureDocument($usage);
            $document->changeSalle($salle);
        } elseif (array_key_exists('salleId', $payload)) {
            $document->changeSalle(
                $this->room(
                    $lieu,
                    (string) $input->salleId,
                    $document->documentUsage() ?? throw new \LogicException(),
                ),
            );
        }
        if (array_key_exists('title', $payload)) {
            $document->changeLegende($input->title);
        }
        if (array_key_exists('source', $payload)) {
            $document->changeSource($input->source);
        }
        if (array_key_exists('keywords', $payload)) {
            $document->changeKeywords($input->keywords);
        }
        if (array_key_exists('rightsExpiresAt', $payload)) {
            $document->changeRightsExpiresAt($input->rightsExpiresAt);
        }
        if (array_key_exists('rightsGranted', $payload)) {
            throw new ApiProblemException(403, 'rights_validation_forbidden', 'La validation des droits est réservée aux validateurs internes du PIM.');
        }
        if ($rightsWereGranted && !$document->rightsGranted()) {
            $this->queuePublicDeletion($document);
        }
        $this->changed($lieu);

        return $this->presenter->resource($document);
    }

    private function replace(
        Lieu $lieu,
        RessourceLieu $document,
    ): LieuDocumentResource {
        $this->access->requireWrite($document);
        $file = $this->requests->getCurrentRequest()?->files->get('document');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(422, 'document_required', 'Le champ multipart document est obligatoire.');
        }
        $usage =
            $document->documentUsage() ??
            throw new ApiProblemException(422, 'invalid_document_usage', 'Usage documentaire invalide.');
        $asset = $this->upload($file, $lieu, $usage);
        $oldId = $document->damAssetId();
        try {
            $this->queuePublicDeletion($document);
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

        return $this->presenter->resource($document);
    }

    private function publication(
        Lieu $lieu,
        RessourceLieu $document,
        bool $published,
    ): LieuDocumentResource {
        $this->access->requirePublish();
        if ($published) {
            $asset = $this->assets->find($document->damAssetId());
            if (
                !($asset instanceof MediaAsset)
                || MediaKind::Document !== $asset->kind()
                || MediaStatus::Processed !== $asset->status()
            ) {
                throw new ApiProblemException(422, 'invalid_document', 'Le fichier DAM est absent ou invalide.');
            }
            try {
                $document->requestPublication();
            } catch (\DomainException $e) {
                throw new ApiProblemException(422, 'publication_refused', $e->getMessage());
            }
            $this->outbox->enqueue(new PublishDocument($document->id()));
        } else {
            $this->queuePublicDeletion($document);
        }
        $this->changed($lieu);

        return $this->presenter->resource($document);
    }

    private function document(Lieu $lieu, string $id): RessourceLieu
    {
        $document = $this->resources->find($id);
        if (
            !($document instanceof RessourceLieu)
            || $document->lieu() !== $lieu
            || NatureRessource::Document !== $document->nature()
        ) {
            throw new ApiProblemException(404, 'not_found', 'Document introuvable.');
        }

        return $document;
    }

    private function room(Lieu $lieu, string $id, DocumentUsage $usage): ?Salle
    {
        if ('' === $id) {
            if ($usage->requiresRoom()) {
                throw new ApiProblemException(422, 'room_required', 'Le plan de salle doit être rattaché à une salle.');
            }

            return null;
        }
        foreach ($lieu->salles() as $salle) {
            if ($salle->id() === $id) {
                return $salle;
            }
        }
        throw new ApiProblemException(422, 'foreign_room', "La salle n'appartient pas au lieu.");
    }

    private function assertMaximum(
        Lieu $lieu,
        DocumentUsage $usage,
        ?Salle $salle,
        ?RessourceLieu $current = null,
    ): void {
        $count = 0;
        foreach ($lieu->ressources() as $resource) {
            if (
                $resource !== $current
                && NatureRessource::Document === $resource->nature()
                && $resource->usage() === $usage->value
                && (!$usage->requiresRoom() || $resource->salle() === $salle)
            ) {
                ++$count;
            }
        }
        if (
            null !== $usage->maximumCount()
            && $count >= $usage->maximumCount()
        ) {
            throw new ApiProblemException(422, 'document_limit_reached', 'Le nombre maximal de documents pour cet usage est atteint.');
        }
    }

    private function upload(
        UploadedFile $file,
        Lieu $lieu,
        DocumentUsage $usage,
    ): MediaAsset {
        try {
            return $this->uploader->upload($file, $lieu, $usage);
        } catch (DocumentUploadException $e) {
            throw new ApiProblemException($e->httpStatus, 'invalid_document_file', $e->getMessage());
        }
    }

    private function queuePublicDeletion(RessourceLieu $document): void
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
        $lieu->fiche()->markSystemChanged();
        $this->state->flushAndIndex($lieu);
    }
}
