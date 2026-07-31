<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dam\Service\LieuDocumentPresenter;
use App\Pim\Api\Dto\LieuDocumentResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalDocumentAccess;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;
use Symfony\Component\HttpFoundation\Response;

/** @implements ProviderInterface<LieuDocumentResource> */
final readonly class LieuDocumentProvider implements ProviderInterface
{
    public function __construct(
        private LieuApiState $state,
        private RessourceLieuRepository $resources,
        private ExternalDocumentAccess $access,
        private LieuDocumentPresenter $presenter,
    ) {
    }

    /** @return LieuDocumentResource|list<LieuDocumentResource> */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): LieuDocumentResource|array {
        $lieu = $this->state->lieu((string) ($uriVariables['lieuId'] ?? ''));
        if (isset($uriVariables['documentId'])) {
            $document = $this->resources->find(
                (string) $uriVariables['documentId'],
            );
            if (
                !($document instanceof RessourceLieu)
                || $document->lieu() !== $lieu
                || NatureRessource::Document !== $document->nature()
                || !$this->access->canRead($document)
            ) {
                throw new ApiProblemException(Response::HTTP_NOT_FOUND, 'not_found', 'Document introuvable.');
            }

            return $this->presenter->resource(
                $document,
                $this->access->canDownloadOriginal($document),
            );
        }
        $result = [];
        foreach ($lieu->ressources() as $resource) {
            if (
                NatureRessource::Document === $resource->nature()
                && $this->access->canRead($resource)
            ) {
                $result[] = $this->presenter->resource($resource);
            }
        }

        return $result;
    }
}
