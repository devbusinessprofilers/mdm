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
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;

/** @implements ProviderInterface<LieuDocumentResource> */
final readonly class ServiceEvenementielDocumentProvider implements
    ProviderInterface
{
    public function __construct(
        private ServiceEvenementielApiState $state,
        private RessourceLieuRepository $resources,
        private ExternalDocumentAccess $access,
        private LieuDocumentPresenter $presenter,
    ) {}

    /** @return LieuDocumentResource|list<LieuDocumentResource> */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): LieuDocumentResource|array {
        $a = $this->state->service((string) ($uriVariables["serviceId"] ?? ""));
        if (isset($uriVariables["documentId"])) {
            $d = $this->resources->find((string) $uriVariables["documentId"]);
            if (
                !($d instanceof RessourceLieu) ||
                !$this->isDocument($a->fiche(), $d) ||
                !$this->access->canRead($d)
            ) {
                throw new ApiProblemException(
                    404,
                    "not_found",
                    "Document introuvable.",
                );
            }

            return $this->presenter->resource(
                $d,
                $this->access->canDownloadOriginal($d),
            );
        }
        $result = [];
        foreach ($a->ressources() as $d) {
            if (
                $this->isDocument($a->fiche(), $d) &&
                $this->access->canRead($d)
            ) {
                $result[] = $this->presenter->resource($d);
            }
        }

        return $result;
    }

    private function isDocument(\App\Pim\Entity\Fiche $fiche, mixed $d): bool
    {
        return $d instanceof RessourceLieu &&
            $d->fiche() === $fiche &&
            NatureRessource::Document === $d->nature() &&
            DocumentUsage::CommercialSupport === $d->documentUsage();
    }
}
