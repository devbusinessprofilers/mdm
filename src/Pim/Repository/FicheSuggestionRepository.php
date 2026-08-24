<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheSuggestion;
use App\Pim\Enum\SuggestionSource;
use App\Pim\Enum\SuggestionStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<FicheSuggestion> */
final class FicheSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheSuggestion::class);
    }

    /**
     * Suggestions génériques en attente d'une fiche, les plus récentes d'abord.
     *
     * @return list<FicheSuggestion>
     */
    public function findEnAttentePourFiche(Fiche $fiche): array
    {
        /** @var list<FicheSuggestion> $rows */
        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.fiche = :fiche')
            ->andWhere('s.statut = :statut')
            ->setParameter('fiche', Ulid::fromString($fiche->idString()), 'ulid')
            ->setParameter('statut', SuggestionStatut::EnAttente->value)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * La suggestion pour une clé (fiche, source, champ), quel que soit son
     * statut — sert au dédoublonnage : la contrainte unique impose une seule
     * ligne par clé, et un re-run doit rafraîchir l'attente OU respecter une
     * décision déjà prise, jamais recréer une ligne.
     */
    public function findPourCle(Fiche $fiche, SuggestionSource $source, string $champ): ?FicheSuggestion
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.fiche = :fiche')
            ->andWhere('s.source = :source')
            ->andWhere('s.champ = :champ')
            ->setParameter('fiche', Ulid::fromString($fiche->idString()), 'ulid')
            ->setParameter('source', $source->value)
            ->setParameter('champ', $champ)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Nombre total de suggestions génériques en attente (indicateur écran Qualité). */
    public function countEnAttente(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.statut = :statut')
            ->setParameter('statut', SuggestionStatut::EnAttente->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
