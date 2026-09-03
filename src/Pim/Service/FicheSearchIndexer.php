<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheSearchDocument;
use App\Pim\Message\CalculateFicheCompleteness;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\FicheSearchDocumentRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Repository\ServiceEvenementielRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FicheSearchIndexer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LieuRepository $lieux,
        private ActiviteRepository $activites,
        private ServiceEvenementielRepository $services,
        private RestaurantRepository $restaurants,
        private FicheSearchDocumentRepository $searchDocuments,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    public function index(Fiche $fiche): void
    {
        $lieu = $this->lieux->findOneByFicheWithLocalisation($fiche);
        $activite = $this->activites->findOneByFiche($fiche);
        $service = $this->services->findOneByFiche($fiche);
        $restaurant = $this->restaurants->findOneByFiche($fiche);
        $location = $fiche->localisation();
        $activeFixedLocation = (null === $activite || 'fixe' === $activite->modeIntervention()?->value)
            && (null === $service || 'fixe' === $service->modeIntervention()?->value);
        $content = implode(' ', array_filter([
            (string) $fiche->code(),
            $fiche->label(),
            $activeFixedLocation ? $location?->ruePostale() : null, $activeFixedLocation ? $location?->codePostal() : null, $activeFixedLocation ? $location?->ville() : null, $activeFixedLocation ? $location?->pays() : null,
            $lieu?->descGenerale(),
            $activite?->descriptionGenerale(), $activite?->comprendPrestation(), null === $activite ? null : implode(' ', $activite->thematiques()),
            null !== $activite && !$activeFixedLocation ? implode(' ', [...$activite->paysMobiles(), ...$activite->regionsMobiles(), ...$activite->departementsMobiles()]) : null,
            $service?->descriptionGenerale(),
            null === $service ? null : implode(' ', $service->prestations()),
            null !== $service && !$activeFixedLocation ? implode(' ', [...$service->paysMobiles(), ...$service->regionsMobiles(), ...$service->departementsMobiles()]) : null,
            $restaurant?->descriptionGenerale(),
            null === $restaurant ? null : implode(' ', [
                ...$restaurant->typesRestaurant(),
                ...$restaurant->typesCuisine(),
                ...$restaurant->specificitesAlimentaires(),
                ...$restaurant->typesEvenement(),
                ...$restaurant->services(),
                ...$restaurant->equipements(),
                ...$restaurant->engagementsRse(),
                ...$restaurant->atouts(),
            ]),
        ], static fn (?string $value): bool => null !== $value && '' !== $value));

        $document = $this->searchDocuments->findForFiche($fiche);
        if (!$document instanceof FicheSearchDocument) {
            $document = new FicheSearchDocument($fiche, $content, $fiche->version());
            $this->entityManager->persist($document);
        } elseif ($document->sourceVersion() < $fiche->version()) {
            $document->reindex($content, $fiche->version());
        }
        $this->outbox->enqueue(new CalculateFicheCompleteness($fiche->idString()));
        $this->entityManager->flush();
    }
}
