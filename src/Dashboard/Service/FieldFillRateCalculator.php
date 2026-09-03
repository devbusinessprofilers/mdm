<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Pim\Completeness\CompletenessEntityResolver;
use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Completeness\CompletenessFieldDefinition;
use App\Pim\Completeness\CompletenessValueAccessor;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\FicheRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Taux de remplissage champ par champ, sur le catalogue de complétude.
 * Parcourt toutes les fiches par lots : trop coûteux pour le cycle 15 min du
 * dashboard, calculé une fois par jour dans un snapshot dédié (field_fill).
 */
final readonly class FieldFillRateCalculator
{
    private const int BATCH_SIZE = 100;
    private const int WORST_FIELDS = 20;

    public function __construct(
        private CompletenessFieldCatalog $catalog,
        private CompletenessValueAccessor $accessor,
        private CompletenessEntityResolver $resolver,
        private FicheRepository $fiches,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array{perType: array<string, array{fiches: int, fieldCount: int, worstFields: list<array{code: string, label: string, applicable: int, filled: int, rate: float}>}>} */
    public function compute(): array
    {
        $perType = [];
        foreach ($this->catalog->supportedTypes() as $type) {
            $perType[$type->value] = $this->computeType($type);
        }

        return ['perType' => $perType];
    }

    /** @return array{fiches: int, fieldCount: int, worstFields: list<array{code: string, label: string, applicable: int, filled: int, rate: float}>} */
    private function computeType(TypeFiche $type): array
    {
        $definitions = $this->catalog->definitions($type);
        $labels = [];
        $applicable = [];
        $filled = [];
        foreach ($definitions as $definition) {
            $labels[$definition->code] = $definition->label;
            $applicable[$definition->code] = 0;
            $filled[$definition->code] = 0;
        }
        $ficheCount = 0;
        $afterId = null;
        while ([] !== ($batch = $this->fiches->findBatchAfter($type, $afterId, self::BATCH_SIZE))) {
            $afterId = $batch[count($batch) - 1]->idString();
            foreach ($this->resolver->resolveBatch($batch) as $entity) {
                ++$ficheCount;
                foreach ($definitions as $definition) {
                    if (!$this->applicable($entity, $definition)) {
                        continue;
                    }
                    ++$applicable[$definition->code];
                    if ($this->isFilled($this->accessor->read($entity, $definition->path))) {
                        ++$filled[$definition->code];
                    }
                }
            }
            $this->entityManager->clear();
        }

        $rows = [];
        foreach ($labels as $code => $label) {
            if (0 === $applicable[$code]) {
                continue;
            }
            $rows[] = [
                'code' => $code,
                'label' => $label,
                'applicable' => $applicable[$code],
                'filled' => $filled[$code],
                'rate' => round(100 * $filled[$code] / $applicable[$code], 1),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$a['rate'], $a['label']] <=> [$b['rate'], $b['label']]);

        return ['fiches' => $ficheCount, 'fieldCount' => count($rows), 'worstFields' => array_slice($rows, 0, self::WORST_FIELDS)];
    }

    // Même sémantique d'applicabilité que CompletenessCalculator.
    private function applicable(object $entity, CompletenessFieldDefinition $definition): bool
    {
        if (null === $definition->conditionPath) {
            return true;
        }

        return $this->accessor->comparable($this->accessor->read($entity, $definition->conditionPath)) === $definition->conditionValue;
    }

    private function isFilled(mixed $value): bool
    {
        if ($value instanceof Collection) {
            $value = $value->toArray();
        } elseif ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->isFilled($item)) {
                    return true;
                }
            }

            return false;
        }
        if (null === $value) {
            return false;
        }

        return !is_string($value) || '' !== trim($value);
    }
}
