<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dam\Enum\DocumentUsage;
use App\Dam\Service\LieuDocumentPresenter;
use App\Pim\Api\Dto\LieuDocumentResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalDocumentAccess;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;

/** @implements ProviderInterface<LieuDocumentResource> */
final readonly class RestaurantDocumentProvider implements ProviderInterface
{
    public function __construct(
        private RestaurantApiState $state,
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
        $restaurant = $this->state->restaurant(
            (string) ($uriVariables['restaurantId'] ?? ''),
        );
        if (isset($uriVariables['documentId'])) {
            $document = $this->resources->find(
                (string) $uriVariables['documentId'],
            );
            if (
                !$document instanceof RessourceLieu
                || !$this->isDocument($restaurant->fiche(), $document)
                || !$this->access->canRead($document)
            ) {
                throw new ApiProblemException(
                    404,
                    'not_found',
                    'Document introuvable.',
                );
            }

            return $this->presenter->resource(
                $document,
                $this->access->canDownloadOriginal($document),
            );
        }

        $result = [];
        foreach ($restaurant->ressources() as $document) {
            if (
                $this->isDocument($restaurant->fiche(), $document)
                && $this->access->canRead($document)
            ) {
                $result[] = $this->presenter->resource($document);
            }
        }

        return $result;
    }

    private function isDocument(Fiche $fiche, mixed $document): bool
    {
        return $document instanceof RessourceLieu
            && $document->fiche() === $fiche
            && NatureRessource::Document === $document->nature()
            && in_array(
                $document->documentUsage(),
                [
                    DocumentUsage::RestaurantMenu,
                    DocumentUsage::RoomPlan,
                    DocumentUsage::CommercialSupport,
                ],
                true,
            );
    }
}
