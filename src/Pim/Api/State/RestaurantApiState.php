<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use App\Pim\Api\Exception\ApiProblemException;
use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Entity\Fiche;
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
        private FicheTranslationScheduler $translationScheduler,
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
        $this->translationScheduler->schedule($restaurant->fiche());
        $this->outbox->enqueue(
            new IndexFiche($restaurant->fiche()->idString()),
        );
        // Liaison Lieu modifiée : resynchroniser les fiches liées, sans
        // transition de workflow (flush sous suppression pour ces fiches).
        $liees = $restaurant->drainFichesLieesAResynchroniser();
        foreach ($liees as $ficheLiee) {
            $this->outbox->enqueue(new IndexFiche($ficheLiee->idString()));
        }
        Fiche::preserveWorkflowsDuring($liees, fn () => $this->entityManager->flush());
    }
}
