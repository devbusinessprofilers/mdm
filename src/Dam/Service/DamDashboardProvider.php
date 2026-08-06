<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaDuplicateAlert;
use App\Dam\Enum\MediaKind;
use App\Dam\Enum\RightsValidityStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Repository\MediaDuplicateAlertRepository;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\RessourceLieuRepository;

final readonly class DamDashboardProvider
{
    public const FILTER_DUPLICATES = 'duplicates';
    public const FILTER_RIGHTS_EXPIRING = 'rights_expiring';
    public const FILTER_RIGHTS_EXPIRED = 'rights_expired';
    public const FILTER_RIGHTS_MISSING = 'rights_missing';
    public const FILTER_FAILED = 'failed';
    public const FILTER_IMAGES = 'images';
    public const FILTER_DOCUMENTS = 'documents';
    private const FILTERS = [self::FILTER_DUPLICATES, self::FILTER_RIGHTS_MISSING, self::FILTER_RIGHTS_EXPIRING, self::FILTER_RIGHTS_EXPIRED, self::FILTER_FAILED, self::FILTER_IMAGES, self::FILTER_DOCUMENTS];
    private const PAGE_SIZE = 50;

    public function __construct(
        private MediaDuplicateAlertRepository $alerts,
        private MediaAssetRepository $assets,
        private RessourceLieuRepository $resources,
        private PublicMediaUrlGenerator $publicUrls,
        private DamFicheLinkResolver $links,
        private DamDashboardFormFactory $forms,
    ) {}

    /** @return array<string, mixed> */
    public function page(string $requestedFilter, ?string $requestedType, int $page): array
    {
        $filter = in_array($requestedFilter, self::FILTERS, true) ? $requestedFilter : self::FILTER_DUPLICATES;
        $type = null === $requestedType ? null : TypeFiche::tryFrom($requestedType);
        if (TypeFiche::Traiteur === $type) {
            $type = null;
        }
        if (in_array($filter, [self::FILTER_FAILED, self::FILTER_IMAGES, self::FILTER_DOCUMENTS], true)) {
            // MediaAsset ne porte pas le type de fiche : ne pas afficher un total
            // global comme s'il était filtré.
            $type = null;
        }
        $page = max(1, $page);
        $stats = [
            self::FILTER_DUPLICATES => $this->alerts->countPending($type?->value),
            self::FILTER_RIGHTS_MISSING => $this->resources->countByRightsStatus(RightsValidityStatus::NotGranted, $type),
            self::FILTER_RIGHTS_EXPIRING => $this->resources->countByRightsStatus(RightsValidityStatus::Expiring, $type),
            self::FILTER_RIGHTS_EXPIRED => $this->resources->countByRightsStatus(RightsValidityStatus::Expired, $type),
            self::FILTER_FAILED => $this->assets->countFailed(),
            self::FILTER_IMAGES => $this->assets->countActiveByKind(MediaKind::Image),
            self::FILTER_DOCUMENTS => $this->assets->countActiveByKind(MediaKind::Document),
        ];
        [$items, $total] = match ($filter) {
            self::FILTER_DUPLICATES => $this->duplicates($type, $page),
            self::FILTER_RIGHTS_MISSING => $this->rights(RightsValidityStatus::NotGranted, $type, $page),
            self::FILTER_RIGHTS_EXPIRING => $this->rights(RightsValidityStatus::Expiring, $type, $page),
            self::FILTER_RIGHTS_EXPIRED => $this->rights(RightsValidityStatus::Expired, $type, $page),
            self::FILTER_FAILED => $this->failed($type, $page),
            self::FILTER_IMAGES => $this->media(MediaKind::Image, $page),
            self::FILTER_DOCUMENTS => $this->media(MediaKind::Document, $page),
        };
        $items = $this->forms->addActions($items, $filter, $type?->value, $page);

        return [
            'filter' => $filter,
            'type' => $type?->value,
            'types' => [TypeFiche::Lieu, TypeFiche::Activite, TypeFiche::Restaurant, TypeFiche::ServiceEvenementiel],
            'stats' => $stats,
            'items' => $items,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'total' => $total,
        ];
    }

    /** @return array{list<array<string, mixed>>, int} */
    private function duplicates(?TypeFiche $type, int $page): array
    {
        $total = $this->alerts->countPending($type?->value);
        $items = [];
        foreach ($this->alerts->findPendingPage($type?->value, $page, self::PAGE_SIZE) as $alert) {
            $resource = $this->resources->find($alert->resourceId());
            if (!$resource instanceof RessourceLieu || null === $resource->fiche()) {
                continue;
            }
            $referenceResource = $this->resources->findOneByMediaId($alert->duplicateOf()->id());
            $items[] = $this->resourceItem($resource, $alert->media()) + [
                'alert' => $alert,
                'reference' => $alert->duplicateOf(),
                'reference_thumbnail' => $this->thumbnail($alert->duplicateOf()),
                'reference_fiche' => $referenceResource instanceof RessourceLieu && null !== $referenceResource->fiche()
                    ? $this->ficheItem($referenceResource)
                    : null,
            ];
        }

        return [$items, $total];
    }

    /** @return array{list<array<string, mixed>>, int} */
    private function rights(RightsValidityStatus $status, ?TypeFiche $type, int $page): array
    {
        $total = $this->resources->countByRightsStatus($status, $type);
        $resources = $this->resources->findRightsPage($status, $type, $page, self::PAGE_SIZE);
        $assets = $this->assetMap($resources);
        $items = [];
        foreach ($resources as $resource) {
            $items[] = $this->resourceItem($resource, $assets[$resource->damAssetId()] ?? null);
        }

        return [$items, $total];
    }

    /** @return array{list<array<string, mixed>>, int} */
    private function failed(?TypeFiche $type, int $page): array
    {
        $total = $this->assets->countFailed();
        $items = [];
        foreach ($this->assets->findFailedPage($page, self::PAGE_SIZE) as $asset) {
            $resource = $this->resources->findOneByMediaId($asset->id());
            if (null === $resource || null === $resource->fiche() || (null !== $type && $resource->fiche()->type() !== $type)) {
                continue;
            }
            $items[] = $this->resourceItem($resource, $asset);
        }

        return [$items, $total];
    }

    /** @return array{list<array<string, mixed>>, int} */
    private function media(MediaKind $kind, int $page): array
    {
        $total = $this->assets->countActiveByKind($kind);
        $assets = $this->assets->findActiveByKindPage($kind, $page, self::PAGE_SIZE);
        $resourcesByMediaId = [];
        foreach ($this->resources->findByMediaIds(array_map(static fn (MediaAsset $asset): string => $asset->id(), $assets)) as $resource) {
            $resourcesByMediaId[$resource->damAssetId()] = $resource;
        }
        $items = [];
        foreach ($assets as $asset) {
            $resource = $resourcesByMediaId[$asset->id()] ?? null;
            $items[] = [
                'resource' => $resource,
                'asset' => $asset,
                'thumbnail' => $this->thumbnail($asset),
            ] + (null !== $resource && null !== $resource->fiche()
                ? $this->ficheItem($resource)
                : ['fiche_label' => null, 'fiche_type' => null, 'fiche_url' => null]);
        }

        return [$items, $total];
    }

    /** @return array<string, mixed> */
    private function resourceItem(RessourceLieu $resource, ?MediaAsset $asset): array
    {
        return [
            'resource' => $resource,
            'asset' => $asset,
            'thumbnail' => null === $asset ? null : $this->thumbnail($asset),
        ] + $this->ficheItem($resource);
    }

    /** @return array{fiche_label: string, fiche_type: string, fiche_url: ?string} */
    private function ficheItem(RessourceLieu $resource): array
    {
        $fiche = $resource->fiche() ?? throw new \LogicException('Ressource DAM sans fiche.');

        return [
            'fiche_label' => $fiche->label() ?: sprintf('Fiche %d', $fiche->code()),
            'fiche_type' => $fiche->type()->value,
            'fiche_url' => $this->links->editUrl($fiche),
        ];
    }

    /** @param list<RessourceLieu> $resources
     *  @return array<string, MediaAsset>
     */
    private function assetMap(array $resources): array
    {
        $map = [];
        foreach ($this->assets->findByStringIds(array_map(static fn (RessourceLieu $resource): string => $resource->damAssetId(), $resources)) as $asset) {
            $map[$asset->id()] = $asset;
        }

        return $map;
    }

    private function thumbnail(MediaAsset $asset): ?string
    {
        $rendition = $asset->rendition('small');

        return null === $rendition ? null : $this->publicUrls->url($rendition->storageKey());
    }
}
