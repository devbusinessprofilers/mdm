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
use App\Pim\Entity\VisibiliteGeoRun;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Enum\ModeInterventionService;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheCreation;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\FicheCollaborateurRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FicheCreationManager
{
    /**
     * Contact affecté quand « contact de repli » est coché. Adresse provisoire
     * reprise de la maquette — à confirmer par le métier.
     */
    private const CONTACT_REPLI = [
        'email' => 'referencement@businessprofilers.fr',
        'prenom' => 'Service',
        'nom' => 'Référencement',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private FicheCollaborateurRepository $collaborateurs,
        private RechercheEntrepriseClient $entreprises,
        private SiteDiffusionRepository $sitesDiffusion,
        private SiteDiffusionGeoAttribueur $geoAttribueur,
        private VisibiliteGeoJournal $journalGeo,
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
            $this->applySitesDiffusion($fiche, $data);
            // Visibilité géographique (CDC §10.1) : la fiche sort du tunnel
            // déjà rattachée aux sites dont la zone couvre son adresse, comme
            // le pré-remplissage annuaire. Seul point d'attribution
            // automatique — ensuite, le bouton « Appliquer les sites
            // automatiques » de la fiche prend le relais à la demande.
            $ajoutes = $this->geoAttribueur->attribuer($fiche);
            if ($ajoutes > 0) {
                $this->journalGeo->traceFiche(VisibiliteGeoRun::DECLENCHEUR_CREATION, $fiche, $ajoutes);
            }
            if ($entity instanceof Lieu && null !== $entreprise) {
                self::enrichAdministratif($entity, $entreprise);
            }

            if ($data->contactRepli) {
                // Le repli remplace le contact prestataire et verrouille l'envoi des accès.
                $data->envoyerAcces = false;
                $this->entityManager->persist(
                    new FicheAffiliation($this->resolveContactRepli(), $fiche, FicheAffiliationRole::Manager, $actor, repli: true),
                );
                $collaborateur = null;
            } else {
                $collaborateur = $this->resolveCollaborateur($data);
                if (null !== $collaborateur) {
                    $this->entityManager->persist(
                        new FicheAffiliation($collaborateur, $fiche, FicheAffiliationRole::Manager, $actor),
                    );
                }
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
     * MICE_STATUT est stocké au niveau Fiche pour toutes les gammes ; le Lieu
     * garde en plus sa colonne mice_statut synchrone (édition et import
     * existants).
     */
    private function applyReferencement(object $entity, Fiche $fiche, FicheCreation $data): void
    {
        $fiche->changeBusinessPremium($data->businessPremium);
        if (null !== $data->miceStatut) {
            $fiche->replaceAttributeValues('MICE_STATUT', [LieuLovCatalog::valueId('MICE_STATUT', $data->miceStatut)]);
        }
        if ($entity instanceof Lieu) {
            $entity->changeMiceStatut($data->miceStatut);
        }
    }

    /** La sélection soumise, complétée des sites obligatoires. */
    private function applySitesDiffusion(Fiche $fiche, FicheCreation $data): void
    {
        $sites = array_values(array_filter(
            $this->sitesDiffusion->findActifsOrdonnes(),
            static fn ($site): bool => $site->obligatoire() || in_array($site->id(), $data->sitesDiffusion, true),
        ));
        if ([] !== $sites) {
            $fiche->replaceSiteDiffusion($sites);
        }
    }

    private function resolveContactRepli(): FicheCollaborateur
    {
        $collaborateur = $this->collaborateurs->findOneByEmail(self::CONTACT_REPLI['email']);
        if (null === $collaborateur) {
            $collaborateur = new FicheCollaborateur(
                self::CONTACT_REPLI['email'],
                self::CONTACT_REPLI['prenom'],
                self::CONTACT_REPLI['nom'],
            );
            $this->entityManager->persist($collaborateur);
        }

        return $collaborateur;
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
        if (null === $localisation->ruePostale()) {
            $localisation->changeRuePostale($entreprise->rue);
        }
        if (null === $localisation->codePostal()) {
            $localisation->changeCodePostal($entreprise->codePostal);
        }
        if (null === $localisation->ville()) {
            $localisation->changeVille($entreprise->ville);
        }
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

    /** Pré-remplit l'onglet « Facturation & partenariat » depuis l'annuaire sans écraser la saisie. */
    private static function enrichAdministratif(Lieu $lieu, EntrepriseInfo $entreprise): void
    {
        $administratif = $lieu->administratif();
        foreach ([
            // Informations légales : le siège fait foi (l'annuaire ne référence que la France).
            ['infoLegaleNom', 'changeInfoLegaleNom', $entreprise->raisonSociale],
            ['infoLegaleFormeJuridique', 'changeInfoLegaleFormeJuridique', $entreprise->formeJuridique],
            ['infoLegaleRuePostal', 'changeInfoLegaleRuePostal', $entreprise->rue],
            ['infoLegaleCodePostal', 'changeInfoLegaleCodePostal', $entreprise->codePostal],
            ['infoLegaleVille', 'changeInfoLegaleVille', $entreprise->ville],
            ['inforLegalePays', 'changeInforLegalePays', 'France'],
            ['infoLegaleSiret', 'changeInfoLegaleSiret', $entreprise->siret],
            ['infoLegaleNumTva', 'changeInfoLegaleNumTva', $entreprise->numeroTva],
            // Adresse de facturation : le siège par défaut, ajustable ensuite dans l'onglet.
            ['adresseFacturationNom', 'changeAdresseFacturationNom', $entreprise->raisonSociale],
            ['adresseFacturationRuePostal', 'changeAdresseFacturationRuePostal', $entreprise->rue],
            ['adresseFacturationCodePostal', 'changeAdresseFacturationCodePostal', $entreprise->codePostal],
            ['adresseFacturationVille', 'changeAdresseFacturationVille', $entreprise->ville],
            ['adresseFacturationPays', 'changeAdresseFacturationPays', 'France'],
            ['adresseFacturationNumTva', 'changeAdresseFacturationNumTva', $entreprise->numeroTva],
        ] as [$getter, $setter, $value]) {
            if (null === $administratif->{$getter}()) {
                $administratif->{$setter}($value);
            }
        }
        // Signataire proposé = dirigeant principal ; jamais si un nom ou prénom est déjà saisi.
        if (null === $administratif->signatairePrenom() && null === $administratif->signataireNom()) {
            $administratif->changeSignatairePrenom($entreprise->dirigeantPrenom);
            $administratif->changeSignataireNom($entreprise->dirigeantNom);
        }
    }
}
