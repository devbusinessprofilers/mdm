<?php

namespace App\Pim\Twig\Components\Form;

use App\Pim\Service\Map\MapPinFactory;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\Map\Icon\Icon;
use Symfony\UX\Map\Icon\IconType;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Live\ComponentWithMapTrait;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

#[AsLiveComponent]
final class LiveMap
{
    use DefaultActionTrait;
    use ComponentWithMapTrait;

    public const DEFAULT_ZOOM = 7;
    public const DEFAULT_TARGET_ZOOM = 15;
    public const DEFAULT_LATITUDE = 48.8566;
    public const DEFAULT_LONGITUDE = 2.3522;
    public const DEFAULT_TARGET_MARKER_ID = 'map-target-marker';

    /**
     * @var array{
     *     position: array{lat: float, lng: float},
     *     title: string|null,
     *     infoWindow: array<string, mixed>|null,
     *     icon: array{type: value-of<IconType>, width: positive-int, height: positive-int, ...}|null,
     *     extra: array,
     *     id: string|null
     * }[]
     */
    #[LiveProp(writable: true)]
    public array $markers = [];
    /** @var float[] */
    public array $center = [self::DEFAULT_LATITUDE, self::DEFAULT_LONGITUDE];
    public int $zoom = self::DEFAULT_ZOOM;

    protected function instantiateMap(): Map
    {
        $map = (new Map())
            ->center($this->buildCenterPoint())
            ->zoom($this->zoom)
        ;

        foreach ($this->markers as $marker) {
            $point = $this->buildMainPoint($marker['position']['lat'] ?? null, $marker['position']['lng'] ?? null);
            if (!$point) {
                continue;
            }

            $map->addMarker(new Marker(
                position: $point,
                id: $marker['id'] ?? null,
                title: $marker['title'] ?? null,
                infoWindow: new InfoWindow($marker['infoWindow'] ?? null),
                icon: Icon::fromArray($marker['icon']) ?? null,
                extra: $marker['extra'] ?? [],
            ));
        }

        return $map;
    }

    #[LiveAction]
    public function onUpdate(
        #[LiveArg]
        ?float $latitude = null,
        #[LiveArg]
        ?float $longitude = null,
    ): void {
        $point = $this->buildMainPoint($latitude, $longitude);

        $map = $this->getMap()
            ->center($this->buildCenterPoint($latitude, $longitude))
            ->zoom($point ? self::DEFAULT_TARGET_ZOOM : self::DEFAULT_ZOOM)
        ;

        $targetMarker = $this->getTargetMarker();
        $map->removeMarker(self::DEFAULT_TARGET_MARKER_ID);

        if ($point) {
            $map->addMarker($this->createDefaultTargetMarker($point, $targetMarker));
        }
    }

    private function buildMainPoint(?float $latitude = null, ?float $longitude = null): ?Point
    {
        if (!$latitude || !$longitude) {
            return null;
        }

        return new Point($latitude, $longitude);
    }

    private function buildCenterPoint(?float $latitude = null, ?float $longitude = null): Point
    {
        return new Point($latitude ?? $this->center[0] ?? self::DEFAULT_LATITUDE, $longitude ?? $this->center[1] ?? self::DEFAULT_LONGITUDE);
    }

    // Get the template marker if set at the start
    private function getTargetMarker(): ?Marker
    {
        $markerData = array_find($this->markers, fn (array $marker) => self::DEFAULT_TARGET_MARKER_ID === $marker['id']);
        if (!$markerData) {
            return null;
        }

        return Marker::fromArray($markerData);
    }

    private function createDefaultTargetMarker(Point $point, ?Marker $fromMarker = null): Marker
    {
        return new Marker(
            position: $point,
            id: self::DEFAULT_TARGET_MARKER_ID,
            title: $fromMarker ? $fromMarker->title : null,
            infoWindow: $fromMarker ? $fromMarker->infoWindow : null,
            icon: $fromMarker ? $fromMarker->icon : MapPinFactory::createHomePin(),
            extra: $fromMarker ? $fromMarker->extra : [],
        );
    }
}
