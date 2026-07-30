<?php

namespace App\Pim\Controller\ProviderPortal\Api;

use App\Pim\Enum\ProviderPortal\Localisation\NearPlaceTypeEnum;
use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Service\Localisation\AutocompletePlaceClientInterface;
use App\Pim\Service\Localisation\ComputeRouteClientInterface;
use App\Pim\Service\Localisation\NearbyPlaceClientInterface;
use App\Pim\Service\Localisation\PlaceDetailsClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LocalisationController extends AbstractController
{
    public function __construct(
        private readonly AutocompletePlaceClientInterface $autocompletePlaceClient,
        private readonly NearbyPlaceClientInterface $nearbyPlaceClient,
        private readonly PlaceDetailsClientInterface $placeDetailsClient,
        private readonly ComputeRouteClientInterface $computeRouteClient,
    ) {
    }

    #[Route('/portal/api/place/autocomplete', name: 'provider_portal_api_place_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        // @todo: block request if country no set?
        $country = $request->query->get('country', 'FR');
        $input = $request->query->get('input', null);
        $type = $request->query->get('type', null);
        if (!$input || !$type) {
            return new JsonResponse(['error' => 'Input and Type parameters are required'], 422);
        }

        $suggestions = match ($type) {
            'street' => $this->autocompletePlaceClient->autocompleteStreet($input, $country),
            'zipCode' => $this->autocompletePlaceClient->autocompleteZipCode($input, $country),
            'city' => $this->autocompletePlaceClient->autocompleteCity($input, $country),
            'department' => $this->autocompletePlaceClient->autocompleteDepartment($input, $country),
            'district' => $this->autocompletePlaceClient->autocompleteDistrict($input, $country),
            'area' => $this->autocompletePlaceClient->autocompleteArea($input, $country),
            default => throw new \Exception('Invalid type'),
        };

        return new JsonResponse($suggestions);
    }

    #[Route('/portal/api/place/near-choices', name: 'provider_portal_api_place_near', methods: ['GET'])]
    public function nearChoices(Request $request): JsonResponse
    {
        $latitude = $request->query->get('latitude', null);
        $longitude = $request->query->get('longitude', null);
        if (!$latitude || !$longitude) {
            return new JsonResponse([]);
        }

        $type = $request->query->get('type', null);
        if (!$type) {
            return new JsonResponse(['error' => 'Type parameter is required'], 422);
        }

        $coordinates = new CoordinatesDTO($latitude, $longitude);

        $places = match ($type) {
            NearPlaceTypeEnum::TRAIN_STATION->value => $this->nearbyPlaceClient->nearbyTrainStations($coordinates),
            NearPlaceTypeEnum::SUBWAY_STATION->value => $this->nearbyPlaceClient->nearbySubwayStations($coordinates),
            NearPlaceTypeEnum::LIGHT_RAIL_STATION->value => $this->nearbyPlaceClient->nearbyLightRailStations($coordinates),
            NearPlaceTypeEnum::AIRPORT->value => $this->nearbyPlaceClient->nearbyAirports($coordinates),
            NearPlaceTypeEnum::CITY->value => $this->nearbyPlaceClient->nearbyCities($coordinates),
            NearPlaceTypeEnum::PARKING->value => $this->nearbyPlaceClient->nearbyParkings($coordinates),
            NearPlaceTypeEnum::POINT_OF_INTEREST->value => $this->nearbyPlaceClient->nearbyPointsOfInterest($coordinates),
            default => throw new \Exception('Invalid type'),
        };

        $choices = [];
        $responses = [];
        $labels = [];
        foreach ($places as $place) {
            $label = $place->displayName;
            if (
                !$label
                || in_array($label, $choices)
                || !$position = $place->position
            ) {
                continue;
            }

            $labels[$place->id] = $label;
            $responses[$place->id] = $this->computeRouteClient->routes($position, $coordinates, async: true);
        }

        $results = $this->computeRouteClient->resolve($responses);
        foreach ($results as $id => $routes) {
            $firstRoute = $routes[0] ?? null;
            if (
                !$firstRoute
                || (!$distance = $firstRoute->distance)
                || (!$duration = $firstRoute->duration)
            ) {
                continue;
            }

            $label = \sprintf('%s, (%d km, %d min)', $labels[$id] ?? '', $distance, $duration);
            $choices[] = [
                'label' => $label,
                'value' => $id,
            ];
        }

        return new JsonResponse($choices);
    }

    #[Route('/portal/api/place/details', name: 'provider_portal_api_place_details', methods: ['GET'])]
    public function place(Request $request): JsonResponse
    {
        $id = $request->query->get('id', null);
        if (!$id) {
            return new JsonResponse(['error' => 'Place id is required'], 422);
        }

        $address = $this->placeDetailsClient->getAddress($id);

        return new JsonResponse($address);
    }
}
