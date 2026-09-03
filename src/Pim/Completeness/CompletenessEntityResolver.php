<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Entity\Fiche;
use App\Pim\Repository\CompletenessEntityRepository;

final readonly class CompletenessEntityResolver
{
    public function __construct(private CompletenessEntityRepository $entities)
    {
    }

    public function resolve(Fiche $fiche): ?object
    {
        return $this->resolveBatch([$fiche])[$fiche->idString()] ?? null;
    }

    /**
     * Charge un lot avec un nombre de requêtes indépendant du nombre de fiches.
     *
     * @param list<Fiche> $fiches
     *
     * @return array<string, object>
     */
    public function resolveBatch(array $fiches): array
    {
        if ([] === $fiches) {
            return [];
        }
        $type = $fiches[0]->type();
        foreach ($fiches as $fiche) {
            if ($fiche->type() !== $type) {
                throw new \InvalidArgumentException('Un lot de complétude ne peut contenir qu’un seul type de fiche.');
            }
        }
        $resolved = [];
        foreach ($this->entities->findAggregates($type, $fiches) as $entity) {
            if (method_exists($entity, 'fiche')) {
                $resolved[$entity->fiche()->idString()] = $entity;
            }
        }

        return $resolved;
    }
}
