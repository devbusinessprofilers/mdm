<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\Entity\AbstractLovTranslation;
use App\Enrichment\Entity\AttributeDefinitionTranslation;
use App\Enrichment\Entity\AttributeValueTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Message\TranslateLovLabel;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\ValeurAttribut;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class LovTranslationScheduler
{
    public function __construct(private EntityManagerInterface $entityManager, private OutboxPublisherInterface $outbox) {}

    public function scheduleDefinition(AttributDefinition $attribute): int
    {
        if (!$attribute->translatable() || 'PRESTATAIRE' === $attribute->code()) { return 0; }

        return $this->schedule('definition', $attribute->id(), $attribute->label(), AttributeDefinitionTranslation::class, ['attribute' => $attribute], static fn (SupportedLocale $locale) => new AttributeDefinitionTranslation($attribute, $locale, $attribute->label()));
    }

    public function scheduleValue(ValeurAttribut $value): int
    {
        if (!$value->attribute()->translatable() || 'PRESTATAIRE' === $value->attribute()->code()) { return 0; }

        return $this->schedule('value', $value->id(), $value->label(), AttributeValueTranslation::class, ['value' => $value], static fn (SupportedLocale $locale) => new AttributeValueTranslation($value, $locale, $value->label()));
    }

    /**
     * @param class-string<AbstractLovTranslation> $class
     * @param array<string, object> $criteria
     * @param callable(SupportedLocale): AbstractLovTranslation $factory
     */
    private function schedule(string $subject, int $subjectId, string $source, string $class, array $criteria, callable $factory): int
    {
        $count = 0;
        foreach (SupportedLocale::targets() as $locale) {
            $token = (string) new Ulid();
            $translation = $this->entityManager->getRepository($class)->findOneBy([...$criteria, 'locale' => $locale]);
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
