<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\TypeFiche;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Ulid;

final readonly class CompletenessEntityRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    /** @param list<Fiche> $fiches
     *  @return list<object>
     */
    public function findAggregates(TypeFiche $type, array $fiches): array
    {
        $class = match ($type) {
            TypeFiche::Lieu => Lieu::class,
            TypeFiche::Activite => Activite::class,
            TypeFiche::Restaurant => Restaurant::class,
            TypeFiche::ServiceEvenementiel => ServiceEvenementiel::class,
            default => null,
        };
        if (null === $class || [] === $fiches) {
            return [];
        }
        $qb = $this->entityManager->createQueryBuilder()
            ->select('entity', 'fiche', 'localisation')
            ->from($class, 'entity')
            ->join('entity.fiche', 'fiche')
            ->leftJoin('fiche.localisation', 'localisation');
        $this->restrictToObjects($qb, 'fiche', $fiches);
        if (TypeFiche::Lieu === $type) {
            $qb->addSelect('administratif', 'tarification')
                ->leftJoin('entity.administratif', 'administratif')
                ->leftJoin('entity.tarification', 'tarification');
        }
        /** @var list<object> $entities */
        $entities = $qb->getQuery()->getResult();
        $this->fetchCollection(Fiche::class, 'attributValues', $fiches);
        $this->fetchCollection(Fiche::class, 'resources', $fiches);
        foreach (match ($type) {
            TypeFiche::Lieu => ['salles', 'periodesFermeture', 'acces'],
            TypeFiche::Activite => ['offres'],
            TypeFiche::Restaurant => ['salles', 'periodesFermeture', 'acces'],
            default => [],
        } as $association) {
            $this->fetchCollection($class, $association, $entities);
        }

        return $entities;
    }

    /** @param class-string $class
     *  @param list<object> $owners
     */
    private function fetchCollection(string $class, string $association, array $owners): void
    {
        if ([] === $owners) {
            return;
        }
        $qb = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT owner', 'item')
            ->from($class, 'owner')
            ->leftJoin('owner.'.$association, 'item');
        $this->restrictToObjects($qb, 'owner', $owners);
        $qb->getQuery()->getResult();
    }

    /** @param list<object> $objects */
    private function restrictToObjects(QueryBuilder $qb, string $alias, array $objects): void
    {
        $or = $qb->expr()->orX();
        foreach ($objects as $index => $object) {
            $parameter = 'object_'.$index;
            $id = $object instanceof Fiche ? $object->id() : (method_exists($object, 'id') ? Ulid::fromString((string) $object->id()) : null);
            if (!$id instanceof Ulid) {
                throw new \InvalidArgumentException('Un objet chargeable par lot doit exposer un identifiant ULID.');
            }
            $or->add($alias.'.id = :'.$parameter);
            $qb->setParameter($parameter, $id, 'ulid');
        }
        $qb->andWhere($or);
    }
}
