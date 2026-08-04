<?php

declare(strict_types=1);

namespace App\Enrichment\Repository;

use App\Enrichment\Entity\AttributeDefinitionTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Entity\AttributDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AttributeDefinitionTranslation> */
final class AttributeDefinitionTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeDefinitionTranslation::class);
    }

    public function findOne(AttributDefinition $attribute, SupportedLocale $locale): ?AttributeDefinitionTranslation
    {
        return $this->findOneBy(['attribute' => $attribute, 'locale' => $locale]);
    }

    public function findRequested(AttributDefinition $attribute, SupportedLocale $locale, string $token): ?AttributeDefinitionTranslation
    {
        return $this->findOneBy(['attribute' => $attribute, 'locale' => $locale, 'requestToken' => $token]);
    }
}
