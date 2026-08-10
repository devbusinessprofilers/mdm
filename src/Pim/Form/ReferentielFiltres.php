<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;

/**
 * Filtres de la liste du référentiel. Sémantique des facettes : union à
 * l'intérieur d'un groupe, intersection entre les groupes.
 */
final class ReferentielFiltres
{
    public ?string $q = null;

    /** @var list<TypeFiche> */
    public array $gammes = [];

    /** @var list<StatutFiche> */
    public array $statuts = [];

    /** @var list<string> Paliers : complet, publiable, insuffisant. */
    public array $completudes = [];

    /** @var list<string> Codes pays ISO 2. */
    public array $pays = [];

    /** @var list<string> Paliers de diffusion : c20, c5, c1, c0. */
    public array $canaux = [];

    /** @var list<string> Paliers : creees_semaine, modifiees_jour, sans_modif_6m. */
    public array $dates = [];

    /** @var list<string> Identifiants des contributeurs internes assignés. */
    public array $contributeurs = [];

    /** Fiches portant des valeurs suggérées par l'IA en attente d'arbitrage. */
    public bool $ia = false;

    /** Fiches dont le contact est le contact de repli (service référencement). */
    public bool $repli = false;

    /** Fiches adhérentes Business Premium. */
    public bool $premium = false;

    /** @var list<int> Identifiants de valeurs de classification (catégories, thématiques…). */
    public array $valeurs = [];

    public function actifs(): int
    {
        return (null !== $this->q && '' !== trim($this->q) ? 1 : 0)
            + count($this->gammes) + count($this->statuts) + count($this->completudes)
            + count($this->pays) + count($this->canaux) + count($this->dates) + count($this->contributeurs)
            + ($this->ia ? 1 : 0) + ($this->repli ? 1 : 0) + ($this->premium ? 1 : 0) + count($this->valeurs);
    }

    public function gammeUnique(): ?TypeFiche
    {
        return 1 === count($this->gammes) ? $this->gammes[0] : null;
    }

    /** Représentation sérialisable, réutilisée par les vues enregistrées et les URL. */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'q' => null !== $this->q && '' !== trim($this->q) ? trim($this->q) : null,
            'gammes' => array_map(static fn (TypeFiche $t): string => $t->value, $this->gammes),
            'statuts' => array_map(static fn (StatutFiche $s): string => $s->value, $this->statuts),
            'completudes' => $this->completudes,
            'pays' => $this->pays,
            'canaux' => $this->canaux,
            'dates' => $this->dates,
            'contributeurs' => $this->contributeurs,
            'ia' => $this->ia ?: null,
            'repli' => $this->repli ?: null,
            'premium' => $this->premium ?: null,
            'valeurs' => $this->valeurs,
        ], static fn (mixed $value): bool => null !== $value && [] !== $value);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $filtres = new self();
        $filtres->q = is_string($data['q'] ?? null) ? $data['q'] : null;
        $filtres->gammes = self::enumList($data['gammes'] ?? [], TypeFiche::tryFrom(...));
        $filtres->statuts = self::enumList($data['statuts'] ?? [], StatutFiche::tryFrom(...));
        $filtres->completudes = self::stringList($data['completudes'] ?? [], ['complet', 'publiable', 'insuffisant']);
        $filtres->pays = self::stringList($data['pays'] ?? [], null);
        $filtres->canaux = self::stringList($data['canaux'] ?? [], ['c20', 'c5', 'c1', 'c0']);
        $filtres->dates = self::stringList($data['dates'] ?? [], ['creees_semaine', 'modifiees_jour', 'sans_modif_6m']);
        $filtres->contributeurs = self::stringList($data['contributeurs'] ?? [], null);
        $filtres->ia = (bool) ($data['ia'] ?? false);
        $filtres->repli = (bool) ($data['repli'] ?? false);
        $filtres->premium = (bool) ($data['premium'] ?? false);
        $filtres->valeurs = array_values(array_filter(array_map(
            static fn (mixed $v): int => (int) $v,
            is_array($data['valeurs'] ?? null) ? $data['valeurs'] : [],
        ), static fn (int $v): bool => $v > 0));

        return $filtres;
    }

    /**
     * @template T
     *
     * @param callable(string): ?T $tryFrom
     *
     * @return list<T>
     */
    private static function enumList(mixed $values, callable $tryFrom): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $v): mixed => is_string($v) ? $tryFrom($v) : null,
            $values,
        )));
    }

    /**
     * @param list<string>|null $allowed
     *
     * @return list<string>
     */
    private static function stringList(mixed $values, ?array $allowed): array
    {
        if (!is_array($values)) {
            return [];
        }
        $values = array_values(array_filter($values, static fn (mixed $v): bool => is_string($v) && '' !== $v));

        return null === $allowed ? $values : array_values(array_intersect($values, $allowed));
    }
}
