<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Entity\CompletenessConfigurationRevision;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\CompletenessFieldConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CompletenessManager
{
    public function __construct(
        private CompletenessEntityResolver $resolver,
        private CompletenessFieldConfigurationRepository $configurations,
        private CompletenessCalculator $calculator,
        private CompletenessPhotoEligibilityResolver $photoEligibility,
        private CompletenessScoreWriter $writer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function calculate(Fiche $fiche, bool $flush = true): bool
    {
        return 0 < $this->calculateBatch([$fiche], $flush);
    }

    /** @param list<Fiche> $fiches */
    public function calculateBatch(array $fiches, bool $flush = true): int
    {
        if ([] === $fiches) { return 0; }
        $type = $fiches[0]->type();
        $configurations = $this->configurations->activeFor($type);
        if ([] === $configurations) {
            throw new \DomainException(sprintf('La configuration de complétude %s doit être synchronisée avant le calcul.', $type->value));
        }
        $revision = $this->entityManager->find(CompletenessConfigurationRevision::class, $type);
        if (!$revision instanceof CompletenessConfigurationRevision) {
            $revision = new CompletenessConfigurationRevision($type);
            $this->entityManager->persist($revision);
        }
        $entities = $this->resolver->resolveBatch($fiches);
        if (count($entities) !== count($fiches)) {
            throw new \RuntimeException(sprintf('Le chargeur de complétude a résolu %d fiche(s) sur %d.', count($entities), count($fiches)));
        }
        $eligiblePhotos = $this->photoEligibility->resolve($entities);
        $scoresByFiche = [];
        foreach ($fiches as $fiche) {
            if ($fiche->type() !== $type) { throw new \InvalidArgumentException('Un lot de complétude ne peut contenir qu’un seul type de fiche.'); }
            $entity = $entities[$fiche->idString()] ?? null;
            if (null === $entity) { continue; }
            $scoresByFiche[$fiche->idString()] = $this->calculator->calculate(
                $entity,
                $type,
                $configurations,
                ['PHOTO' => true === ($eligiblePhotos[$fiche->idString()] ?? false) ? true : null],
            );
        }
        $count = $this->writer->write($type, $scoresByFiche, $revision->revision());
        if ($count !== count($scoresByFiche)) {
            throw new \RuntimeException(sprintf('L’écriture de complétude a mis à jour %d fiche(s) sur %d.', $count, count($scoresByFiche)));
        }
        if ($flush) { $this->entityManager->flush(); }

        return $count;
    }
}
