<?php

namespace App\Etl\Service\GoogleTranslate;

use App\Entity\Option;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TranslationQuotaService
{
    public const QUOTA_OPTION_NAME = 'i18n_monthly_quota';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function refreshMonthlyQuota(): void
    {
        $quotaOption = $this->getOrCreateQuotaOption();
        $quota = $this->normalizeQuotaValue($quotaOption->getValue());

        if ($this->resetPeriodIfNeeded($quota)) {
            $quotaOption->setValue($quota);
            $this->entityManager->flush();
        }
    }

    public function reserveCharacters(int $characters): bool
    {
        if ($characters <= 0) {
            return true;
        }

        $quotaOption = $this->getOrCreateQuotaOption();
        $quota = $this->normalizeQuotaValue($quotaOption->getValue());
        $this->resetPeriodIfNeeded($quota);

        if (($quota['autoDisabled'] ?? false) === true) {
            $quotaOption->setValue($quota);
            $this->entityManager->flush();

            return false;
        }

        if (($quota['enabled'] ?? true) !== true) {
            $quotaOption->setValue($quota);
            $this->entityManager->flush();

            return true;
        }

        $usedCharacters = (int) ($quota['usedCharacters'] ?? 0);
        $monthlyLimitCharacters = $this->resolveMonthlyLimitCharacters($quota);
        if ($monthlyLimitCharacters === null) {
            $quotaOption->setValue($quota);
            $this->entityManager->flush();

            $this->logger->warning('Google Translate monthly quota limit is missing or invalid', [
                'period' => $quota['period'] ?? null,
            ]);

            return false;
        }

        if ($usedCharacters + $characters > $monthlyLimitCharacters) {
            $quota['autoDisabled'] = true;

            $quotaOption->setValue($quota);
            $this->entityManager->flush();

            $this->logger->warning('Google Translate monthly quota exceeded', [
                'characters' => $characters,
                'used_characters' => $usedCharacters,
                'monthly_limit_characters' => $monthlyLimitCharacters,
                'period' => $quota['period'] ?? null,
            ]);

            return false;
        }

        $quota['usedCharacters'] = $usedCharacters + $characters;
        $quotaOption->setValue($quota);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @param array<string, mixed> $quota
     */
    private function resetPeriodIfNeeded(array &$quota): bool
    {
        $currentPeriod = (new \DateTimeImmutable())->format('Y-m');
        if (($quota['period'] ?? null) === $currentPeriod) {
            return false;
        }

        $quota['period'] = $currentPeriod;
        $quota['usedCharacters'] = 0;
        $quota['autoDisabled'] = false;

        return true;
    }

    private function getOrCreateQuotaOption(): Option
    {
        $option = $this->entityManager->getRepository(Option::class)->findOneBy([
            'name' => self::QUOTA_OPTION_NAME,
        ]);

        if ($option instanceof Option) {
            return $option;
        }

        $option = (new Option())
            ->setName(self::QUOTA_OPTION_NAME)
            ->setValue($this->getDefaultQuotaValue());

        $this->entityManager->persist($option);

        return $option;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function normalizeQuotaValue(array $value): array
    {
        return array_replace($this->getDefaultQuotaValue(), $value);
    }

    /**
     * @param array<string, mixed> $quota
     */
    private function resolveMonthlyLimitCharacters(array $quota): ?int
    {
        $monthlyLimitCharacters = $quota['monthlyLimitCharacters'] ?? null;
        if (!is_numeric($monthlyLimitCharacters)) {
            return null;
        }

        $monthlyLimitCharacters = (int) $monthlyLimitCharacters;

        return $monthlyLimitCharacters > 0 ? $monthlyLimitCharacters : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultQuotaValue(): array
    {
        return [
            'enabled' => true,
            'usedCharacters' => 0,
            'period' => (new \DateTimeImmutable())->format('Y-m'),
            'autoDisabled' => false,
        ];
    }
}
