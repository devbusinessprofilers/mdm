<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\TypeFiche;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class CompletenessEntityResolver
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function resolve(Fiche $fiche): object|null
    {
        return $this->resolveBatch([$fiche])[$fiche->idString()] ?? null;
    }

    /**
     * Charge un lot avec un nombre de requêtes indépendant du nombre de fiches.
     *
     * @param list<Fiche> $fiches
     * @return array<string, object>
     */
    public function resolveBatch(array $fiches): array
    {
        if ([] === $fiches) {
            return [];
        }
        $type = $fiches[0]->type();
        foreach ($fiches as $fiche) {
            if ($fiche->type() !== $type) {
                throw new \InvalidArgumentException('Un lot de complétude ne peut contenir qu’un seul type de fiche.');
            }
        }
        $class = match ($type) {
            TypeFiche::Lieu => Lieu::class,
            TypeFiche::Activite => Activite::class,
            TypeFiche::Restaurant => Restaurant::class,
            TypeFiche::ServiceEvenementiel => ServiceEvenementiel::class,
            default => null,
        };
        if (null === $class) {
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

        $resolved = [];
        foreach ($entities as $entity) {
            if (method_exists($entity, 'fiche')) {
                $resolved[$entity->fiche()->idString()] = $entity;
            }
        }

        return $resolved;
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
    private function restrictToObjects(\Doctrine\ORM\QueryBuilder $qb, string $alias, array $objects): void
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
