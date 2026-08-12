<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheRelance;
use App\Pim\Enum\StatutFiche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<FicheRelance> */
final class FicheRelanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheRelance::class);
    }

    /**
     * Fiches sous le seuil de complétude, non archivées, avec au moins un
     * collaborateur destinataire actif et sans relance depuis le cooldown.
     *
     * @return list<array{id: string, completeness: int}> identifiants de fiches (ULID base32) et score global
     */
    public function findFichesNeedingReminder(int $threshold, \DateTimeImmutable $cooldownDate): array
    {
        /** @var list<array{id: string, completeness: int|string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT f.id, detail.completeness_global AS completeness FROM pim_fiche f
             JOIN (
                 SELECT fiche_id, completeness_global FROM pim_lieu
                 UNION ALL SELECT fiche_id, completeness_global FROM pim_activite
                 UNION ALL SELECT fiche_id, completeness_global FROM pim_restaurant
                 UNION ALL SELECT fiche_id, completeness_global FROM pim_service_evenementiel
             ) detail ON detail.fiche_id = f.id
             WHERE f.status <> :archived
               AND detail.completeness_global < :threshold
               AND EXISTS (
                   SELECT 1 FROM pim_fiche_affiliation affiliation
                   JOIN pim_fiche_collaborateur collaborateur ON collaborateur.id = affiliation.collaborateur_id
                   WHERE affiliation.fiche_id = f.id
                     AND affiliation.receives_requests = 1
                     AND collaborateur.is_active = 1
               )
               AND NOT EXISTS (
                   SELECT 1 FROM pim_fiche_relance relance
                   WHERE relance.fiche_id = f.id AND relance.sent_at > :cooldown
               )',
            [
                'archived' => StatutFiche::Archivee->value,
                'threshold' => $threshold,
                'cooldown' => $cooldownDate->format('Y-m-d H:i:s'),
            ],
        );

        return array_map(static fn (array $row): array => [
            'id' => (string) Ulid::fromBinary($row['id']),
            'completeness' => (int) $row['completeness'],
        ], $rows);
    }

    /**
     * Dernières relances envoyées, pour la section historique du dashboard.
     *
     * @return list<FicheRelance>
     */
    public function dernieres(int $max = 50): array
    {
        return $this->findBy([], ['sentAt' => 'DESC', 'id' => 'DESC'], $max);
    }

    public function lastSentAt(Fiche $fiche): ?\DateTimeImmutable
    {
        /** @var string|null $sentAt */
        $sentAt = $this->createQueryBuilder('relance')
            ->select('MAX(relance.sentAt)')
            ->where('relance.fiche = :fiche')
            ->setParameter('fiche', $fiche->id(), 'ulid')
            ->getQuery()
            ->getSingleScalarResult();

        return null === $sentAt ? null : new \DateTimeImmutable($sentAt);
    }
}
