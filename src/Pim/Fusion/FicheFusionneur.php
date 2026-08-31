<?php

declare(strict_types=1);

namespace App\Pim\Fusion;

use App\Account\Entity\User;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\NatureRessource;
use App\Pim\Import\Schema\CollectionSchema;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Pim\Service\PhotoUsageCatalog;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fusionne deux fiches d'une même gamme : la survivante reçoit les valeurs
 * retenues champ par champ (choix fait sur l'écran de comparaison) et l'union
 * dédoublonnée des collections ; l'absorbée est archivée avec la trace de la
 * survivante (markMergedInto). Les setters métier font foi : la survivante
 * redescend En cours (contenu fusionné = contenu à revalider), l'aval
 * (IndexFiche des deux fiches) déclenche réindexation, garde photos, retrait
 * marketplace de l'absorbée et resynchronisations.
 */
final readonly class FicheFusionneur
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FicheImportSchemaRegistry $schemas,
        private FusionChampsCatalogue $catalogue,
        private FusionValeurLecteur $lecteur,
        private FicheAffiliationRepository $affiliations,
        private SiteDiffusionRepository $sitesDiffusion,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    /**
     * @param list<string> $champsDepuisAbsorbee en-têtes de colonnes du
     *                                           catalogue dont la valeur retenue est celle de la fiche absorbée
     *
     * @throws \DomainException si la fusion est impossible ou qu'un setter refuse une valeur
     */
    public function fusionner(Fiche $survivante, Fiche $absorbee, array $champsDepuisAbsorbee, User $acteur): void
    {
        if ($survivante->id()->equals($absorbee->id())) {
            throw new \DomainException('Choisissez deux fiches distinctes.');
        }
        $type = $survivante->type();
        if ($absorbee->type() !== $type) {
            throw new \DomainException('Seules deux fiches d’une même gamme peuvent être fusionnées.');
        }
        $schema = $this->schemas->for($type);
        $aggregatSurvivant = $schema->findAggregateByFiche($survivante)
            ?? throw new \DomainException('La fiche survivante n’a pas de détail de gamme.');
        $aggregatAbsorbe = $schema->findAggregateByFiche($absorbee)
            ?? throw new \DomainException('La fiche absorbée n’a pas de détail de gamme.');

        try {
            $this->entityManager->wrapInTransaction(function () use ($survivante, $absorbee, $aggregatSurvivant, $aggregatAbsorbe, $schema, $type, $champsDepuisAbsorbee, $acteur): void {
                // Le lien Restaurant↔Lieu d'abord : son transfert impose un
                // flush intermédiaire (colonne lieu_id unique), à faire avant
                // toute autre mutation.
                $fichesLiees = $this->transfererLienLieuRestaurant($survivante, $absorbee, $aggregatSurvivant, $aggregatAbsorbe);

                $erreurs = [];
                $this->appliquerChampsChoisis($type, $aggregatSurvivant, $survivante, $aggregatAbsorbe, $absorbee, $champsDepuisAbsorbee, $erreurs);
                $this->unionSelectionsMultiples($type, $aggregatSurvivant, $survivante, $aggregatAbsorbe, $absorbee);
                $this->unionRessources($aggregatSurvivant, $survivante, $absorbee);
                $this->unionCollections($schema->collections(), $aggregatSurvivant, $aggregatAbsorbe, $erreurs);
                $this->unionAffiliations($survivante, $absorbee, $acteur);
                if ([] !== $erreurs) {
                    throw new \DomainException(implode(' ', array_unique($erreurs)));
                }

                $absorbee->markMergedInto($survivante->id(), $acteur->id());

                $this->outbox->enqueue(new IndexFiche($survivante->idString()));
                $this->outbox->enqueue(new IndexFiche($absorbee->idString()));
                foreach ($fichesLiees as $ficheLiee) {
                    $this->outbox->enqueue(new IndexFiche($ficheLiee->idString()));
                }
                // L'absorbée et les fiches tierces liées (Lieu↔Restaurant) ne
                // doivent pas repasser En cours au PreUpdate de leur ligne
                // détail ; la survivante, elle, assume son markChanged.
                Fiche::preserveWorkflowsDuring(
                    [$absorbee, ...$fichesLiees],
                    function (): void {
                        $this->entityManager->flush();
                    },
                );
            });
        } catch (\Throwable $exception) {
            // Purge les mutations en mémoire : rien de partiel ne doit
            // pouvoir être flushé par un appelant ultérieur.
            $this->entityManager->clear();
            throw $exception;
        }
    }

    /**
     * @param list<string> $champsDepuisAbsorbee
     * @param list<string> $erreurs
     */
    private function appliquerChampsChoisis(
        \App\Pim\Enum\TypeFiche $type,
        object $aggregatSurvivant,
        Fiche $survivante,
        object $aggregatAbsorbe,
        Fiche $absorbee,
        array $champsDepuisAbsorbee,
        array &$erreurs,
    ): void {
        $localisation = null;
        foreach ($this->catalogue->champsComparables($type) as $column) {
            if (!in_array($column->header, $champsDepuisAbsorbee, true)) {
                continue;
            }
            $valeur = $this->lecteur->native($column, $aggregatAbsorbe, $absorbee);

            $cible = $aggregatSurvivant;
            if ('localisation' === $column->targetPath) {
                $localisation ??= $survivante->localisation() ?? new Localisation();
                $cible = $localisation;
            } elseif (FusionChampsCatalogue::CIBLE_FICHE === $column->targetPath) {
                $cible = $survivante;
            } elseif (null !== $column->targetPath) {
                $cible = $aggregatSurvivant->{$column->targetPath}();
            }

            $this->callSetter($cible, $column->setter(), $valeur, $column->header, $erreurs);
        }
        if ($localisation instanceof Localisation && $localisation !== $survivante->localisation()) {
            $this->callSetter($aggregatSurvivant, 'changeLocalisation', $localisation, 'localisation', $erreurs);
        }
    }

    /** Union des sélections multiples : LOV multi (codes sur l'agrégat) et sites de diffusion. */
    private function unionSelectionsMultiples(
        \App\Pim\Enum\TypeFiche $type,
        object $aggregatSurvivant,
        Fiche $survivante,
        object $aggregatAbsorbe,
        Fiche $absorbee,
    ): void {
        foreach ($this->catalogue->champsUnion($type) as $column) {
            if (ColumnKind::LovMulti === $column->kind) {
                $actuels = $this->lecteur->native($column, $aggregatSurvivant, $survivante);
                $ajouts = $this->lecteur->native($column, $aggregatAbsorbe, $absorbee);
                $union = array_values(array_unique(array_merge(
                    is_array($actuels) ? $actuels : [],
                    is_array($ajouts) ? $ajouts : [],
                )));
                if ($union !== ($actuels ?? [])) {
                    $aggregatSurvivant->{$column->setter()}($union);
                }
            }
        }

        $manquants = array_diff($absorbee->siteDiffusionIds(), $survivante->siteDiffusionIds());
        if ([] !== $manquants) {
            /** @var list<SiteDiffusion> $sites */
            $sites = $this->sitesDiffusion->findBy(['id' => array_values($manquants)]);
            $survivante->ajouterSitesDiffusion($sites);
        }
    }

    /**
     * Clone (jamais déplacé : l'absorbée reste consultable et la fusion
     * réversible) les photos et documents absents de la survivante, identifiés
     * par (asset DAM, nature). Les ressources rattachées à une salle restent
     * sur l'absorbée : la correspondance salle à salle n'est pas décidable.
     */
    private function unionRessources(object $aggregatSurvivant, Fiche $survivante, Fiche $absorbee): void
    {
        $presents = [];
        $position = -1;
        $principalePresente = false;
        foreach ($survivante->resources() as $resource) {
            $presents[$resource->damAssetId().'|'.$resource->nature()->value] = true;
            $position = max($position, $resource->position());
            $principalePresente = $principalePresente
                || (NatureRessource::Photo === $resource->nature() && PhotoUsageCatalog::PRINCIPALE === $resource->usage());
        }

        foreach ($absorbee->resources() as $resource) {
            if (null !== $resource->salle() || null !== $resource->restaurantSalle()) {
                continue;
            }
            if (isset($presents[$resource->damAssetId().'|'.$resource->nature()->value])) {
                continue;
            }
            $clone = new RessourceLieu();
            $clone->changeDamAssetId($resource->damAssetId());
            $clone->changeNature($resource->nature());
            // Deux photos principales sont impossibles : celle de la
            // survivante prime, la clonée redescend en catégorie neutre.
            $usage = $resource->usage();
            if (NatureRessource::Photo === $resource->nature() && PhotoUsageCatalog::PRINCIPALE === $usage && $principalePresente) {
                $usage = PhotoUsageCatalog::DEFAUT;
            }
            $clone->changeUsage($usage);
            $clone->changeLegende($resource->legende());
            $clone->changeSource($resource->source());
            $clone->changeKeywords($resource->keywords());
            $crop = $resource->crop();
            if (null !== $crop) {
                $clone->changeCrop($crop['x'], $crop['y'], $crop['width'], $crop['height']);
            }
            $clone->changeRotation($resource->rotation());
            $clone->changeRightsExpiresAt($resource->rightsExpiresAt());
            if ($resource->rightsGranted() && null !== $resource->rightsGrantedBy()) {
                try {
                    $clone->grantRights($resource->rightsGrantedBy());
                } catch (\DomainException) {
                    // Droits expirés : le clone repart non validé.
                }
            }
            if (NatureRessource::Document === $resource->nature() && null !== $resource->documentUsage()) {
                $clone->configureDocument($resource->documentUsage());
            }
            $clone->changePosition(++$position);
            // Adder conventionnel des quatre gammes (dynamique : les agrégats
            // ne partagent pas d'interface) ; les gardes des entités passent,
            // le clone n'est rattaché ni à un lieu ni à une salle.
            $erreursAjout = [];
            $this->callSetter($aggregatSurvivant, 'addRessource', $clone, 'photos', $erreursAjout);
            if ([] !== $erreursAjout) {
                throw new \DomainException(implode(' ', $erreursAjout));
            }
            $this->entityManager->persist($clone);
            $presents[$clone->damAssetId().'|'.$clone->nature()->value] = true;
        }
    }

    /**
     * Union générique des collections des schémas (salles, périodes de
     * fermeture, accès, offres) : toute entrée absorbée sans équivalent —
     * signature = valeurs des colonnes du schéma — est clonée à la suite.
     *
     * @param list<CollectionSchema> $collections
     * @param list<string>           $erreurs
     */
    private function unionCollections(array $collections, object $aggregatSurvivant, object $aggregatAbsorbe, array &$erreurs): void
    {
        foreach ($collections as $collection) {
            $existantes = $aggregatSurvivant->{$collection->getter}();
            $signatures = [];
            $position = count($existantes) - 1;
            foreach ($existantes as $entree) {
                $signatures[$this->signature($collection, $entree)] = true;
            }
            foreach ($aggregatAbsorbe->{$collection->getter}() as $entree) {
                $signature = $this->signature($collection, $entree);
                if (isset($signatures[$signature])) {
                    continue;
                }
                $clone = new ($collection->entryClass)();
                foreach ($collection->columns as $column) {
                    $this->callSetter($clone, $column->setter(), $entree->{$column->target}(), $collection->prefix.'_'.$column->header, $erreurs);
                }
                if (method_exists($clone, 'changePosition')) {
                    $clone->changePosition(++$position);
                }
                $this->callSetter($aggregatSurvivant, $collection->adder, $clone, $collection->prefix, $erreurs);
                $this->entityManager->persist($clone);
                $signatures[$signature] = true;
            }
        }
    }

    private function signature(CollectionSchema $collection, object $entree): string
    {
        $valeurs = [];
        foreach ($collection->columns as $column) {
            $valeur = $entree->{$column->target}();
            $valeurs[] = match (true) {
                $valeur instanceof \DateTimeInterface => $valeur->format(DATE_ATOM),
                $valeur instanceof \BackedEnum => (string) $valeur->value,
                is_array($valeur) => implode(',', array_map(strval(...), $valeur)),
                is_bool($valeur) => $valeur ? '1' : '0',
                default => mb_strtolower(trim((string) $valeur)),
            };
        }

        return implode('|', $valeurs);
    }

    /** Rattache à la survivante les collaborateurs de l'absorbée qu'elle n'a pas déjà, avec leurs rôles et droits. */
    private function unionAffiliations(Fiche $survivante, Fiche $absorbee, User $acteur): void
    {
        $presents = [];
        foreach ($this->affiliations->findBy(['fiche' => $survivante]) as $affiliation) {
            $presents[$affiliation->collaborateur()->id()] = true;
        }
        foreach ($this->affiliations->findBy(['fiche' => $absorbee]) as $affiliation) {
            if (isset($presents[$affiliation->collaborateur()->id()])) {
                continue;
            }
            $copie = new FicheAffiliation(
                $affiliation->collaborateur(),
                $survivante,
                $affiliation->role(),
                $acteur,
                $affiliation->receivesRequests(),
                $affiliation->repli(),
            );
            $copie->changeTraiteContenus($affiliation->traiteContenus());
            $copie->changeTraitePaiements($affiliation->traitePaiements());
            $this->entityManager->persist($copie);
            $presents[$affiliation->collaborateur()->id()] = true;
        }
    }

    /**
     * La survivante garde son lien Restaurant↔Lieu ; si elle n'en a pas et
     * que l'absorbée en a un, il est transféré. La colonne lieu_id étant
     * unique, le détachement de l'absorbée est flushé avant le rattachement.
     *
     * @return list<Fiche> fiches tierces à réindexer et à protéger du markChanged de leur ligne détail
     */
    private function transfererLienLieuRestaurant(Fiche $survivante, Fiche $absorbee, object $aggregatSurvivant, object $aggregatAbsorbe): array
    {
        $fichesLiees = [];

        if ($aggregatSurvivant instanceof Restaurant && $aggregatAbsorbe instanceof Restaurant) {
            $lieu = $aggregatAbsorbe->lieu();
            if (null === $aggregatSurvivant->lieu() && null !== $lieu) {
                $aggregatAbsorbe->changeLieu(null);
                $liees = $aggregatAbsorbe->drainFichesLieesAResynchroniser();
                Fiche::preserveWorkflowsDuring(
                    [$survivante, $absorbee, ...$liees],
                    function (): void {
                        $this->entityManager->flush();
                    },
                );
                $aggregatSurvivant->changeLieu($lieu);
                $fichesLiees = [...$liees, ...$aggregatSurvivant->drainFichesLieesAResynchroniser()];
            }
        }

        if ($aggregatSurvivant instanceof Lieu && $aggregatAbsorbe instanceof Lieu) {
            $restaurant = $aggregatAbsorbe->restaurant();
            if (null === $aggregatSurvivant->restaurant() && null !== $restaurant) {
                // Un seul UPDATE du côté propriétaire : pas de conflit d'unicité.
                $restaurant->changeLieu($aggregatSurvivant);
                $fichesLiees = [...$restaurant->drainFichesLieesAResynchroniser(), $restaurant->fiche()];
            }
        }

        $uniques = [];
        foreach ($fichesLiees as $fiche) {
            $uniques[$fiche->idString()] = $fiche;
        }

        return array_values($uniques);
    }

    /** @param list<string> $erreurs */
    private function callSetter(object $cible, string $setter, mixed $valeur, string $champ, array &$erreurs): void
    {
        if (!method_exists($cible, $setter)) {
            throw new \LogicException(sprintf('Méthode %s absente sur %s (champ %s).', $setter, $cible::class, $champ));
        }
        try {
            $cible->{$setter}($valeur);
        } catch (\DomainException|\InvalidArgumentException|\TypeError $exception) {
            $erreurs[] = sprintf('%s : %s', $champ, $exception->getMessage());
        }
    }
}
