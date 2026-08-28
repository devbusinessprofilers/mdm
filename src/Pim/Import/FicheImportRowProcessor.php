<?php

declare(strict_types=1);

namespace App\Pim\Import;

use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Dto\ConvertedValue;
use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Import\Dto\RowAction;
use App\Pim\Import\Dto\RowError;
use App\Pim\Import\Dto\RowOutcome;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Pim\Repository\ValeurAttributRepository;
use App\Pim\Validation\ValidationGroups;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class FicheImportRowProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FicheRepository $fiches,
        private FicheImportSchemaRegistry $schemas,
        private RowConverter $converter,
        private ValidatorInterface $validator,
        private ValeurAttributRepository $prestataires,
        private SiteDiffusionRepository $sitesDiffusion,
        private FicheTranslationScheduler $translationScheduler,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    /**
     * Traite une ligne dans la transaction du message courant : les lignes valides
     * sont flushées, les lignes invalides déclenchent un clear() de l'EntityManager
     * pour purger les mutations en mémoire (l'appelant doit recharger ses entités).
     * Le fichier d'export fait foi : cellules vides = champs vidés, libellés LOV
     * acceptés (voir RowConverter).
     */
    public function process(TypeFiche $type, RawCsvRow $row): RowOutcome
    {
        $schema = $this->schemas->for($type);
        $converted = $this->converter->convert($schema, $row);
        $errors = $converted->errors;

        if (null !== $converted->code) {
            $fiche = $this->fiches->findOneByTypeAndCode($type, $converted->code);
            $aggregate = $fiche instanceof Fiche ? $schema->findAggregateByFiche($fiche) : null;
            if (null === $aggregate) {
                $errors[] = new RowError($row->lineNumber, 'code', sprintf('Aucune fiche %s avec le code %d.', $type->value, $converted->code));

                return RowOutcome::failed($errors);
            }
            $action = RowAction::Updated;
        } else {
            $hasLabel = [] !== array_filter($converted->fields, static fn (ConvertedValue $field): bool => 'label' === $field->column->header && !$field->clear);
            if (!$hasLabel) {
                $errors[] = new RowError($row->lineNumber, 'label', 'Le libellé est obligatoire à la création.');

                return RowOutcome::failed($errors);
            }
            $aggregate = $schema->createAggregate();
            $action = RowAction::Created;
        }

        if ([] !== $errors) {
            return RowOutcome::failed($errors);
        }

        $fiche = $schema->ficheOf($aggregate);
        $this->applyFields($schema->type(), $aggregate, $fiche, $converted->fields, $row->lineNumber, $errors);
        $this->applyCollections($schema->collections(), $aggregate, $converted->collections, $row->lineNumber, $errors);

        if ([] === $errors) {
            foreach ($this->validator->validate($aggregate, null, [ValidationGroups::DRAFT]) as $violation) {
                $errors[] = new RowError($row->lineNumber, $violation->getPropertyPath(), (string) $violation->getMessage());
            }
        }

        if ([] !== $errors) {
            // Purge les mutations non flushées (fiche existante modifiée en mémoire).
            $this->entityManager->clear();

            return RowOutcome::failed($errors);
        }

        $this->entityManager->persist($aggregate);
        $this->translationScheduler->schedule($fiche);
        $this->outbox->enqueue(new IndexFiche($fiche->idString()));
        $this->entityManager->flush();

        return new RowOutcome($action);
    }

    /**
     * @param list<ConvertedValue> $fields
     * @param list<RowError>       $errors
     */
    private function applyFields(TypeFiche $type, object $aggregate, Fiche $fiche, array $fields, int $line, array &$errors): void
    {
        $localisation = null;

        foreach ($fields as $field) {
            $column = $field->column;
            if ('label' === $column->header && $field->clear) {
                continue;
            }

            $value = $field->value;
            if (ColumnKind::Prestataire === $column->kind && !$field->clear) {
                // Le fichier d'export porte le libellé du prestataire, un code est accepté aussi.
                $value = $this->prestataires->findPrestataireByCode(strtoupper((string) $field->value))
                    ?? $this->prestataires->findPrestataireByLabel((string) $field->value);
                if (null === $value) {
                    $message = sprintf('Prestataire inconnu : « %s ».', trim((string) $field->value));
                    $suggestion = SuggestionProche::trouver((string) $field->value, $this->prestataires->listePrestataireLabels());
                    if (null !== $suggestion) {
                        $message .= sprintf(' Vouliez-vous dire « %s » ?', $suggestion);
                    }
                    $errors[] = new RowError($line, $column->header, $message);
                    continue;
                }
            }
            if (ColumnKind::SitesDiffusion === $column->kind) {
                /** @var list<string> $libelles liste produite par RowConverter */
                $libelles = $field->clear || !is_array($value) ? [] : array_values($value);
                // Cellule réduite à des séparateurs : seule la sentinelle NULL
                // exprime « remettre aux sites obligatoires seuls ».
                if (!$field->clear && [] === $libelles) {
                    continue;
                }
                $this->applySitesDiffusion($fiche, $libelles, $line, $column->header, $errors);
                continue;
            }

            $target = $aggregate;
            if ('localisation' === $column->targetPath) {
                $localisation ??= $fiche->localisation() ?? new Localisation();
                $target = $localisation;
            } elseif (null !== $column->targetPath) {
                $target = $this->resolveTargetPath($aggregate, $column->targetPath);
            }

            $this->callSetter($target, $column->setter(), $value, $line, $column->header, $errors);
        }

        if ($localisation instanceof Localisation && $localisation !== $fiche->localisation()) {
            $this->callSetter($aggregate, 'changeLocalisation', $localisation, $line, 'localisation', $errors);
        }
    }

    /**
     * Remplace la sélection de sites de diffusion : libellés (ou codes) résolus
     * sur le référentiel, sites obligatoires réappliqués, sans transition de
     * workflow (la visibilité est une mise à jour technique).
     *
     * @param list<string>   $libelles
     * @param list<RowError> $errors
     */
    private function applySitesDiffusion(Fiche $fiche, array $libelles, int $line, string $column, array &$errors): void
    {
        $referentiel = $this->sitesDiffusion->findActifsOrdonnes();
        $parCle = [];
        foreach ($referentiel as $site) {
            $parCle[mb_strtolower($site->label())] = $site;
            $parCle[mb_strtolower($site->code())] = $site;
        }

        $retenus = [];
        foreach ($libelles as $libelle) {
            $site = $parCle[mb_strtolower($libelle)] ?? null;
            if (null === $site) {
                $message = sprintf('Site de diffusion inconnu : « %s ».', $libelle);
                $candidats = [];
                foreach ($referentiel as $connu) {
                    $candidats[] = $connu->label();
                    $candidats[] = $connu->code();
                }
                $suggestion = SuggestionProche::trouver($libelle, $candidats);
                if (null !== $suggestion) {
                    $message .= sprintf(' Vouliez-vous dire « %s » ?', $suggestion);
                }
                $errors[] = new RowError($line, $column, $message);

                return;
            }
            $retenus[$site->code()] = $site;
        }
        foreach ($referentiel as $site) {
            if ($site->obligatoire()) {
                $retenus[$site->code()] = $site;
            }
        }

        $fiche->preserveWorkflowDuring(static function () use ($fiche, $retenus): void {
            $fiche->replaceSiteDiffusion(array_values($retenus));
        });
    }

    /**
     * @param list<\App\Pim\Import\Schema\CollectionSchema>  $schemas
     * @param array<string, list<list<ConvertedValue>>>      $collections
     * @param list<RowError>                                 $errors
     */
    private function applyCollections(array $schemas, object $aggregate, array $collections, int $line, array &$errors): void
    {
        foreach ($schemas as $collectionSchema) {
            if (!array_key_exists($collectionSchema->prefix, $collections)) {
                continue;
            }

            $current = $aggregate->{$collectionSchema->getter}();
            if (!$current instanceof \Doctrine\Common\Collections\Collection) {
                throw new \LogicException(sprintf('%s::%s() doit retourner une Collection.', $aggregate::class, $collectionSchema->getter));
            }
            foreach ($current->toArray() as $existing) {
                $current->removeElement($existing);
            }

            foreach ($collections[$collectionSchema->prefix] as $position => $values) {
                $entry = new ($collectionSchema->entryClass)();
                foreach ($values as $value) {
                    $this->callSetter($entry, $value->column->setter(), $value->value, $line, $collectionSchema->header($position + 1, $value->column), $errors);
                }
                if (method_exists($entry, 'changePosition')) {
                    $entry->changePosition($position);
                }
                $this->callSetter($aggregate, $collectionSchema->adder, $entry, $line, $collectionSchema->prefix, $errors);
            }
        }
    }

    /**
     * @param list<RowError> $errors
     */
    private function callSetter(object $target, string $setter, mixed $value, int $line, string $column, array &$errors): void
    {
        if (!method_exists($target, $setter)) {
            throw new \LogicException(sprintf('Méthode %s absente sur %s (colonne %s).', $setter, $target::class, $column));
        }

        try {
            $target->{$setter}($value);
        } catch (\DomainException|\InvalidArgumentException|\TypeError $exception) {
            $errors[] = new RowError($line, $column, $exception->getMessage());
        }
    }

    private function resolveTargetPath(object $aggregate, string $path): object
    {
        if (!method_exists($aggregate, $path)) {
            throw new \LogicException(sprintf('Chemin %s absent sur %s.', $path, $aggregate::class));
        }

        $target = $aggregate->{$path}();
        if (!is_object($target)) {
            throw new \LogicException(sprintf('%s::%s() doit retourner un objet.', $aggregate::class, $path));
        }

        return $target;
    }
}
