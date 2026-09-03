<?php

declare(strict_types=1);

namespace App\Dashboard\Journal;

/**
 * Une famille du journal des traitements : où lire ses lignes, comment
 * reconnaître un échec, et comment présenter une ligne. Le journal complet,
 * la vue des échecs et le compteur du tableau de bord dérivent tous de cette
 * seule définition ; ajouter une famille, c'est ajouter une entrée.
 */
final readonly class FamilleTraitement
{
    /**
     * @param \Closure(array<string, mixed>): array{sujet: string, statut: string, erreur: ?string, quand: string, lien: ?array{route: string, params: array<string, string>}, expire?: ?string} $ligne
     */
    public function __construct(
        public string $code,
        /** Liste SELECT. */
        public string $colonnes,
        /** Clause FROM, jointures comprises. */
        public string $depuis,
        /** Restriction permanente (WHERE sans le mot-clé). */
        public ?string $condition,
        /** Condition d'échec (WHERE) ; null si la famille ne connaît pas l'échec. */
        public ?string $echec,
        /** Expression de tri décroissant. */
        public string $tri,
        /** Présentation d'une ligne brute. */
        public \Closure $ligne,
        /** Faux pour une famille visible seulement parmi les échecs (outbox). */
        public bool $dansJournal = true,
    ) {
    }

    public function requete(bool $seulementEchecs, int $limit): string
    {
        return 'SELECT '.$this->colonnes.' FROM '.$this->depuis.$this->where($seulementEchecs).' ORDER BY '.$this->tri.' DESC LIMIT '.$limit;
    }

    public function requeteCompteEchecs(): string
    {
        return 'SELECT COUNT(*) FROM '.$this->depuis.$this->where(true);
    }

    private function where(bool $seulementEchecs): string
    {
        $conditions = array_values(array_filter([$this->condition, $seulementEchecs ? $this->echec : null]));

        return [] === $conditions ? '' : ' WHERE '.implode(' AND ', $conditions);
    }
}
