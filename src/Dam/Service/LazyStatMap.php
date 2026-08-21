<?php

declare(strict_types=1);

namespace App\Dam\Service;

/**
 * Compteurs à calcul paresseux : chaque valeur n'est comptée en base qu'à la
 * première lecture, puis mémorisée pour la requête. Les gabarits des médias ne
 * consultent que deux à quatre compteurs selon l'onglet servi : matérialiser
 * les onze filtres à chaque affichage coûtait plusieurs centaines de
 * millisecondes de COUNT jamais rendus.
 *
 * @implements \ArrayAccess<string, int>
 */
final class LazyStatMap implements \ArrayAccess
{
    /** @var array<string, int> */
    private array $resolus = [];

    /** @param array<string, \Closure(): int> $calculs */
    public function __construct(private readonly array $calculs)
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->calculs[$offset]);
    }

    public function offsetGet(mixed $offset): int
    {
        return $this->resolus[$offset] ??= ($this->calculs[$offset])();
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Les compteurs du tableau de bord sont en lecture seule.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Les compteurs du tableau de bord sont en lecture seule.');
    }
}
