<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\FicheRelancePlanifiee;
use App\Pim\Enum\StatutRelancePlanifiee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<FicheRelancePlanifiee> */
final class FicheRelancePlanifieeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheRelancePlanifiee::class);
    }

    /**
     * Lignes de la dernière préparation, tous statuts confondus : les lignes
     * partagent le même preparedAt (instant unique passé au constructeur),
     * le lot courant est donc celui du preparedAt le plus récent.
     *
     * @return list<FicheRelancePlanifiee>
     */
    public function lotCourant(): array
    {
        /** @var string|null $preparedAt */
        $preparedAt = $this->createQueryBuilder('planifiee')
            ->select('MAX(planifiee.preparedAt)')
            ->getQuery()
            ->getSingleScalarResult();
        if (null === $preparedAt) {
            return [];
        }

        return $this->findBy(
            ['preparedAt' => new \DateTimeImmutable($preparedAt)],
            ['completenessAtPreparation' => 'ASC', 'id' => 'ASC'],
        );
    }

    /** @return list<string> identifiants (ULID base32) des lignes encore planifiées */
    public function idsPlanifiees(): array
    {
        /** @var list<string> $binaryIds */
        $binaryIds = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT id FROM pim_fiche_relance_planifiee WHERE statut = ?',
            [StatutRelancePlanifiee::Planifiee->value],
        );

        return array_map(static fn (string $id): string => (string) Ulid::fromBinary($id), $binaryIds);
    }

    /** Annule en masse les lignes encore planifiées (remplacement de lot). */
    public function annulerPlanifiees(\DateTimeImmutable $quand, string $motif): int
    {
        return (int) $this->createQueryBuilder('planifiee')
            ->update()
            ->set('planifiee.statut', ':annulee')
            ->set('planifiee.processedAt', ':quand')
            ->set('planifiee.motif', ':motif')
            ->where('planifiee.statut = :statut')
            ->setParameter('annulee', StatutRelancePlanifiee::Annulee->value)
            ->setParameter('quand', $quand)
            ->setParameter('motif', $motif)
            ->setParameter('statut', StatutRelancePlanifiee::Planifiee)
            ->getQuery()
            ->execute();
    }
}
