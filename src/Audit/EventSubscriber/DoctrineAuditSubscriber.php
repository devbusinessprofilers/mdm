<?php

declare(strict_types=1);

namespace App\Audit\EventSubscriber;

use App\Audit\AuditContext;
use App\Audit\Entity\AuditChange;
use App\Audit\Entity\AuditRevision;
use App\Audit\ValueNormalizer;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Activite\OffreActivite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAttributValeur;
use App\Pim\Entity\FicheSiteDiffusion;
use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\LieuAdministratif;
use App\Pim\Entity\Lieu\LieuTarification;
use App\Pim\Entity\Lieu\PeriodeFermeture;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantAcces;
use App\Pim\Entity\Restaurant\RestaurantPeriodeFermeture;
use App\Pim\Entity\Restaurant\RestaurantSalle;
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
        OffreActivite::class,
        Localisation::class,
        LieuAdministratif::class,
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
        $lieux = array_values(
            array_filter(
                $uow->getIdentityMap()[Lieu::class] ?? [],
                static fn (object $entity): bool => $entity instanceof Lieu,
            ),
        );
        foreach ($operations['create'] as $entity) {
            if ($entity instanceof Lieu) {
                $lieux[] = $entity;
            }
        }
        $activites = array_values(
            array_filter(
                $uow->getIdentityMap()[Activite::class] ?? [],
                static fn (object $entity): bool => $entity instanceof Activite,
            ),
        );
        foreach ($operations['create'] as $entity) {
            if ($entity instanceof Activite) {
                $activites[] = $entity;
            }
        }
        $services = array_values(
            array_filter(
                $uow->getIdentityMap()[ServiceEvenementiel::class] ?? [],
                static fn (object $entity): bool => $entity instanceof ServiceEvenementiel,
            ),
        );
        foreach ($operations['create'] as $entity) {
            if ($entity instanceof ServiceEvenementiel) {
                $services[] = $entity;
            }
        }
        $restaurants = array_values(
            array_filter(
                $uow->getIdentityMap()[Restaurant::class] ?? [],
                static fn (object $entity): bool => $entity instanceof Restaurant,
            ),
        );
        foreach ($operations['create'] as $entity) {
            if ($entity instanceof Restaurant) {
                $restaurants[] = $entity;
            }
        }
        // Certains chemins (arbitrage d'adresse, vérification au fil de
        // l'eau) mutent la Localisation sans jamais charger l'entité de
        // gamme : la fiche elle-même porte l'association et suffit à
        // rattacher la révision.
        $fiches = array_values(
            array_filter(
                $uow->getIdentityMap()[Fiche::class] ?? [],
                static fn (object $entity): bool => $entity instanceof Fiche,
            ),
        );
        foreach ($operations['create'] as $entity) {
            if ($entity instanceof Fiche) {
                $fiches[] = $entity;
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
                $fiche = $this->resolveFiche(
                    $entity,
                    $lieux,
                    $activites,
                    $services,
                    $restaurants,
                    $fiches,
                );
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
                        $this->path($entity, $field),
                        $this->normalizer->normalize($old),
                        $this->normalizer->normalize($new),
                    ];
                }
                if ([] === $changes) {
                    continue;
                }
                $action = $auditContext['action']
                    ?? $this->action($entity, $operation, $changeset);
                $source = in_array(
                    $action,
                    ['submission', 'publication', 'archive', 'rejection'],
                    true,
                )
                    ? 'workflow'
                    : $auditContext['source'];
                if (!isset($revisions[$ficheId])) {
                    $revisions[$ficheId] = new AuditRevision(
                        $ficheId,
                        $action,
                        $source,
                        $auditContext['actor'],
                        $auditContext['rolesScopes'],
                        $auditContext['correlationId'],
                    );
                } elseif (
                    $this->actionPriority($action) >
                    $this->actionPriority($revisions[$ficheId]->action())
                ) {
                    $revisions[$ficheId]->changeAction($action);
                    $revisions[$ficheId]->changeSource($source);
                }
                foreach ($changes as [$path, $old, $new]) {
                    new AuditChange($revisions[$ficheId], $path, $old, $new);
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
                $fiche = $this->resolveFiche(
                    $owner,
                    $lieux,
                    $activites,
                    $services,
                    $restaurants,
                    $fiches,
                );
                if (null === $fiche) {
                    continue;
                }
                $ficheId = $fiche->idString();
                $action = $auditContext['action'] ?? 'update';
                $source = in_array(
                    $action,
                    ['submission', 'publication', 'archive', 'rejection'],
                    true,
                )
                    ? 'workflow'
                    : $auditContext['source'];
                if (!isset($revisions[$ficheId])) {
                    $revisions[$ficheId] = new AuditRevision(
                        $ficheId,
                        $action,
                        $source,
                        $auditContext['actor'],
                        $auditContext['rolesScopes'],
                        $auditContext['correlationId'],
                    );
                } elseif (
                    $this->actionPriority($action) >
                    $this->actionPriority($revisions[$ficheId]->action())
                ) {
                    $revisions[$ficheId]->changeAction($action);
                    $revisions[$ficheId]->changeSource($source);
                }
                new AuditChange(
                    $revisions[$ficheId],
                    $this->path($owner, $field),
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
     * @param list<Lieu>     $lieux
     * @param list<Activite> $activites
     * @param list<ServiceEvenementiel> $services
     * @param list<Restaurant> $restaurants
     * @param list<Fiche> $fiches
     */
    private function resolveFiche(
        object $entity,
        array $lieux,
        array $activites,
        array $services,
        array $restaurants,
        array $fiches,
    ): ?Fiche {
        if (
            $entity instanceof Lieu
            || $entity instanceof Activite
            || $entity instanceof ServiceEvenementiel
            || $entity instanceof Restaurant
        ) {
            return $entity->fiche();
        }
        if ($entity instanceof OffreActivite) {
            return $entity->activite()?->fiche();
        }
        if ($entity instanceof RessourceLieu) {
            return $entity->fiche();
        }
        if (
            $entity instanceof Salle
            || $entity instanceof PeriodeFermeture
            || $entity instanceof AccesLieu
        ) {
            return $entity->lieu()?->fiche();
        }
        if (
            $entity instanceof RestaurantSalle
            || $entity instanceof RestaurantPeriodeFermeture
            || $entity instanceof RestaurantAcces
        ) {
            return $entity->restaurant()?->fiche();
        }
        $fiche =
            $entity instanceof Fiche
                ? $entity
                : ($entity instanceof FicheAttributValeur
                    ? $entity->fiche()
                    : null);
        foreach ($lieux as $lieu) {
            if (
                ($fiche instanceof Fiche && $lieu->fiche() === $fiche)
                || ($entity instanceof Localisation
                    && $lieu->localisation() === $entity)
                || ($entity instanceof LieuAdministratif
                    && $lieu->administratif() === $entity)
                || ($entity instanceof LieuTarification
                    && $lieu->tarification() === $entity)
            ) {
                return $lieu->fiche();
            }
        }
        foreach ($activites as $activite) {
            if (
                ($fiche instanceof Fiche && $activite->fiche() === $fiche)
                || ($entity instanceof Localisation
                    && $activite->localisation() === $entity)
            ) {
                return $activite->fiche();
            }
        }
        foreach ($services as $service) {
            if (
                ($fiche instanceof Fiche && $service->fiche() === $fiche)
                || ($entity instanceof Localisation && $service->localisation() === $entity)
            ) {
                return $service->fiche();
            }
        }
        foreach ($restaurants as $restaurant) {
            if (
                ($fiche instanceof Fiche && $restaurant->fiche() === $fiche)
                || ($entity instanceof Localisation
                    && $restaurant->localisation() === $entity)
            ) {
                return $restaurant->fiche();
            }
        }
        // Localisation mutée sans entité de gamme chargée (arbitrage
        // d'adresse, vérification au fil de l'eau) : la fiche porte
        // directement l'association.
        if ($entity instanceof Localisation) {
            foreach ($fiches as $candidate) {
                if ($candidate->localisation() === $entity) {
                    return $candidate;
                }
            }
        }

        return null;
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

    private function path(object $entity, string $field): string
    {
        return match (true) {
            $entity instanceof Fiche => match ($field) {
                'label' => 'nom',
                'status' => 'workflow.status',
                'siteSelections' => 'sitesDiffusion',
                default => 'fiche.'.$field,
            },
            $entity instanceof Lieu => 'lieu.'.$field,
            $entity instanceof Activite => 'activite.'.$field,
            $entity instanceof ServiceEvenementiel => 'service.'.$field,
            $entity instanceof Restaurant => 'restaurant.'.$field,
            $entity instanceof OffreActivite => sprintf(
                '%s[%s].%s',
                $entity->type()->value.'s',
                $entity->id(),
                $field,
            ),
            $entity instanceof Localisation => 'localisation.'.$field,
            $entity instanceof LieuAdministratif => 'administratif.'.$field,
            $entity instanceof LieuTarification => 'tarification.'.$field,
            $entity instanceof Salle => sprintf(
                'salles[%s].%s',
                $entity->id(),
                $field,
            ),
            $entity instanceof PeriodeFermeture => sprintf(
                'fermetures[%s].%s',
                $entity->id(),
                $field,
            ),
            $entity instanceof AccesLieu => sprintf(
                'acces[%s].%s',
                $entity->id(),
                $field,
            ),
            $entity instanceof RestaurantSalle => sprintf(
                'salles[%s].%s',
                $entity->id(),
                $field,
            ),
            $entity instanceof RestaurantPeriodeFermeture => sprintf(
                'fermetures[%s].%s',
                $entity->id(),
                $field,
            ),
            $entity instanceof RestaurantAcces => sprintf(
                'acces[%s].%s',
                $entity->id(),
                $field,
            ),
            $entity instanceof RessourceLieu => sprintf(
                '%s[%s].%s',
                NatureRessource::Document === $entity->nature()
                    ? 'documents'
                    : 'medias',
                $entity->id(),
                $field,
            ),
            $entity instanceof FicheAttributValeur => sprintf(
                'attributs[%s].%s',
                $entity->attributeCode(),
                $field,
            ),
            default => $field,
        };
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
