<?php

namespace App\Etl\Service\GoogleTranslate;

use App\Entity\Lieu;
use App\Entity\Option;
use App\Etl\Exception\TranslationQuotaExceededException;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Mapping\Annotation\Translatable;
use Gedmo\Mapping\Annotation\TranslationEntity;
use Gedmo\Translatable\Entity\MappedSuperclass\AbstractPersonalTranslation;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleTranslateService
{
    private const TRANSLATE_ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';
    private const AUTO_TRANSLATION_DISABLE_OPTION_NAME = 'disable_i18n';
    private const LIEU_PAGE_PRODUIT_TRANSLATABLE_FIELDS = ['descriptionFr'];
    private const LIEU_PAGE_LIEU_TRANSLATABLE_FIELDS = ['chambresFr', 'sallesFr', 'restaurationFr', 'offreSpecialeFr', 'motExpert'];
    private const LIEU_PAGE_TAXONOMY_TRANSLATABLE_FIELDS = ['nom'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $google_maps_api_key,
        private readonly string $defaultLocale,
        private readonly array $locales,
        private readonly ?TranslationQuotaService $translationQuotaService = null,
    ) {
    }

    public function isConfigured(): bool
    {
        $apiKey = trim($this->google_maps_api_key);

        return $apiKey !== '' && !str_starts_with($apiKey, '#');
    }

    public function translateText(
        string $sourceText,
        string $targetLocale,
        ?string $sourceLocale = null,
        ?string $format = null,
    ): string {
        $trimmedSourceText = trim($sourceText);
        if ($trimmedSourceText === '') {
            return '';
        }

        if (!$this->isConfigured()) {
            throw new \RuntimeException('GOOGLE_MAPS_API_KEY is not configured.');
        }

        $targetLocale = $this->normalizeLocale($targetLocale);
        $sourceLocale = $this->normalizeLocale($sourceLocale ?? $this->defaultLocale);
        $format ??= $this->detectFormat($sourceText);

        if ($targetLocale === $sourceLocale) {
            return $sourceText;
        }

        if (
            $this->translationQuotaService instanceof TranslationQuotaService
            && !$this->translationQuotaService->reserveCharacters($this->countCharacters($sourceText))
        ) {
            throw new TranslationQuotaExceededException(sprintf(
                'Google Translate monthly quota exceeded for locale "%s".',
                $targetLocale,
            ));
        }

        try {
            $payload = $this->httpClient->request('POST', self::TRANSLATE_ENDPOINT, [
                'query' => [
                    'key' => $this->google_maps_api_key,
                ],
                'body' => [
                    'q' => $sourceText,
                    'source' => $sourceLocale,
                    'target' => $targetLocale,
                    'format' => $format,
                ],
            ])->toArray();
        } catch (\Throwable $exception) {
            $this->logger->error('Google Translate request failed', [
                'source_locale' => $sourceLocale,
                'target_locale' => $targetLocale,
                'format' => $format,
                'exception' => $exception->getMessage(),
            ]);

            throw new \RuntimeException(
                sprintf('Google Translate request failed for locale "%s".', $targetLocale),
                0,
                $exception,
            );
        }

        $translatedText = $payload['data']['translations'][0]['translatedText'] ?? null;
        if (!is_string($translatedText) || $translatedText === '') {
            throw new \RuntimeException(sprintf(
                'Google Translate returned an empty response for locale "%s".',
                $targetLocale,
            ));
        }

        return html_entity_decode($translatedText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function translateAndPersist(
        object $entity,
        string $field,
        string $targetLocale,
        ?string $sourceText = null,
        ?string $sourceLocale = null,
        bool $flush = true,
    ): ?string {
        $entityClass = $this->entityManager->getClassMetadata($entity::class)->getName();
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $identifierValues = array_filter(
            $metadata->getIdentifierValues($entity),
            static fn (mixed $value): bool => null !== $value,
        );

        if ([] === $identifierValues) {
            throw new \InvalidArgumentException(sprintf(
                'The entity "%s" must be persisted before it can receive translations.',
                $entityClass,
            ));
        }

        $this->assertFieldIsTranslatable($entityClass, $field);

        $sourceText = $this->resolveSourceText($entity, $entityClass, $field, $sourceText);
        if ($sourceText === null || trim($sourceText) === '') {
            return null;
        }

        $targetLocale = $this->normalizeLocale($targetLocale);
        $sourceLocale = $this->normalizeLocale($sourceLocale ?? $this->defaultLocale);

        if ($targetLocale === $sourceLocale) {
            return $sourceText;
        }

        $translationClass = $this->resolveTranslationClass($entityClass);
        $translation = $this->entityManager->getRepository($translationClass)->findOneBy([
            'object' => $entity,
            'field' => $field,
            'locale' => $targetLocale,
        ]);

        if (null !== $translation && !$translation instanceof AbstractPersonalTranslation) {
            throw new \LogicException(sprintf(
                'The translation class "%s" must extend "%s".',
                $translationClass,
                AbstractPersonalTranslation::class,
            ));
        }

        if ($translation instanceof AbstractPersonalTranslation && trim((string) $translation->getContent()) !== '') {
            return $translation->getContent();
        }

        $this->translationQuotaService?->refreshMonthlyQuota();
        if ($this->isAutoTranslationDisabled()) {
            return $sourceText;
        }

        try {
            $translatedText = $this->translateText($sourceText, $targetLocale, $sourceLocale);
        } catch (TranslationQuotaExceededException) {
            return $sourceText;
        }

        if (!$translation instanceof AbstractPersonalTranslation) {
            $translation = new $translationClass($targetLocale, $field, $translatedText);

            if (!$translation instanceof AbstractPersonalTranslation) {
                throw new \LogicException(sprintf(
                    'The translation class "%s" must extend "%s".',
                    $translationClass,
                    AbstractPersonalTranslation::class,
                ));
            }

            if (!method_exists($entity, 'addTranslation')) {
                throw new \LogicException(sprintf(
                    'The entity "%s" must expose an addTranslation() method.',
                    $entityClass,
                ));
            }

            $translation->setObject($entity);
            $entity->addTranslation($translation);
            $this->entityManager->persist($translation);
        } else {
            $translation->setContent($translatedText);
        }

        if ($flush) {
            $this->entityManager->flush();
        }

        return $translatedText;
    }

    private function isAutoTranslationDisabled(): bool
    {
        $option = $this->entityManager->getRepository(Option::class)->findOneBy([
            'name' => self::AUTO_TRANSLATION_DISABLE_OPTION_NAME,
        ]);

        if (!$option instanceof Option) {
            return true;
        }

        $disableValue = $option->getValue()['disabled'] ?? null;
        return $disableValue === true;
    }

    public function ensureLieuPageTranslations(Lieu $lieu, string $locale): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $targetLocales = $this->resolveConfiguredTargetLocales([$locale]);
        if ($targetLocales === []) {
            return false;
        }

        $targetLocale = $targetLocales[0];

        $produit = $lieu->getProduit();
        if ($produit === null) {
            return false;
        }

        $this->translateEntityFields($produit, self::LIEU_PAGE_PRODUIT_TRANSLATABLE_FIELDS, $targetLocale);
        $this->translateEntityFields($lieu, self::LIEU_PAGE_LIEU_TRANSLATABLE_FIELDS, $targetLocale);

        foreach ($produit->getAtouts() as $atout) {
            $this->translateEntityFields($atout, self::LIEU_PAGE_TAXONOMY_TRANSLATABLE_FIELDS, $targetLocale);
        }

        foreach ($produit->getEquipements() as $equipement) {
            $this->translateEntityFields($equipement, self::LIEU_PAGE_TAXONOMY_TRANSLATABLE_FIELDS, $targetLocale);
        }

        foreach ($produit->getLoisirs() as $loisir) {
            $this->translateEntityFields($loisir, self::LIEU_PAGE_TAXONOMY_TRANSLATABLE_FIELDS, $targetLocale);
        }

        foreach ($lieu->getTypesLieu() as $typeLieu) {
            $this->translateEntityFields($typeLieu, self::LIEU_PAGE_TAXONOMY_TRANSLATABLE_FIELDS, $targetLocale);
        }

        return true;
    }

    /**
     * @param list<string>|null $requestedLocales
     *
     * @return list<string>
     */
    public function resolveConfiguredTargetLocales(?array $requestedLocales = null): array
    {
        $configuredLocales = $this->buildConfiguredTargetLocaleMap();
        if ($requestedLocales === null) {
            return array_keys($configuredLocales);
        }

        $resolvedLocales = [];
        foreach ($requestedLocales as $locale) {
            $normalizedLocale = $this->normalizeLocale((string) $locale);
            if (isset($configuredLocales[$normalizedLocale])) {
                $resolvedLocales[] = $normalizedLocale;
            }
        }

        return array_values(array_unique($resolvedLocales));
    }

    /**
     * @param array<string, mixed> $previousValues
     * @param array<string, mixed> $currentValues
     * @param list<string>|null $targetLocales
     *
     * @return list<string>
     */
    public function invalidateUpdatedFields(string $entityClass, int $entityId, array $previousValues, array $currentValues, ?array $targetLocales = null,
    ): array {
        $changedValues = $this->extractChangedValues($previousValues, $currentValues);
        if ($changedValues === []) {
            return [];
        }

        $locales = $this->resolveConfiguredTargetLocales($targetLocales);
        if ($locales === []) {
            return array_keys($changedValues);
        }

        $translationClass = $this->resolveTranslationClass($entityClass);
        $entity = $this->entityManager->getReference($entityClass, $entityId);
        $fields = array_keys($changedValues);

        foreach ($fields as $field) {
            $this->assertFieldIsTranslatable($entityClass, $field);
        }

        $this->entityManager->createQuery(sprintf(
            'DELETE FROM %s t WHERE t.object = :entity AND t.field IN (:fields) AND t.locale IN (:locales)',
            $translationClass,
        ))
            ->setParameter('entity', $entity)
            ->setParameter('fields', $fields)
            ->setParameter('locales', $locales)
            ->execute();

        $this->entityManager->clear($translationClass);

        return $fields;
    }

    private function assertFieldIsTranslatable(string $entityClass, string $field): void
    {
        $reflection = new \ReflectionClass($entityClass);
        if (!$reflection->hasProperty($field)) {
            throw new \InvalidArgumentException(sprintf(
                'The field "%s" does not exist on "%s".',
                $field,
                $entityClass,
            ));
        }

        if ([] === $reflection->getProperty($field)->getAttributes(Translatable::class)) {
            throw new \InvalidArgumentException(sprintf(
                'The field "%s::%s" is not marked as Gedmo translatable.',
                $entityClass,
                $field,
            ));
        }
    }

    private function detectFormat(string $sourceText): string
    {
        return strip_tags($sourceText) !== $sourceText ? 'html' : 'text';
    }

    private function countCharacters(string $text): int
    {
        return mb_strlen($text, 'UTF-8');
    }

    /**
     * @return array<string, true>
     */
    private function buildConfiguredTargetLocaleMap(): array
    {
        $defaultLocale = $this->normalizeLocale($this->defaultLocale);
        $resolvedLocales = [];

        foreach ($this->locales as $locale) {
            $normalizedLocale = $this->normalizeLocale((string) $locale);
            if ($normalizedLocale !== $defaultLocale) {
                $resolvedLocales[$normalizedLocale] = true;
            }
        }

        return $resolvedLocales;
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = trim(str_replace('_', '-', $locale));
        if ($locale === '') {
            throw new \InvalidArgumentException('Locale cannot be empty.');
        }

        $parts = explode('-', $locale);
        $normalized = array_shift($parts);
        $normalized = strtolower((string) $normalized);

        foreach ($parts as $part) {
            $normalized .= '-' . strtoupper($part);
        }

        if (strlen($normalized) > 8) {
            throw new \InvalidArgumentException(sprintf(
                'Locale "%s" is too long for Gedmo translation storage.',
                $normalized,
            ));
        }

        return $normalized;
    }

    private function resolveSourceText(
        object $entity,
        string $entityClass,
        string $field,
        ?string $sourceText,
    ): ?string {
        if (null !== $sourceText) {
            return $sourceText;
        }

        $getter = 'get' . ucfirst($field);
        if (method_exists($entity, $getter)) {
            $value = $entity->$getter();

            return null === $value ? null : (string) $value;
        }

        $reflection = new \ReflectionClass($entityClass);
        $property = $reflection->getProperty($field);
        $value = $property->getValue($entity);

        return null === $value ? null : (string) $value;
    }

    private function resolveTranslationClass(string $entityClass): string
    {
        $reflection = new \ReflectionClass($entityClass);
        $attributes = $reflection->getAttributes(TranslationEntity::class);

        if ([] === $attributes) {
            throw new \LogicException(sprintf(
                'The entity "%s" is not configured with Gedmo TranslationEntity.',
                $entityClass,
            ));
        }

        /** @var TranslationEntity $attribute */
        $attribute = $attributes[0]->newInstance();

        return $attribute->class;
    }

    /**
     * @param array<string, mixed> $previousValues
     * @param array<string, mixed> $currentValues
     *
     * @return array<string, ?string>
     */
    private function extractChangedValues(array $previousValues, array $currentValues): array
    {
        $changedValues = [];

        foreach ($currentValues as $field => $value) {
            $currentValue = null === $value ? null : (string) $value;
            if ($this->normalizeComparableValue($previousValues[$field] ?? null) === $this->normalizeComparableValue($currentValue)) {
                continue;
            }

            $changedValues[$field] = $currentValue;
        }

        return $changedValues;
    }

    private function normalizeComparableValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    /**
     * @param list<string> $fields
     */
    private function translateEntityFields(object $entity, array $fields, string $locale): void
    {
        foreach ($fields as $field) {
            try {
                $translatedValue = $this->translateAndPersist($entity, $field, $locale);
                if ($translatedValue !== null) {
                    $this->applyFieldValue($entity, $field, $translatedValue);
                }
            } catch (\Throwable $exception) {
                $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

                $this->logger->error('Failed to translate lieu page content', [
                    'entity_class' => $entity::class,
                    'entity_id' => $entityId,
                    'field' => $field,
                    'locale' => $locale,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function applyFieldValue(object $entity, string $field, string $value): void
    {
        $setter = 'set' . ucfirst($field);
        if (method_exists($entity, $setter)) {
            $entity->$setter($value);

            return;
        }

        $reflection = new \ReflectionClass($entity::class);
        $property = $reflection->getProperty($field);
        $property->setValue($entity, $value);
    }
}
