<?php

declare(strict_types=1);

namespace App\Enrichment\Repository;

use App\Enrichment\Entity\AttributeValueTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Enum\TranslationStatus;
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
     * @return array<int, array<string, AttributeValueTranslation>>
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

    /**
     * Libellés traduits disponibles, indexés pour la diffusion marketplace du
     * dictionnaire LOV.
     *
     * @return array<string, array<string, array<string, string>>> [attribut][code valeur][locale] => libellé
     */
    public function findAvailableDictionaryRows(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT a.code attribute_code, v.code value_code, t.locale, t.translated_label
             FROM pim_attribute_value_translation t
             INNER JOIN pim_attribute_value v ON v.id = t.value_id
             INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id
             WHERE t.status = :status AND t.translated_label IS NOT NULL',
            ['status' => TranslationStatus::Available->value],
        );
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['attribute_code']][(string) $row['value_code']][(string) $row['locale']] = (string) $row['translated_label'];
        }

        return $indexed;
    }
}
