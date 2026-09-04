<?php

declare(strict_types=1);

namespace App\Audit\EventSubscriber;

use App\Audit\AuditContext;
use App\Audit\AuditPath;
use App\Audit\Entity\AuditChange;
use App\Audit\Entity\AuditRevision;
use App\Audit\ValueNormalizer;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Activite\OffreActivite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAdministratif;
use App\Pim\Entity\FicheAttributValeur;
use App\Pim\Entity\FicheSiteDiffusion;
use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\LieuTarification;
use App\Pim\Entity\Lieu\PeriodeFermeture;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantAcces;
use App\Pim\Entity\Restaurant\RestaurantPeriodeFermeture;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Entity\Service\ServiceAcces;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\NatureRessource;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsDoctrineListener(event: Events::onFlush)]
final readonly class DoctrineAuditSubscriber
{
    private const IGNORED_FIELDS = [
        'createdAt',
        'updatedAt',
        'version',
        'villeNormalisee',
        'addressFingerprint',
        // Trace technique de la vérification BAN : l'empreinte est binaire
        // (illisible en JSON d'audit) et le reste n'est pas une saisie.
        'banFingerprint',
        'banProposition',
        'banScore',
        'banVerifieLe',
        'banEcart',
        'completenessGlobal',
        'completenessMarketplace',
        'completenessThematicSites',
        'completenessSalesforce',
        'completenessProviderPortal',
        'completenessCalculatedAt',
        'completenessRevision',
    ];

    /** @var list<class-string> */
    private const AUDITED_CLASSES = [
        Fiche::class,
        Lieu::class,
        Activite::class,
        ServiceEvenementiel::class,
        Restaurant::class,
        RestaurantSalle::class,
        RestaurantPeriodeFermeture::class,
        RestaurantAcces::class,
        ServiceAcces::class,
        OffreActivite::class,
        Localisation::class,
        FicheAdministratif::class,
        LieuTarification::class,
        Salle::class,
        PeriodeFermeture::class,
        AccesLieu::class,
        RessourceLieu::class,
        FicheAttributValeur::class,
    ];

    public function __construct(
        private AuditContext $context,
        private ValueNormalizer $normalizer,
        #[Autowire(env: 'bool:AUDIT_ENABLED')]
        private bool $enabled = true,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $entityManager = $event->getObjectManager();
        $uow = $entityManager->getUnitOfWork();
        $operations = [
            'create' => $uow->getScheduledEntityInsertions(),
            'update' => $uow->getScheduledEntityUpdates(),
            'delete' => $uow->getScheduledEntityDeletions(),
        ];
        // Entités déjà chargées ou créées dans ce flush, par classe : elles
        // permettent de rattacher une sous-entité (localisation, tarification,
        // valeur d'attribut) à sa fiche sans requête. Certains chemins
        // (arbitrage d'adresse, vérification au fil de l'eau) mutent la
        // Localisation sans jamais charger l'entité de gamme : la fiche
        // elle-même porte l'association et suffit à rattacher la révision.
        $charges = [];
        foreach ([Lieu::class, Activite::class, ServiceEvenementiel::class, Restaurant::class, Fiche::class] as $class) {
            $charges[$class] = array_values(array_filter($uow->getIdentityMap()[$class] ?? [], static fn (object $entity): bool => $entity instanceof $class));
            foreach ($operations['create'] as $entity) {
                if ($entity instanceof $class) {
                    $charges[$class][] = $entity;
                }
            }
        }
        /** @var array<string, AuditRevision> $revisions */
        $revisions = [];
        $auditContext = $this->context->current();
        foreach ($operations as $operation => $entities) {
            foreach ($entities as $entity) {
                if (
                    $entity instanceof AuditRevision
                    || $entity instanceof AuditChange
                    || !$this->isAudited($entity)
                ) {
                    continue;
                }
                $fiche = $this->resolveFiche($entity, $charges);
                if (null === $fiche) {
                    continue;
                }
                $ficheId = $fiche->idString();
                $rawChangeset =
                    'update' === $operation
                        ? $uow->getEntityChangeSet($entity)
                        : $this->fullChangeSet(
                            $entity,
                            'create' === $operation,
                        );
                /** @var array<string, array{mixed, mixed}> $changeset */
                $changeset = [];
                foreach ($rawChangeset as $field => $pair) {
                    if (is_array($pair)) {
                        $changeset[$field] = [$pair[0], $pair[1]];
                    }
                }
                $metadata = $entityManager->getClassMetadata($entity::class);
                $changes = [];
                foreach ($changeset as $field => $pair) {
                    [$old, $new] = $pair;
                    if (
                        in_array($field, self::IGNORED_FIELDS, true)
                        // Colonnes générées en SQL (ex. Fiche.code, attribué par
                        // trigger à l'INSERT) : Doctrine recharge la valeur sans
                        // rafraîchir le snapshot, elles réapparaissent donc dans
                        // le changeset du flush suivant sans être des
                        // modifications métier.
                        || ('update' === $operation
                            && $metadata->hasField($field)
                            && true === $metadata->getFieldMapping($field)->notUpdatable)
                        || $this->normalizer->same($old, $new)
                    ) {
                        continue;
                    }
                    $changes[] = [
                        AuditPath::pour($entity, $field),
                        $this->normalizer->normalize($old),
                        $this->normalizer->normalize($new),
                    ];
                }
                if ([] === $changes) {
                    continue;
                }
                $revision = $this->revision($revisions, $ficheId, $auditContext['action'] ?? $this->action($entity, $operation, $changeset), $auditContext);
                foreach ($changes as [$path, $old, $new]) {
                    new AuditChange($revision, $path, $old, $new);
                }
            }
        }
        // Les modifications purement collection (sites de diffusion, futures
        // ManyToMany…) ne passent pas par les changesets d'entités : sans ce
        // second passage, elles ne produiraient aucune révision d'audit.
        $collectionOperations = [
            'update' => $uow->getScheduledCollectionUpdates(),
            'delete' => $uow->getScheduledCollectionDeletions(),
        ];
        foreach ($collectionOperations as $operation => $collections) {
            foreach ($collections as $collection) {
                $owner = $collection->getOwner();
                if (
                    null === $owner
                    || !$this->isAudited($owner)
                    // La suppression du propriétaire est déjà auditée avec son
                    // changeset complet : inutile de dupliquer les collections.
                    || $uow->isScheduledForDelete($owner)
                    // La création est couverte par la révision « create » ; les
                    // éléments liés dans le même flush n'ont pas encore d'id.
                    || $uow->isScheduledForInsert($owner)
                ) {
                    continue;
                }
                $mapping = $collection->getMapping();
                $field = $mapping->fieldName;
                if (
                    in_array($field, self::IGNORED_FIELDS, true)
                    // Les collections d'entités elles-mêmes auditées (salles,
                    // ressources, valeurs d'attributs…) sont déjà couvertes
                    // par les insertions/suppressions d'entités : pas de doublon.
                    || $this->isAuditedClass($mapping->targetEntity)
                ) {
                    continue;
                }
                $snapshot = array_values($collection->getSnapshot());
                if ('delete' === $operation) {
                    $current = [];
                } else {
                    $removed = $collection->getDeleteDiff();
                    $inserted = $collection->getInsertDiff();
                    if ([] === $removed && [] === $inserted) {
                        continue;
                    }
                    $current = array_merge(
                        array_values(array_filter(
                            $snapshot,
                            static fn (object $element): bool => !in_array(
                                $element,
                                $removed,
                                true,
                            ),
                        )),
                        array_values($inserted),
                    );
                }
                $old = array_map($this->collectionElement(...), $snapshot);
                $new = array_map($this->collectionElement(...), $current);
                // L'ordre interne d'une collection n'est pas une donnée métier :
                // seule la composition (ajouts/retraits) est auditée.
                sort($old);
                sort($new);
                if ($old === $new) {
                    continue;
                }
                $fiche = $this->resolveFiche($owner, $charges);
                if (null === $fiche) {
                    continue;
                }
                $ficheId = $fiche->idString();
                new AuditChange(
                    $this->revision($revisions, $ficheId, $auditContext['action'] ?? 'update', $auditContext),
                    AuditPath::pour($owner, $field),
                    $old,
                    $new,
                );
            }
        }
        foreach ($revisions as $revision) {
            $entityManager->persist($revision);
            $uow->computeChangeSet(
                $entityManager->getClassMetadata(AuditRevision::class),
                $revision,
            );
            foreach ($revision->changes() as $change) {
                $entityManager->persist($change);
                $uow->computeChangeSet(
                    $entityManager->getClassMetadata(AuditChange::class),
                    $change,
                );
            }
        }
    }

    private function isAudited(object $entity): bool
    {
        return $this->isAuditedClass($entity::class);
    }

    /** @param class-string $class */
    private function isAuditedClass(string $class): bool
    {
        foreach (self::AUDITED_CLASSES as $audited) {
            if (is_a($class, $audited, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fiche à laquelle rattacher la révision d'une entité auditée.
     *
     * @param array<class-string, list<object>> $charges entités chargées ou créées, par classe
     */
    private function resolveFiche(object $entity, array $charges): ?Fiche
    {
        if ($entity instanceof Lieu || $entity instanceof Activite || $entity instanceof ServiceEvenementiel || $entity instanceof Restaurant) {
            return $entity->fiche();
        }
        if ($entity instanceof OffreActivite) {
            return $entity->activite()?->fiche();
        }
        if ($entity instanceof RessourceLieu || $entity instanceof FicheAdministratif) {
            return $entity->fiche();
        }
        if ($entity instanceof Salle || $entity instanceof PeriodeFermeture || $entity instanceof AccesLieu) {
            return $entity->lieu()?->fiche();
        }
        if ($entity instanceof RestaurantSalle || $entity instanceof RestaurantPeriodeFermeture || $entity instanceof RestaurantAcces) {
            return $entity->restaurant()?->fiche();
        }
        if ($entity instanceof ServiceAcces) {
            return $entity->service()?->fiche();
        }
        $fiche = $entity instanceof Fiche ? $entity : ($entity instanceof FicheAttributValeur ? $entity->fiche() : null);
        // Sous-entité singulière (localisation, tarification) ou fiche : la
        // gamme chargée qui la porte donne la fiche.
        foreach ([Lieu::class, Activite::class, ServiceEvenementiel::class, Restaurant::class] as $class) {
            foreach ($charges[$class] ?? [] as $detail) {
                if (!$detail instanceof Lieu && !$detail instanceof Activite && !$detail instanceof ServiceEvenementiel && !$detail instanceof Restaurant) {
                    continue;
                }
                if (
                    ($fiche instanceof Fiche && $detail->fiche() === $fiche)
                    || ($entity instanceof Localisation && $detail->localisation() === $entity)
                    || ($entity instanceof LieuTarification && $detail instanceof Lieu && $detail->tarification() === $entity)
                ) {
                    return $detail->fiche();
                }
            }
        }
        // Localisation mutée sans entité de gamme chargée (arbitrage
        // d'adresse, vérification au fil de l'eau) : la fiche porte
        // directement l'association.
        if ($entity instanceof Localisation) {
            foreach ($charges[Fiche::class] ?? [] as $candidate) {
                if ($candidate instanceof Fiche && $candidate->localisation() === $entity) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Révision de la fiche dans ce flush : créée au premier changement, son
     * action et sa source suivent ensuite l'action la plus significative.
     *
     * @param array<string, AuditRevision>                                                                            $revisions
     * @param array{source: string, actor: string, rolesScopes: list<string>, correlationId: string, action: ?string} $auditContext
     */
    private function revision(array &$revisions, string $ficheId, string $action, array $auditContext): AuditRevision
    {
        $source = in_array($action, ['submission', 'publication', 'archive', 'rejection'], true) ? 'workflow' : $auditContext['source'];
        if (!isset($revisions[$ficheId])) {
            $revisions[$ficheId] = new AuditRevision($ficheId, $action, $source, $auditContext['actor'], $auditContext['rolesScopes'], $auditContext['correlationId']);
        } elseif ($this->actionPriority($action) > $this->actionPriority($revisions[$ficheId]->action())) {
            $revisions[$ficheId]->changeAction($action);
            $revisions[$ficheId]->changeSource($source);
        }

        return $revisions[$ficheId];
    }

    /** @return array<string, array{mixed, mixed}> */
    private function fullChangeSet(object $entity, bool $insertion): array
    {
        $reflection = new \ReflectionObject($entity);
        $changes = [];
        do {
            foreach ($reflection->getProperties() as $property) {
                if (
                    $property->isStatic()
                    || isset($changes[$property->getName()])
                ) {
                    continue;
                }
                $value = $property->isInitialized($entity)
                    ? $property->getValue($entity)
                    : null;
                if ($value instanceof \Doctrine\Common\Collections\Collection) {
                    continue;
                }
                $changes[$property->getName()] = $insertion
                    ? [null, $value]
                    : [$value, null];
            }
        } while (false !== ($reflection = $reflection->getParentClass()));

        return $changes;
    }

    /**
     * Représentation compacte d'un élément de collection : identifiants
     * plutôt que sérialisation d'entités.
     */
    private function collectionElement(object $element): mixed
    {
        if ($element instanceof FicheSiteDiffusion) {
            // Repli sur le code pour un site créé dans le même flush (id
            // auto-incrémenté pas encore attribué au moment du onFlush).
            return $element->site()->id() ?? $element->site()->code();
        }

        return $this->normalizer->normalize($element);
    }

    /** @param array<string, array{mixed, mixed}> $changeset */
    private function action(
        object $entity,
        string $operation,
        array $changeset,
    ): string {
        if ($entity instanceof Fiche && isset($changeset['status'])) {
            $new =
                $changeset['status'][1] instanceof \BackedEnum
                    ? $changeset['status'][1]->value
                    : $changeset['status'][1];

            return match ($new) {
                'en_attente_validation' => 'submission',
                'validee' => 'validation',
                'publiee' => 'publication',
                'archivee' => 'archive',
                'en_cours' => 'rejection',
                default => 'workflow',
            };
        }
        if ($entity instanceof RessourceLieu) {
            $kind =
                NatureRessource::Document === $entity->nature()
                    ? 'document'
                    : 'media';
            if (isset($changeset['publicationStatus'])) {
                return 'document_publication';
            }
            if (isset($changeset['damAssetId']) && 'update' === $operation) {
                return $kind.'_replacement';
            }

            return $operation.'_'.$kind;
        }

        return $operation;
    }

    private function actionPriority(string $action): int
    {
        return match ($action) {
            'submission', 'publication', 'archive', 'rejection' => 100,
            'document_publication' => 80,
            'create', 'delete' => 70,
            default => 'update' === $action ? 0 : 50,
        };
    }
}
