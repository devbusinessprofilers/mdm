<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\TextFingerprint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<TextFingerprint> */
final class TextFingerprintRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TextFingerprint::class);
    }

    public function findOneByFicheAndField(string $ficheId, string $fieldPath): ?TextFingerprint
    {
        return $this->findOneBy(['ficheId' => $ficheId, 'fieldPath' => $fieldPath]);
    }

    /**
     * Plus ancienne empreinte au même hash exact portée par une AUTRE fiche :
     * la fiche de référence d'un doublon exact (copier-coller intégral).
     */
    public function findOldestExactMatchOnOtherFiche(string $exactHash, string $ficheId): ?TextFingerprint
    {
        return $this->createQueryBuilder('fp')
            ->where('fp.exactHash = :hash')
            ->andWhere('fp.ficheId <> :ficheId')
            ->setParameter('hash', $exactHash)
            ->setParameter('ficheId', $ficheId)
            ->orderBy('fp.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<string> $ids empreintes candidates (identifiants ULID en chaîne)
     *
     * @return list<TextFingerprint>
     */
    public function findByStringIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<TextFingerprint> $rows */
        $rows = $this->createQueryBuilder('fp')
            ->where('fp.id IN (:ids)')
            ->setParameter(
                'ids',
                array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $ids),
                ArrayParameterType::BINARY,
            )
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
