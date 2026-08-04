<?php

declare(strict_types=1);

namespace App\Enrichment\Repository;

use App\Enrichment\Entity\AttributeValueTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Entity\ValeurAttribut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AttributeValueTranslation> */
final class AttributeValueTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeValueTranslation::class);
    }

    public function findOne(ValeurAttribut $value, SupportedLocale $locale): ?AttributeValueTranslation
    {
        return $this->findOneBy(['value' => $value, 'locale' => $locale]);
    }

    public function findRequested(ValeurAttribut $value, SupportedLocale $locale, string $token): ?AttributeValueTranslation
    {
        return $this->findOneBy(['value' => $value, 'locale' => $locale, 'requestToken' => $token]);
    }

    /** @param list<ValeurAttribut> $values
     *  @return array<int, array<string, AttributeValueTranslation>>
     */
    public function indexedForValues(array $values): array
    {
        if ([] === $values) {
            return [];
        }
        $indexed = [];
        foreach ($this->findBy(['value' => $values]) as $translation) {
            $indexed[$translation->value()->id()][$translation->locale()->value] = $translation;
        }

        return $indexed;
    }
}
