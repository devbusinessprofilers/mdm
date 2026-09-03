<?php

declare(strict_types=1);

namespace App\Ocr\Service;

use App\Enrichment\Service\FicheTranslationScheduler;
use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Entity\OcrSuggestion;
use App\Ocr\Enum\ExtractionStatus;
use App\Ocr\Enum\SuggestionStatus;
use App\Pim\Entity\Localisation;
use App\Pim\Import\Dto\ConvertedValue;
use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Import\RowConverter;
use App\Pim\Import\Schema\CollectionSchema;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use App\Pim\Message\CalculateFicheCompleteness;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\ValeurAttributRepository;
use App\Pim\Validation\ValidationGroups;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class OcrSuggestionApplier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FicheImportSchemaRegistry $schemas,
        private RowConverter $converter,
        private ValeurAttributRepository $prestataires,
        private ValidatorInterface $validator,
        private OutboxPublisherInterface $outbox,
        private FicheTranslationScheduler $translationScheduler,
    ) {
    }

    /**
     * @param array<string, array{value: mixed, accept: bool, reject: bool}> $review indexed by suggestion id
     */
    public function apply(DocumentExtraction $extraction, int $expectedVersion, array $review, string $actor): void
    {
        if (!in_array($extraction->status(), [ExtractionStatus::Ready, ExtractionStatus::PartiallyReviewed], true)) {
            throw new OcrReviewException(['Cette extraction ne peut plus être modifiée.']);
        }
        if ($extraction->fiche()->version() !== $expectedVersion) {
            throw new OcrReviewException(['La fiche a changé depuis l’ouverture de cette page. Rechargez-la avant de sauvegarder.']);
        }
        $schema = $this->schemas->for($extraction->fiche()->type());
        $aggregate = $schema->findAggregateByFiche($extraction->fiche());
        if (null === $aggregate) {
            throw new OcrReviewException(['La fiche métier est introuvable.']);
        }

        $byId = [];
        foreach ($extraction->suggestions() as $suggestion) {
            $byId[$suggestion->id()] = $suggestion;
        }
        $errors = [];
        $accepted = [];
        $decisions = [];
        foreach ($review as $id => $input) {
            $suggestion = $byId[$id] ?? null;
            if (!$suggestion instanceof OcrSuggestion || !$suggestion->isPending()) {
                continue;
            }
            if ($input['accept'] && $input['reject']) {
                $errors[] = sprintf('%s : Valider et Refuser sont exclusifs.', $suggestion->label());
                continue;
            }
            if (!$input['accept'] && !$input['reject']) {
                continue;
            }
            $decisions[] = [$suggestion, $input];
            if ($input['accept']) {
                $accepted[$suggestion->fieldPath()] = $input['value'];
            }
        }
        if ([] !== $errors) {
            throw new OcrReviewException($errors);
        }

        $cells = [];
        $collectionValues = [];
        foreach ($accepted as $path => $value) {
            if (str_starts_with($path, 'collections.')) {
                $collectionValues[substr($path, 12)] = $value;
                continue;
            }
            $column = $this->columnForPath($schema->ficheColumns(), $path);
            if (null === $column) {
                $errors[] = $path.' : champ OCR inconnu.';
                continue;
            }
            $cells[$column->header] = $this->raw($value, $column);
        }
        foreach ($schema->collections() as $collection) {
            if (!array_key_exists($collection->prefix, $collectionValues)) {
                continue;
            }
            $rows = $collectionValues[$collection->prefix];
            if (is_string($rows)) {
                try {
                    $rows = json_decode($rows, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $rows = null;
                }
            }
            if (!is_array($rows) || !array_is_list($rows)) {
                $errors[] = sprintf('%s : tableau JSON attendu.', $collection->prefix);
                continue;
            }
            if (count($rows) > $collection->max) {
                $errors[] = sprintf('%s : %d lignes maximum.', $collection->prefix, $collection->max);
                continue;
            }
            foreach ($rows as $position => $row) {
                if (!is_array($row)) {
                    $errors[] = sprintf('%s ligne %d invalide.', $collection->prefix, $position + 1);
                    continue;
                }
                foreach ($collection->columns as $column) {
                    if (array_key_exists($column->target, $row)) {
                        $cells[$collection->header($position + 1, $column)] = $this->raw($row[$column->target], $column);
                    }
                }
            }
        }
        if ([] !== $errors) {
            throw new OcrReviewException($errors);
        }
        $converted = $this->converter->convert($schema, new RawCsvRow(1, $cells));
        if ([] !== $converted->errors) {
            throw new OcrReviewException(array_map(static fn ($error): string => $error->column.' : '.$error->message, $converted->errors));
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->applyFields($aggregate, $extraction, $converted->fields);
            $this->applyCollections($aggregate, $schema->collections(), $converted->collections, array_keys($collectionValues));
            if ([] !== $accepted) {
                // Collection removals performed through Doctrine's Collection do not
                // necessarily call an aggregate mutator. Keep the workflow/version
                // invariant identical for every accepted OCR value, including an
                // accepted replacement by an empty collection.
                $extraction->fiche()->markChanged();
            }
            foreach ($this->validator->validate($aggregate, null, [ValidationGroups::DRAFT]) as $violation) {
                $errors[] = $violation->getPropertyPath().' : '.$violation->getMessage();
            }
            if ([] !== $errors) {
                throw new OcrReviewException($errors);
            }
            foreach ($decisions as [$suggestion, $input]) {
                $suggestion->correct($input['value']);
                $suggestion->decide($input['accept'] ? SuggestionStatus::Accepted : SuggestionStatus::Rejected, $actor);
            }
            $extraction->refreshReviewStatus();
            if ([] !== $accepted) {
                $this->translationScheduler->schedule($extraction->fiche());
                $this->outbox->enqueue(new IndexFiche($extraction->fiche()->idString()));
                $this->outbox->enqueue(new CalculateFicheCompleteness($extraction->fiche()->idString()));
            }
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $error) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();
            if ($error instanceof OcrReviewException) {
                throw $error;
            }
            throw new OcrReviewException([$error->getMessage()]);
        }
    }

    /** @param list<ConvertedValue> $fields */
    private function applyFields(object $aggregate, DocumentExtraction $extraction, array $fields): void
    {
        $localisation = null;
        foreach ($fields as $field) {
            $column = $field->column;
            $value = $field->value;
            if (ColumnKind::Prestataire === $column->kind && !$field->clear) {
                $value = $this->prestataires->findPrestataireByCode((string) $value);
                if (null === $value) {
                    throw new OcrReviewException([$column->header.' : code prestataire inconnu.']);
                }
            }
            $target = $aggregate;
            if ('localisation' === $column->targetPath) {
                $localisation ??= $extraction->fiche()->localisation() ?? new Localisation();
                $target = $localisation;
            } elseif (null !== $column->targetPath) {
                $getter = $column->targetPath;
                $target = method_exists($aggregate, $getter) ? $aggregate->{$getter}() : null;
                if (!is_object($target)) {
                    throw new OcrReviewException([$column->header.' : bloc PIM introuvable.']);
                }
            }
            $setter = $column->setter();
            if (!method_exists($target, $setter)) {
                throw new OcrReviewException([$column->header.' : méthode métier absente.']);
            }
            $target->{$setter}($value);
        }
        if ($localisation instanceof Localisation && $localisation !== $extraction->fiche()->localisation()) {
            if (!method_exists($aggregate, 'changeLocalisation')) {
                throw new OcrReviewException(['localisation : méthode métier absente.']);
            }
            $aggregate->changeLocalisation($localisation);
        }
    }

    /**
     * @param list<CollectionSchema>                    $schemas
     * @param array<string, list<list<ConvertedValue>>> $values
     * @param list<string>                              $touched
     */
    private function applyCollections(object $aggregate, array $schemas, array $values, array $touched): void
    {
        foreach ($schemas as $schema) {
            if (!in_array($schema->prefix, $touched, true)) {
                continue;
            }
            $current = $aggregate->{$schema->getter}();
            if (!$current instanceof Collection) {
                throw new OcrReviewException([$schema->prefix.' : collection métier introuvable.']);
            }
            foreach ($current->toArray() as $existing) {
                $current->removeElement($existing);
            }
            foreach ($values[$schema->prefix] ?? [] as $position => $row) {
                $entry = new ($schema->entryClass)();
                foreach ($row as $field) {
                    $entry->{$field->column->setter()}($field->value);
                }
                if (method_exists($entry, 'changePosition')) {
                    $entry->changePosition($position);
                }
                $aggregate->{$schema->adder}($entry);
            }
        }
    }

    /** @param list<ColumnDefinition> $columns */
    private function columnForPath(array $columns, string $path): ?ColumnDefinition
    {
        foreach ($columns as $column) {
            $candidate = 'label' === $column->target ? 'fiche.label' : (null === $column->targetPath ? $column->target : $column->targetPath.'.'.$column->target);
            if ($candidate === $path) {
                return $column;
            }
        }

        return null;
    }

    private function raw(mixed $value, ColumnDefinition $column): string
    {
        if (null === $value || '' === $value) {
            return RowConverter::NULL_SENTINEL;
        }
        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }
        if (is_array($value)) {
            return implode('|', array_map('strval', $value));
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }
}
