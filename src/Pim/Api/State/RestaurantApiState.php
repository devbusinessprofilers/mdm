<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\RestaurantRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final readonly class RestaurantApiState
{
    public function __construct(
        private RestaurantRepository $restaurants,
        private RequestStack $requests,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    public function restaurant(string $id): Restaurant
    {
        $restaurant = $this->restaurants->find($id);
        if (!$restaurant instanceof Restaurant) {
            throw new ApiProblemException(
                Response::HTTP_NOT_FOUND,
                'not_found',
                'Restaurant introuvable.',
            );
        }

        return $restaurant;
    }

    public function assertVersion(Restaurant $restaurant): void
    {
        $header = trim(
            (string) $this->requests
                ->getCurrentRequest()
                ?->headers->get('If-Match'),
            " \t\n\r\x00\v\"",
        );
        if ('' === $header) {
            throw new ApiProblemException(
                428,
                'precondition_required',
                "L’en-tête If-Match est obligatoire.",
            );
        }
        if (
            !ctype_digit($header)
            || (int) $header !== $restaurant->fiche()->version()
        ) {
            throw new ApiProblemException(
                409,
                'version_conflict',
                'La fiche a été modifiée depuis sa lecture.',
                ['currentVersion' => $restaurant->fiche()->version()],
            );
        }
    }

    public function flushAndIndex(Restaurant $restaurant): void
    {
        $this->outbox->enqueue(
            new IndexFiche($restaurant->fiche()->idString()),
        );
        $this->entityManager->flush();
    }
}
