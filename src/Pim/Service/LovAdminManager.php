<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Enrichment\Entity\AttributeValueTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Repository\AttributeValueTranslationRepository;
use App\Enrichment\Service\LovTranslationScheduler;
use App\Etl\Service\MarketplaceLovSyncScheduler;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Lov\LovRuntimeCatalog;
use App\Pim\Lov\LovStableIdGenerator;
use App\Pim\Repository\ValeurAttributRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LovAdminManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValeurAttributRepository $values,
        private AttributeValueTranslationRepository $translations,
        private LovStableIdGenerator $ids,
        private LovTranslationScheduler $scheduler,
        private MarketplaceLovSyncScheduler $marketplace,
        private LovRuntimeCatalog $runtime,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(AttributDefinition $attribute, array $data, string $actor): ValeurAttribut
    {
        $code = strtoupper(trim((string) $data['code']));
        if (null !== $this->values->findOneByAttributeAndCode($attribute, $code)) {
            throw new \DomainException('Ce code existe déjà.');
        }
        $value = new ValeurAttribut(
            $this->ids->valueId($attribute->code(), $code),
            $attribute,
            $code,
            (string) $data['label'],
            (int) $data['position'],
            (bool) $data['active'],
        );
        $this->entityManager->persist($value);
        $this->scheduler->scheduleValue($value);
        $this->entityManager->flush();
        $this->applyManualTranslations($value, $data, $actor);
        // Même transaction que la mutation (outbox) : la marketplace reçoit
        // le snapshot complet du dictionnaire.
        $this->marketplace->schedule();
        $this->entityManager->flush();
        $this->runtime->reload();

        return $value;
    }

    /** @param array<string, mixed> $data */
    public function update(ValeurAttribut $value, array $data, string $actor): void
    {
        $labelChanged = trim((string) $data['label']) !== $value->label();
        $value->changeLabel((string) $data['label']);
        $value->changePosition((int) $data['position']);
        (bool) $data['active'] ? $value->activate() : $value->deactivate();
        if ($labelChanged) {
            $this->scheduler->scheduleValue($value);
        }
        $this->applyManualTranslations($value, $data, $actor);
        $this->marketplace->schedule();
        $this->entityManager->flush();
        $this->runtime->reload();
    }

    public function retry(ValeurAttribut $value): int
    {
        $scheduled = $this->scheduler->scheduleValue($value);
        $this->entityManager->flush();

        return $scheduled;
    }

    /** @param array<string, mixed> $data */
    private function applyManualTranslations(ValeurAttribut $value, array $data, string $actor): void
    {
        foreach (SupportedLocale::targets() as $locale) {
            $manual = trim((string) ($data['translation_'.$locale->value] ?? ''));
            if ('' === $manual) {
                continue;
            }
            $translation = $this->translations->findOne($value, $locale);
            if (!$translation instanceof AttributeValueTranslation) {
                $translation = new AttributeValueTranslation($value, $locale, $value->label());
                $this->entityManager->persist($translation);
            }
            $translation->applyManual($manual, $actor);
        }
    }
}
