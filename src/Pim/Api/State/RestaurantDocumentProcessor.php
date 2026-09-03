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
use App\Dam\Service\FicheDocumentUploader;
use App\Dam\Service\LieuDocumentPresenter;
use App\Pim\Api\Dto\DocumentPatchInput;
use App\Pim\Api\Dto\DocumentPublicationInput;
use App\Pim\Api\Dto\LieuDocumentResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalDocumentAccess;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/** @implements ProcessorInterface<mixed, LieuDocumentResource|void> */
final readonly class RestaurantDocumentProcessor implements ProcessorInterface
{
    public function __construct(
        private RestaurantApiState $state,
        private RessourceLieuRepository $resources,
        private MediaAssetRepository $assets,
        private FicheDocumentUploader $uploader,
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
        $restaurant = $this->state->restaurant(
            (string) ($uriVariables['restaurantId'] ?? ''),
        );
        $this->state->assertVersion($restaurant);
        if (!$operation instanceof HttpOperation) {
            throw new \LogicException('Opération HTTP requise.');
        }

        $method = $operation->getMethod();
        $template = $operation->getUriTemplate() ?? '';

        return $restaurant->fiche()->preserveWorkflowDuring(
            function () use (
                $data,
                $restaurant,
                $method,
                $template,
                $uriVariables,
            ): ?LieuDocumentResource {
                if ('POST' === $method && str_ends_with($template, '/documents')) {
                    return $this->create($restaurant);
                }

                $document = $this->document(
                    $restaurant,
                    (string) ($uriVariables['documentId'] ?? ''),
                );
                if ('PATCH' === $method) {
                    if (!$data instanceof DocumentPatchInput) {
                        throw new ApiProblemException(400, 'invalid_payload', 'Corps JSON invalide.');
                    }

                    return $this->metadata($restaurant, $document, $data);
                }
                if ('POST' === $method && str_ends_with($template, '/fichier')) {
                    return $this->replace($restaurant, $document);
                }
                if ('POST' === $method && str_ends_with($template, '/publication')) {
                    if (!$data instanceof DocumentPublicationInput) {
                        throw new ApiProblemException(400, 'invalid_payload', 'Corps JSON invalide.');
                    }

                    return $this->publication(
                        $restaurant,
                        $document,
                        $data->published,
                    );
                }
                if ('DELETE' === $method) {
                    $this->access->requireWrite($document);
                    $this->unpublish($document);
                    $this->outbox->enqueue(
                        new DeleteMedia($document->damAssetId()),
                    );
                    $restaurant->removeRessource($document);
                    $this->entityManager->remove($document);
                    $this->changed($restaurant);

                    return null;
                }

                throw new \LogicException('Opération documentaire inconnue.');
            },
        );
    }

    private function create(Restaurant $restaurant): LieuDocumentResource
    {
        $request = $this->request();
        $usage = DocumentUsage::tryFrom($request->request->getString('usage'));
        if (null === $usage || !$this->allowedUsage($usage)) {
            throw new ApiProblemException(422, 'invalid_document_usage', 'Usage documentaire Restaurant invalide.');
        }
        $this->access->requireCreate($usage->access());

        $room = $this->room(
            $restaurant,
            $request->request->getString('salleId'),
        );
        if ($usage->requiresRoom() && null === $room) {
            throw new ApiProblemException(422, 'room_required', 'Un plan de salle doit être rattaché à une salle.');
        }
        $this->assertCount($restaurant, $usage, $room);

        $file = $request->files->get('document');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(422, 'document_required', 'Le champ multipart document est obligatoire.');
        }

        $asset = $this->upload($file, $restaurant, $usage);
        try {
            $document = new RessourceLieu();
            $document->configureDocument($usage);
            $document->changeRestaurantSalle($room);
            $document->changeDamAssetId($asset->id());
            $document->changeLegende($request->request->getString('title'));
            $document->changeSource($request->request->getString('source'));
            if ($request->request->getBoolean('rightsGranted')) {
                throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'rights_validation_forbidden', 'La validation des droits est réservée aux validateurs internes du PIM.');
            }
            $restaurant->addRessource($document);
            $this->entityManager->persist($asset);
            $this->changed($restaurant);
        } catch (\Throwable $exception) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
            throw $exception;
        }

        return $this->presenter->resource($document);
    }

    private function metadata(
        Restaurant $restaurant,
        RessourceLieu $document,
        DocumentPatchInput $input,
    ): LieuDocumentResource {
        $this->access->requireWrite($document);
        $rightsWereGranted = $document->rightsGranted();
        $payload = json_decode((string) $this->request()->getContent(), true);
        $payload = is_array($payload) ? $payload : [];

        if (array_key_exists('usage', $payload)) {
            $usage = DocumentUsage::tryFrom((string) $input->usage);
            if (null === $usage || !$this->allowedUsage($usage)) {
                throw new ApiProblemException(422, 'invalid_document_usage', 'Usage documentaire Restaurant invalide.');
            }
            $document->configureDocument($usage);
        }
        if (array_key_exists('salleId', $payload)) {
            $document->changeRestaurantSalle(
                $this->room($restaurant, $input->salleId),
            );
        }
        if (
            DocumentUsage::RoomPlan === $document->documentUsage()
            && null === $document->restaurantSalle()
        ) {
            throw new ApiProblemException(422, 'room_required', 'Un plan de salle doit être rattaché à une salle.');
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
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'rights_validation_forbidden', 'La validation des droits est réservée aux validateurs internes du PIM.');
        }
        if ($rightsWereGranted && !$document->rightsGranted()) {
            $this->unpublish($document);
        }

        $this->changed($restaurant);

        return $this->presenter->resource($document);
    }

    private function replace(
        Restaurant $restaurant,
        RessourceLieu $document,
    ): LieuDocumentResource {
        $this->access->requireWrite($document);
        $file = $this->request()->files->get('document');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(422, 'document_required', 'Le champ multipart document est obligatoire.');
        }

        $usage = $document->documentUsage()
            ?? throw new ApiProblemException(422, 'invalid_document_usage', 'Usage documentaire invalide.');
        $asset = $this->upload($file, $restaurant, $usage);
        $oldAssetId = $document->damAssetId();
        try {
            $this->unpublish($document);
            $this->entityManager->persist($asset);
            $document->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new DeleteMedia($oldAssetId));
            $this->changed($restaurant);
        } catch (\Throwable $exception) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
            throw $exception;
        }

        return $this->presenter->resource($document);
    }

    private function publication(
        Restaurant $restaurant,
        RessourceLieu $document,
        bool $published,
    ): LieuDocumentResource {
        $this->access->requirePublish();
        if ($published) {
            $asset = $this->assets->find($document->damAssetId());
            if (
                !$asset instanceof MediaAsset
                || MediaKind::Document !== $asset->kind()
                || MediaStatus::Processed !== $asset->status()
            ) {
                throw new ApiProblemException(422, 'invalid_document', 'Le fichier DAM est absent ou invalide.');
            }
            try {
                $document->requestPublication();
            } catch (\DomainException $exception) {
                throw new ApiProblemException(422, 'publication_refused', $exception->getMessage());
            }
            $this->outbox->enqueue(new PublishDocument($document->id()));
        } else {
            $this->unpublish($document);
        }
        $this->changed($restaurant);

        return $this->presenter->resource($document);
    }

    private function document(
        Restaurant $restaurant,
        string $id,
    ): RessourceLieu {
        $document = $this->resources->find($id);
        if (
            !$document instanceof RessourceLieu
            || $document->fiche() !== $restaurant->fiche()
            || NatureRessource::Document !== $document->nature()
            || null === $document->documentUsage()
            || !$this->allowedUsage($document->documentUsage())
        ) {
            throw new ApiProblemException(404, 'not_found', 'Document introuvable.');
        }

        return $document;
    }

    private function upload(
        UploadedFile $file,
        Restaurant $restaurant,
        DocumentUsage $usage,
    ): MediaAsset {
        try {
            return $this->uploader->upload($file, $restaurant->fiche(), $usage);
        } catch (DocumentUploadException $exception) {
            throw new ApiProblemException($exception->httpStatus, 'invalid_document_file', $exception->getMessage());
        }
    }

    private function room(
        Restaurant $restaurant,
        ?string $id,
    ): ?RestaurantSalle {
        if (null === $id || '' === trim($id)) {
            return null;
        }
        foreach ($restaurant->salles() as $room) {
            if ($room->id() === $id) {
                return $room;
            }
        }

        throw new ApiProblemException(422, 'foreign_room', 'La salle doit appartenir au Restaurant.');
    }

    private function assertCount(
        Restaurant $restaurant,
        DocumentUsage $usage,
        ?RestaurantSalle $room,
    ): void {
        $maximum = $usage->maximumCount();
        if (null === $maximum) {
            return;
        }

        $count = 0;
        foreach ($restaurant->ressources() as $document) {
            if (
                $document->documentUsage() === $usage
                && $document->restaurantSalle() === $room
            ) {
                ++$count;
            }
        }
        if ($count >= $maximum) {
            throw new ApiProblemException(422, 'document_limit_reached', 'Le nombre maximal de documents pour cet usage est atteint.');
        }
    }

    private function allowedUsage(DocumentUsage $usage): bool
    {
        return in_array(
            $usage,
            [
                DocumentUsage::RestaurantMenu,
                DocumentUsage::RoomPlan,
                DocumentUsage::CommercialSupport,
            ],
            true,
        );
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

    private function changed(Restaurant $restaurant): void
    {
        $restaurant->fiche()->markSystemChanged();
        $this->state->flushAndIndex($restaurant);
    }

    private function request(): Request
    {
        return $this->requests->getCurrentRequest()
            ?? throw new \LogicException('Aucune requête HTTP active.');
    }
}
