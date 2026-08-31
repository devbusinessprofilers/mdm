<?php

declare(strict_types=1);

namespace App\Pim\Fusion;

use App\Audit\Repository\AuditChangeRepository;
use App\Pim\Entity\Fiche;

/**
 * Présélectionne, pour un champ divergent entre deux fiches, la valeur à
 * retenir : la plus récemment modifiée. L'audit par champ fait foi quand il
 * existe ; l'essentiel du parc étant antérieur au système d'audit (import
 * legacy), les replis dégradent proprement jusqu'à la date de modification
 * de la fiche.
 */
final class ChampRecenceProvider
{
    /** @var array<string, array<string, \DateTimeImmutable>> dates par path, par fiche */
    private array $cache = [];

    public function __construct(private readonly AuditChangeRepository $changes)
    {
    }

    /**
     * @return 'a'|'b' côté présélectionné
     */
    public function preselection(Fiche $a, Fiche $b, ?string $auditPath, mixed $valeurA, mixed $valeurB): string
    {
        // 1. Une seule valeur renseignée : elle gagne, quelle que soit sa date.
        $videA = self::vide($valeurA);
        $videB = self::vide($valeurB);
        if ($videA !== $videB) {
            return $videA ? 'b' : 'a';
        }

        if (null !== $auditPath) {
            $dateA = $this->datesPour($a)[$auditPath] ?? null;
            $dateB = $this->datesPour($b)[$auditPath] ?? null;
            // 2. Deux dates d'audit : la plus récente l'emporte.
            if (null !== $dateA && null !== $dateB && $dateA != $dateB) {
                return $dateA > $dateB ? 'a' : 'b';
            }
            // 3. Une seule fiche auditée sur ce champ : l'absence d'audit date
            // la valeur d'avant le système, elle est donc plus ancienne.
            if ((null === $dateA) !== (null === $dateB)) {
                return null !== $dateA ? 'a' : 'b';
            }
        }

        // 4. Aucun audit exploitable : repli sur la fiche modifiée en dernier.
        return $a->updatedAt() >= $b->updatedAt() ? 'a' : 'b';
    }

    /** Date de la dernière modification auditée du champ, si le système d'audit l'a vue passer. */
    public function derniereModification(Fiche $fiche, ?string $auditPath): ?\DateTimeImmutable
    {
        if (null === $auditPath) {
            return null;
        }

        return $this->datesPour($fiche)[$auditPath] ?? null;
    }

    private static function vide(mixed $valeur): bool
    {
        return null === $valeur
            || (is_string($valeur) && '' === trim($valeur))
            || (is_array($valeur) && [] === $valeur);
    }

    /** @return array<string, \DateTimeImmutable> */
    private function datesPour(Fiche $fiche): array
    {
        return $this->cache[$fiche->idString()] ??= $this->changes->lastChangeDatesByPath($fiche->idString());
    }
}
