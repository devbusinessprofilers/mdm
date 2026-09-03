<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<FicheAffiliation> */
final class FicheAffiliationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheAffiliation::class);
    }

    public function findFor(FicheCollaborateur $collaborateur, Fiche $fiche): ?FicheAffiliation
    {
        return $this->findOneBy(['collaborateur' => $collaborateur, 'fiche' => $fiche]);
    }

    public function countRequestRecipients(Fiche $fiche, ?FicheAffiliation $excluding = null): int
    {
        $sql = 'SELECT COUNT(*) FROM pim_fiche_affiliation WHERE fiche_id = :fiche AND receives_requests = 1';
        $parameters = ['fiche' => $fiche->id()];
        $types = ['fiche' => 'ulid'];
        if (null !== $excluding) {
            $sql .= ' AND id != :excluding';
            $parameters['excluding'] = $excluding->id();
            $types['excluding'] = 'ulid';
        }

        return (int) $this->getEntityManager()->getConnection()->fetchOne($sql, $parameters, $types);
    }

    /**
     * @param list<FicheCollaborateur> $collaborateurs
     *
     * @return array<string, list<FicheAffiliation>> affiliations (fiche chargée) indexées par id du collaborateur
     */
    public function indexedByCollaborateur(array $collaborateurs): array
    {
        if ([] === $collaborateurs) {
            return [];
        }
        /** @var list<FicheAffiliation> $affiliations */
        $affiliations = $this->createQueryBuilder('a')
            ->select('a', 'f')
            ->join('a.fiche', 'f')
            ->andWhere('a.collaborateur IN (:collaborateurs)')
            // DQL n'applique pas le type 'ulid' aux paramètres inférés : lier
            // les valeurs binaires explicitement.
            ->setParameter(
                'collaborateurs',
                array_map(static fn (FicheCollaborateur $c): string => Ulid::fromString($c->id())->toBinary(), $collaborateurs),
                ArrayParameterType::BINARY,
            )
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()->getResult();
        $indexed = [];
        foreach ($affiliations as $affiliation) {
            $indexed[$affiliation->collaborateur()->id()][] = $affiliation;
        }

        return $indexed;
    }
}
