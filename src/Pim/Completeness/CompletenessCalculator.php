<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Entity\CompletenessFieldConfiguration;
use App\Pim\Enum\CompletenessFormula;
use App\Pim\Enum\TypeFiche;
use Doctrine\Common\Collections\Collection;

final readonly class CompletenessCalculator
{
    public function __construct(
        private CompletenessFieldCatalog $catalog,
        private CompletenessValueAccessor $accessor,
    ) {
    }

    /** @param list<CompletenessFieldConfiguration> $configurations
     *  @param array<string, mixed> $overrides Valeurs indexées par code de champ.
     */
    public function calculate(object $entity, TypeFiche $type, array $configurations, array $overrides = []): CompletenessScores
    {
        $totals = ['global' => [0.0, 0.0], 'marketplace' => [0.0, 0.0], 'thematicSites' => [0.0, 0.0], 'salesforce' => [0.0, 0.0], 'providerPortal' => [0.0, 0.0]];

        foreach ($configurations as $configuration) {
            if (!$configuration->active() || $configuration->weight() <= 0) {
                continue;
            }
            $definition = $this->catalog->find($type, $configuration->fieldCode());
            if (!$definition instanceof CompletenessFieldDefinition || !$this->applicable($entity, $definition)) {
                continue;
            }
            $target = $configuration->targetLengthOverride() ?? $definition->defaultTargetLength;
            if (CompletenessFormula::LengthRatio === $configuration->formula() && null === $target) {
                throw new \DomainException(sprintf('Aucune longueur cible n’est définie pour %s.', $configuration->fieldCode()));
            }
            $value = array_key_exists($configuration->fieldCode(), $overrides)
                ? $overrides[$configuration->fieldCode()]
                : $this->accessor->read($entity, $definition->path);
            $fraction = $this->fraction($value, $configuration->formula(), $target);
            $weight = $configuration->weight();
            $channels = ['global'];
            if ($configuration->marketplace()) { $channels[] = 'marketplace'; }
            if ($configuration->thematicSites()) { $channels[] = 'thematicSites'; }
            if ($configuration->salesforce()) { $channels[] = 'salesforce'; }
            if ($configuration->providerPortal()) { $channels[] = 'providerPortal'; }
            foreach ($channels as $channel) {
                $totals[$channel][0] += $weight * $fraction;
                $totals[$channel][1] += $weight;
            }
        }

        $score = static fn (array $total): int => 0.0 === $total[1] ? 0 : max(0, min(100, (int) round(100 * $total[0] / $total[1])));

        return new CompletenessScores($score($totals['global']), $score($totals['marketplace']), $score($totals['thematicSites']), $score($totals['salesforce']), $score($totals['providerPortal']));
    }

    private function applicable(object $entity, CompletenessFieldDefinition $definition): bool
    {
        if (null === $definition->conditionPath) {
            return true;
        }

        return $this->accessor->comparable($this->accessor->read($entity, $definition->conditionPath)) === $definition->conditionValue;
    }

    private function fraction(mixed $value, CompletenessFormula $formula, ?int $target): float
    {
        if ($value instanceof Collection) {
            $value = $value->toArray();
        } elseif ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }
        if (is_array($value)) {
            if ([] === $value) { return 0.0; }
            $fractions = array_map(fn (mixed $item): float => $this->fraction($item, $formula, $target), array_values($value));

            return array_sum($fractions) / count($fractions);
        }
        if (null === $value) { return 0.0; }
        if (CompletenessFormula::Presence === $formula) {
            return is_string($value) ? ('' === trim($value) ? 0.0 : 1.0) : 1.0;
        }
        if (!is_string($value) || null === $target) { return 0.0; }
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $value));

        return min(mb_strlen($normalized) / $target, 1.0);
    }
}
