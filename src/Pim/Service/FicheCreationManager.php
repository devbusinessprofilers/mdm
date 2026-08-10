<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Account\Message\CollaborateurAccessRequested;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Enum\ModeInterventionService;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheCreation;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\FicheCollaborateurRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FicheCreationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private FicheCollaborateurRepository $collaborateurs,
        private RechercheEntrepriseClient $entreprises,
    ) {
    }

    /** Interroge l'annuaire des entreprises une seule fois par requête (détection de doublons + enrichissement). */
    public function lookupEntreprise(FicheCreation $data): ?EntrepriseInfo
    {
        $localisation = self::isEmptyLocalisation($data->localisation) ? null : $data->localisation;

        return $this->entreprises->findBest((string) $data->label, $localisation?->codePostal());
    }

    public function create(FicheCreation $data, User $actor, ?EntrepriseInfo $entreprise): FicheCreationResult
    {
        self::neutralizeHiddenFields($data);
        $type = $data->type ?? throw new \DomainException('Choisissez une gamme.');

        return $this->entityManager->wrapInTransaction(function () use ($data, $type, $actor, $entreprise): FicheCreationResult {
            $entity = match ($type) {
                TypeFiche::Lieu => new Lieu(),
                TypeFiche::Restaurant => new Restaurant(),
                TypeFiche::Activite => new Activite(),
                TypeFiche::ServiceEvenementiel => new ServiceEvenementiel(),
                default => throw new \DomainException('Type de fiche non pris en charge.'),
            };
            $entity->changeLabel($data->label);
            $fiche = $entity->fiche();

            $localisation = self::isEmptyLocalisation($data->localisation) ? null : $data->localisation;
            $localisation = self::enrichLocalisation($localisation, $entreprise);
            if (null !== $localisation) {
                $fiche->changeLocalisation($localisation);
            }

            $this->applyClassification($entity, $data);
            $this->applyReferencement($entity, $fiche, $data);
            if ($entity instanceof Lieu && null !== $entreprise) {
                self::enrichAdministratif($entity, $entreprise);
            }

            $collaborateur = $this->resolveCollaborateur($data);
            if (null !== $collaborateur) {
                $this->entityManager->persist(
                    new FicheAffiliation($collaborateur, $fiche, FicheAffiliationRole::Manager, $actor),
                );
            }

            $this->entityManager->persist($entity);
            if ($data->envoyerAcces && null !== $collaborateur) {
                $this->outbox->enqueue(new CollaborateurAccessRequested(
                    $collaborateur->id(),
                    $fiche->idString(),
                    (string) $data->trameEmail,
                ));
            }
            $this->outbox->enqueue(new IndexFiche($fiche->idString()));

            return new FicheCreationResult($fiche, $entreprise);
        });
    }

    /** Les valeurs soumises pour des sections masquées (autre gamme) ne sont jamais persistées. */
    private static function neutralizeHiddenFields(FicheCreation $data): void
    {
        if (TypeFiche::Lieu !== $data->type) {
            $data->lieuTypologie = [];
            $data->lieuThematique = [];
            $data->lieuAmbiance = [];
        }
        if (TypeFiche::Restaurant !== $data->type) {
            $data->typeRestaurant = [];
        }
        if (TypeFiche::Activite !== $data->type) {
            $data->thematiqueActivite = [];
        }
        if (TypeFiche::ServiceEvenementiel !== $data->type) {
            $data->typePrestataire = [];
        }
        if (!\in_array($data->type, [TypeFiche::Activite, TypeFiche::ServiceEvenementiel], true)) {
            $data->modeIntervention = null;
        }
    }

    private function applyClassification(object $entity, FicheCreation $data): void
    {
        if ($entity instanceof Lieu) {
            $entity->changeGeneraleTypologie($data->lieuTypologie);
            $entity->changeTaThematique($data->lieuThematique);
            $entity->changeTaAmbiance($data->lieuAmbiance);
        } elseif ($entity instanceof Restaurant) {
            $entity->changeTypesRestaurant($data->typeRestaurant);
        } elseif ($entity instanceof Activite) {
            $entity->changeThematiques($data->thematiqueActivite);
            $entity->changeModeIntervention(
                null === $data->modeIntervention ? null : ModeInterventionActivite::from($data->modeIntervention),
            );
        } elseif ($entity instanceof ServiceEvenementiel) {
            $entity->changePrestations($data->typePrestataire);
            $entity->changeModeIntervention(
                null === $data->modeIntervention ? null : ModeInterventionService::from($data->modeIntervention),
            );
        }
    }

    /**
     * MICE_STATUT et SITE_PREMIUM sont stockés au niveau Fiche pour toutes les
     * gammes ; le Lieu garde en plus sa colonne mice_statut synchrone (édition
     * et import existants).
     */
    private function applyReferencement(object $entity, Fiche $fiche, FicheCreation $data): void
    {
        if (null !== $data->miceStatut) {
            $fiche->replaceAttributeValues('MICE_STATUT', [LieuLovCatalog::valueId('MICE_STATUT', $data->miceStatut)]);
        }
        if ([] !== $data->sitePremium) {
            $fiche->replaceAttributeValues('SITE_PREMIUM', array_map(
                static fn (string $code): int => LieuLovCatalog::valueId('SITE_PREMIUM', $code),
                $data->sitePremium,
            ));
        }
        if ($entity instanceof Lieu) {
            $entity->changeMiceStatut($data->miceStatut);
        }
    }

    private function resolveCollaborateur(FicheCreation $data): ?FicheCollaborateur
    {
        if (null !== $data->collaborateurExistant) {
            return $data->collaborateurExistant;
        }
        $email = trim((string) $data->collabEmail);
        if ('' === $email) {
            return null;
        }
        $collaborateur = $this->collaborateurs->findOneByEmail($email);
        if (null === $collaborateur) {
            $collaborateur = new FicheCollaborateur($email, (string) $data->collabPrenom, (string) $data->collabNom);
            $this->entityManager->persist($collaborateur);
        }
        if (null === $collaborateur->phone()) {
            $collaborateur->changePhone($data->collabTelephone);
        }

        return $collaborateur;
    }

    private static function isEmptyLocalisation(?Localisation $localisation): bool
    {
        return null === $localisation || (
            null === $localisation->pays()
            && null === $localisation->countryCode()
            && null === $localisation->region()
            && null === $localisation->departement()
            && null === $localisation->ruePostale()
            && null === $localisation->codePostal()
            && null === $localisation->ville()
            && null === $localisation->arrondissement()
            && null === $localisation->latitude()
            && null === $localisation->longitude()
        );
    }

    /** Complète la localisation avec la réponse API sans écraser la saisie utilisateur. */
    private static function enrichLocalisation(?Localisation $localisation, ?EntrepriseInfo $entreprise): ?Localisation
    {
        if (null === $entreprise) {
            return $localisation;
        }
        $apport = null !== $entreprise->rue || null !== $entreprise->codePostal
            || null !== $entreprise->ville || null !== $entreprise->latitude;
        if (null === $localisation) {
            if (!$apport) {
                return null;
            }
            $localisation = new Localisation();
        }
        if (null === $localisation->ruePostale()) { $localisation->changeRuePostale($entreprise->rue); }
        if (null === $localisation->codePostal()) { $localisation->changeCodePostal($entreprise->codePostal); }
        if (null === $localisation->ville()) { $localisation->changeVille($entreprise->ville); }
        if (null === $localisation->latitude() && null === $localisation->longitude()) {
            try {
                $localisation->changeLatitude($entreprise->latitude);
                $localisation->changeLongitude($entreprise->longitude);
            } catch (\InvalidArgumentException) {
                // Coordonnées API invalides : on les ignore.
            }
        }
        if (null === $localisation->pays() && null === $localisation->countryCode()) {
            $localisation->changePays('France');
            $localisation->changeCountryCode('FR');
        }

        return $localisation;
    }

    private static function enrichAdministratif(Lieu $lieu, EntrepriseInfo $entreprise): void
    {
        $administratif = $lieu->administratif();
        if (null === $administratif->infoLegaleSiret()) {
            $administratif->changeInfoLegaleSiret($entreprise->siret);
        }
        if (null === $administratif->infoLegaleNumTva()) {
            $administratif->changeInfoLegaleNumTva($entreprise->numeroTva);
        }
    }
}
