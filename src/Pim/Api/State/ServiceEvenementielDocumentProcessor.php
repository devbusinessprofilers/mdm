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
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProcessorInterface<mixed,LieuDocumentResource|void> */
final readonly class ServiceEvenementielDocumentProcessor implements
    ProcessorInterface
{
    public function __construct(
        private ServiceEvenementielApiState $state,
        private RessourceLieuRepository $resources,
        private MediaAssetRepository $assets,
        private FicheDocumentUploader $uploader,
        private LieuDocumentPresenter $presenter,
        private ExternalDocumentAccess $access,
        private EntityManagerInterface $em,
        private OutboxPublisherInterface $outbox,
        private RequestStack $requests,
        private Security $security,
    ) {}

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ?LieuDocumentResource {
        $a = $this->state->service((string) ($uriVariables["serviceId"] ?? ""));
        $this->state->assertVersion($a);
        if (!($operation instanceof HttpOperation)) {
            throw new \LogicException("Opération HTTP requise.");
        }
        $method = $operation->getMethod();
        $template = $operation->getUriTemplate() ?? "";

        return $a
            ->fiche()
            ->preserveWorkflowDuring(function () use (
                $data,
                $a,
                $method,
                $template,
                $uriVariables,
            ) {
                if (
                    "POST" === $method &&
                    str_ends_with($template, "/documents")
                ) {
                    return $this->create($a);
                }
                $d = $this->document(
                    $a,
                    (string) ($uriVariables["documentId"] ?? ""),
                );
                if ("PATCH" === $method) {
                    if (!($data instanceof DocumentPatchInput)) {
                        throw new ApiProblemException(
                            400,
                            "invalid_payload",
                            "Corps JSON invalide.",
                        );
                    }

                    return $this->metadata($a, $d, $data);
                }
                if (
                    "POST" === $method &&
                    str_ends_with($template, "/fichier")
                ) {
                    return $this->replace($a, $d);
                }
                if (
                    "POST" === $method &&
                    str_ends_with($template, "/publication")
                ) {
                    if (!($data instanceof DocumentPublicationInput)) {
                        throw new ApiProblemException(
                            400,
                            "invalid_payload",
                            "Corps JSON invalide.",
                        );
                    }

                    return $this->publication($a, $d, $data->published);
                }
                if ("DELETE" === $method) {
                    $this->access->requireWrite($d);
                    $this->unpublish($d);
                    $this->outbox->enqueue(new DeleteMedia($d->damAssetId()));
                    $a->removeRessource($d);
                    $this->em->remove($d);
                    $this->changed($a);

                    return null;
                }
                throw new \LogicException("Opération documentaire inconnue.");
            });
    }

    private function create(ServiceEvenementiel $a): LieuDocumentResource
    {
        $this->access->requireCreate(
            DocumentUsage::CommercialSupport->access(),
        );
        $request = $this->requests->getCurrentRequest();
        if (null === $request) {
            throw new \LogicException("Aucune requete HTTP active.");
        }
        $file = $request->files->get("document");
        if (!($file instanceof UploadedFile)) {
            throw new ApiProblemException(
                422,
                "document_required",
                "Le champ multipart document est obligatoire.",
            );
        }
        $asset = $this->upload($file, $a);
        try {
            $d = new RessourceLieu();
            $d->configureDocument(DocumentUsage::CommercialSupport);
            $d->changeDamAssetId($asset->id());
            $d->changeLegende($request->request->getString("title"));
            $d->changeSource($request->request->getString("source"));
            if ($request->request->getBoolean("rightsGranted")) {
                $d->grantRights(
                    $this->security->getUser()?->getUserIdentifier() ??
                        "external-site",
                );
            }
            $a->addRessource($d);
            $this->em->persist($asset);
            $this->changed($a);
        } catch (\Throwable $e) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
            throw $e;
        }

        return $this->presenter->resource($d);
    }

    private function metadata(
        ServiceEvenementiel $a,
        RessourceLieu $d,
        DocumentPatchInput $input,
    ): LieuDocumentResource {
        $this->access->requireWrite($d);
        $payload = json_decode((string) $this->request()->getContent(), true);
        $payload = is_array($payload) ? $payload : [];
        if (
            array_key_exists("usage", $payload) &&
            DocumentUsage::CommercialSupport->value !== $input->usage
        ) {
            throw new ApiProblemException(
                422,
                "invalid_document_usage",
                "Un Service accepte uniquement les supports commerciaux.",
            );
        }
        if (
            array_key_exists("salleId", $payload) &&
            null !== $input->salleId &&
            "" !== $input->salleId
        ) {
            throw new ApiProblemException(
                422,
                "foreign_room",
                "Un support Service ne peut pas être rattaché à une salle.",
            );
        }
        if (array_key_exists("title", $payload)) {
            $d->changeLegende($input->title);
        }
        if (array_key_exists("source", $payload)) {
            $d->changeSource($input->source);
        }
        if (array_key_exists("rightsGranted", $payload)) {
            if (true === $input->rightsGranted) {
                $d->grantRights(
                    $this->security->getUser()?->getUserIdentifier() ??
                        "external-site",
                );
            } else {
                $d->revokeRights();
                $this->unpublish($d);
            }
        }
        $this->changed($a);

        return $this->presenter->resource($d);
    }

    private function replace(
        ServiceEvenementiel $a,
        RessourceLieu $d,
    ): LieuDocumentResource {
        $this->access->requireWrite($d);
        $file = $this->request()->files->get("document");
        if (!($file instanceof UploadedFile)) {
            throw new ApiProblemException(
                422,
                "document_required",
                "Le champ multipart document est obligatoire.",
            );
        }
        $asset = $this->upload($file, $a);
        $old = $d->damAssetId();
        try {
            $this->unpublish($d);
            $this->em->persist($asset);
            $d->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new DeleteMedia($old));
            $this->changed($a);
        } catch (\Throwable $e) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
            throw $e;
        }

        return $this->presenter->resource($d);
    }

    private function publication(
        ServiceEvenementiel $a,
        RessourceLieu $d,
        bool $published,
    ): LieuDocumentResource {
        $this->access->requirePublish();
        if ($published) {
            $asset = $this->assets->find($d->damAssetId());
            if (
                !($asset instanceof MediaAsset) ||
                MediaKind::Document !== $asset->kind() ||
                MediaStatus::Processed !== $asset->status()
            ) {
                throw new ApiProblemException(
                    422,
                    "invalid_document",
                    "Le fichier DAM est absent ou invalide.",
                );
            }
            try {
                $d->requestPublication();
            } catch (\DomainException $e) {
                throw new ApiProblemException(
                    422,
                    "publication_refused",
                    $e->getMessage(),
                );
            }
            $this->outbox->enqueue(new PublishDocument($d->id()));
        } else {
            $this->unpublish($d);
        }
        $this->changed($a);

        return $this->presenter->resource($d);
    }

    private function document(ServiceEvenementiel $a, string $id): RessourceLieu
    {
        $d = $this->resources->find($id);
        if (
            !($d instanceof RessourceLieu) ||
            $d->fiche() !== $a->fiche() ||
            NatureRessource::Document !== $d->nature() ||
            DocumentUsage::CommercialSupport !== $d->documentUsage()
        ) {
            throw new ApiProblemException(
                404,
                "not_found",
                "Document introuvable.",
            );
        }

        return $d;
    }

    private function upload(
        UploadedFile $file,
        ServiceEvenementiel $a,
    ): MediaAsset {
        try {
            return $this->uploader->upload(
                $file,
                $a->fiche(),
                DocumentUsage::CommercialSupport,
            );
        } catch (DocumentUploadException $e) {
            throw new ApiProblemException(
                $e->httpStatus,
                "invalid_document_file",
                $e->getMessage(),
            );
        }
    }

    private function unpublish(RessourceLieu $d): void
    {
        $key = $d->requestUnpublication();
        if (null !== $key) {
            $this->outbox->enqueue(new UnpublishDocument($d->id(), $key));
        }
    }

    private function changed(ServiceEvenementiel $a): void
    {
        $a->fiche()->markSystemChanged();
        $this->state->flushAndIndex($a);
    }

    private function request(): \Symfony\Component\HttpFoundation\Request
    {
        return $this->requests->getCurrentRequest() ??
            throw new \LogicException("Aucune requête HTTP active.");
    }
}
