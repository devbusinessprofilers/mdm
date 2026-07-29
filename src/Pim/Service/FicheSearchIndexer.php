<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheSearchDocument;
use App\Pim\Entity\Lieu\Lieu;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FicheSearchIndexer
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function index(Fiche $fiche): void
    {
        $location = $fiche->localisation();
        $lieu = $this->entityManager->getRepository(Lieu::class)->findOneBy(['fiche' => $fiche]);
        $content = implode(' ', array_filter([
            null === $fiche->code() ? null : (string) $fiche->code(),
            $fiche->label(),
            $location?->ruePostale(), $location?->codePostal(), $location?->ville(), $location?->pays(),
            $lieu?->descGenerale(),
        ], static fn (?string $value): bool => null !== $value && '' !== $value));

        $repository = $this->entityManager->getRepository(FicheSearchDocument::class);
        $document = $repository->find($fiche);
        if (!$document instanceof FicheSearchDocument) {
            $document = new FicheSearchDocument($fiche, $content, $fiche->version());
            $this->entityManager->persist($document);
        } elseif ($document->sourceVersion() < $fiche->version()) {
            $document->reindex($content, $fiche->version());
        }
        $this->entityManager->flush();
    }
}
