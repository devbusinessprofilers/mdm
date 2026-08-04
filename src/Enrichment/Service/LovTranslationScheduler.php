<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\Entity\AbstractLovTranslation;
use App\Enrichment\Entity\AttributeDefinitionTranslation;
use App\Enrichment\Entity\AttributeValueTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Message\TranslateLovLabel;
use App\Enrichment\Repository\AttributeDefinitionTranslationRepository;
use App\Enrichment\Repository\AttributeValueTranslationRepository;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\ValeurAttribut;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class LovTranslationScheduler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private AttributeDefinitionTranslationRepository $definitionTranslations,
        private AttributeValueTranslationRepository $valueTranslations,
    ) {}

    public function scheduleDefinition(AttributDefinition $attribute): int
    {
        if (!$attribute->translatable() || 'PRESTATAIRE' === $attribute->code()) { return 0; }

        return $this->schedule('definition', $attribute->id(), $attribute->label(), fn (SupportedLocale $locale) => $this->definitionTranslations->findOne($attribute, $locale), static fn (SupportedLocale $locale) => new AttributeDefinitionTranslation($attribute, $locale, $attribute->label()));
    }

    public function scheduleValue(ValeurAttribut $value): int
    {
        if (!$value->attribute()->translatable() || 'PRESTATAIRE' === $value->attribute()->code()) { return 0; }

        return $this->schedule('value', $value->id(), $value->label(), fn (SupportedLocale $locale) => $this->valueTranslations->findOne($value, $locale), static fn (SupportedLocale $locale) => new AttributeValueTranslation($value, $locale, $value->label()));
    }

    /**
     * @param callable(SupportedLocale): ?AbstractLovTranslation $finder
     * @param callable(SupportedLocale): AbstractLovTranslation $factory
     */
    private function schedule(string $subject, int $subjectId, string $source, callable $finder, callable $factory): int
    {
        $count = 0;
        foreach (SupportedLocale::targets() as $locale) {
            $token = (string) new Ulid();
            $translation = $finder($locale);
            if (!$translation instanceof AbstractLovTranslation) {
                $translation = $factory($locale);
                $this->entityManager->persist($translation);
            }
            if ($translation->schedule($source, $token)) {
                $this->outbox->enqueue(new TranslateLovLabel($subject, $subjectId, $locale->value, $token));
                ++$count;
            }
        }

        return $count;
    }
}
