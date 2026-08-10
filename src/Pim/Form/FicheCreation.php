<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\TypeFiche;

/** Données du formulaire de création de fiche (l'entité n'existe qu'après soumission). */
final class FicheCreation
{
    public ?TypeFiche $type = null;
    public ?string $label = null;
    public ?Localisation $localisation = null;
    /** 'fixe'|'mobile', pertinent pour les activités et services uniquement. */
    public ?string $modeIntervention = null;

    /** @var list<string> */ public array $lieuTypologie = [];
    /** @var list<string> */ public array $lieuThematique = [];
    /** @var list<string> */ public array $lieuAmbiance = [];
    /** @var list<string> */ public array $typeRestaurant = [];
    /** @var list<string> */ public array $thematiqueActivite = [];
    /** @var list<string> */ public array $typePrestataire = [];

    public bool $businessPremium = false;

    /** Niveau de statut : réservé au formulaire complet, conservé pour les reprises. */
    public ?string $miceStatut = null;
    /** @var list<string> */ public array $sitePremium = [];

    /** @var list<int> Sites de diffusion retenus (les obligatoires sont réappliqués côté serveur). */
    public array $sitesDiffusion = [];

    /** Le service référencement remplace le contact prestataire ; l'envoi des accès est désactivé. */
    public bool $contactRepli = false;

    public ?FicheCollaborateur $collaborateurExistant = null;
    public ?string $collabPrenom = null;
    public ?string $collabNom = null;
    public ?string $collabEmail = null;
    public ?string $collabTelephone = null;

    public bool $envoyerAcces = false;
    public ?string $trameEmail = null;

    public function veutCollaborateur(): bool
    {
        return null !== $this->collaborateurExistant || '' !== trim((string) $this->collabEmail);
    }
}
