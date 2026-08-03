<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\CompletenessFieldConfiguration;
use App\Pim\Enum\TypeFiche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CompletenessFieldConfiguration> */
final class CompletenessFieldConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompletenessFieldConfiguration::class);
    }

    /** @return list<CompletenessFieldConfiguration> */
    public function activeFor(TypeFiche $type): array
    {
        return $this->findBy(['ficheType' => $type, 'active' => true], ['fieldCode' => 'ASC']);
    }
}
