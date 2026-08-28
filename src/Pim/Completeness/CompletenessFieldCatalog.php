<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Attribute\CompletenessTarget;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\LieuFormCatalog;

final class CompletenessFieldCatalog
{
    /** @var array<string, list<CompletenessFieldDefinition>> */
    private array $definitions = [];

    /** @var array<string, array<string, CompletenessFieldDefinition>> */
    private array $definitionsByCode = [];

    /** @return list<TypeFiche> */
    public function supportedTypes(): array
    {
        return [TypeFiche::Lieu, TypeFiche::Activite, TypeFiche::Restaurant, TypeFiche::ServiceEvenementiel];
    }

    /** @return list<CompletenessFieldDefinition> */
    public function definitions(TypeFiche $type): array
    {
        return $this->definitions[$type->value] ??= match ($type) {
            TypeFiche::Lieu => $this->lieu(),
            TypeFiche::Activite => $this->activite(),
            TypeFiche::Restaurant => $this->restaurant(),
            TypeFiche::ServiceEvenementiel => $this->service(),
            default => [],
        };
    }

    public function find(TypeFiche $type, string $code): ?CompletenessFieldDefinition
    {
        if (!isset($this->definitionsByCode[$type->value])) {
            $this->definitionsByCode[$type->value] = [];
            foreach ($this->definitions($type) as $definition) {
                $this->definitionsByCode[$type->value][$definition->code] = $definition;
            }
        }

        return $this->definitionsByCode[$type->value][$code] ?? null;
    }

    /** @return list<CompletenessFieldDefinition> */
    private function lieu(): array
    {
        $type = TypeFiche::Lieu;
        $fields = $this->common($type, Lieu::LABEL_MAX_LENGTH);
        $fields[] = $this->field($type, 'GENERALE_TYPOLOGIE', 'Typologie', 'generaleTypologie');
        $fields[] = $this->field($type, 'GENERALE_WEBSITE_URL', 'Site web officiel', 'generaleWebsiteUrl', $this->target(Lieu::class, 'generaleWebsiteUrl'));

        foreach ([
            LieuFormCatalog::general(), LieuFormCatalog::availability(), LieuFormCatalog::accessibilityAndDescription(),
            LieuFormCatalog::equipmentAndServices(), LieuFormCatalog::rse(), LieuFormCatalog::leisure(),
            LieuFormCatalog::restaurant(), LieuFormCatalog::visibility(),
        ] as $group) {
            $fields = [...$fields, ...$this->fromMap($type, $group)];
        }
        $fields = [...$fields, ...$this->fromMap($type, LieuFormCatalog::accommodation(), '', 'chambreHebergement', true, 'Applicable si le lieu propose un hébergement')];
        $fields = [...$fields, ...$this->fromMap($type, LieuFormCatalog::meetingRooms(), '', 'salleReunionExist', true, 'Applicable si le lieu propose des salles de réunion')];
        $fields = [...$fields, ...$this->fromMap($type, LieuFormCatalog::administrative(), 'administratif.')];
        $fields = [...$fields, ...$this->fromMap($type, LieuFormCatalog::pricing(), 'tarification.')];
        $fields = [...$fields, ...$this->localisation($type)];

        foreach ([
            'nom' => 'Nom de la salle', 'superficie' => 'Superficie de la salle', 'capaciteReunion' => 'Capacité réunion',
            'capaciteU' => 'Capacité en U', 'capaciteClasse' => 'Capacité classe', 'capaciteTheatre' => 'Capacité théâtre',
            'capaciteCabaret' => 'Capacité cabaret', 'capaciteBanquet' => 'Capacité banquet', 'capaciteCocktail' => 'Capacité cocktail',
            'capaciteAuditorium' => 'Capacité auditorium', 'lumiereJour' => 'Lumière du jour', 'accesPmr' => 'Accès PMR de la salle',
            'espaceDansant' => 'Espace dansant', 'climatisee' => 'Salle climatisée',
        ] as $name => $label) {
            $fields[] = $this->field($type, 'CONFIG_'.self::code($name), $label, 'salles.*.'.$name, null, 'salleReunionExist', true, 'Applicable si le lieu propose des salles de réunion');
        }
        foreach (['nom' => 'Nom de la période', 'dateDebut' => 'Début de fermeture', 'dateFin' => 'Fin de fermeture'] as $name => $label) {
            $fields[] = $this->field($type, 'DISPO_'.self::code($name), $label, 'periodesFermeture.*.'.$name);
        }
        foreach (['nom' => 'Nom de l’accès', 'distanceKilometres' => 'Distance de l’accès', 'dureeMinutes' => 'Durée de l’accès', 'modeTransport' => 'Mode de transport'] as $name => $label) {
            $fields[] = $this->field($type, 'ACCESS_'.self::code($name), $label, 'acces.*.'.$name);
        }
        $fields[] = $this->field($type, 'PHOTO', 'Photos', 'ressources');

        return $this->unique($fields);
    }

    /** @return list<CompletenessFieldDefinition> */
    private function activite(): array
    {
        $type = TypeFiche::Activite;
        $fields = $this->common($type);
        $map = [
            'PRESTATAIRE' => ['Prestataire', 'prestataire'], 'TYPE_EXT_INT' => ['Type intérieur/extérieur', 'types'],
            'THEMATIQUE_ACTIVITE' => ['Thématiques', 'thematiques'], 'SOUS_THEMATIQUE_ACTIVITE' => ['Sous-thématiques', 'sousThematiques'], 'LANGUE_PARLEE' => ['Langues parlées', 'langues'],
            'ENGAGEMENT_RSE' => ['Engagements RSE', 'engagementsRse'], 'RAYON_ACTION' => ["Rayon d'action", 'modeIntervention'],
            'INFO_GENERALE_DESC' => ['Description générale', 'descriptionGenerale'], 'INFO_GENERALE_WYSIWYG' => ['Contenu de la prestation', 'comprendPrestation'],
            'OBJECTIF_SEMINAIRE' => ['Objectifs', 'objectifs'], 'CAP_GLOBALE_NB_MIN_PAX' => ['Participants minimum', 'participantsMin'],
            'CAP_GLOBALE_NB_MAX_PAX' => ['Participants maximum', 'participantsMax'], 'ACTIVITE_DUREE_MIN' => ['Durée minimale', 'dureeMinMinutes'],
            'ACTIVITE_DUREE_MAX' => ['Durée maximale', 'dureeMaxMinutes'], 'PLUS' => ['Les plus', 'plus'],
            'TARIF_PAR_PERSONNE' => ['Tarif par personne', 'tarifParPersonne'], 'GENERALE_YOUTUBE' => ['Lien vidéo', 'youtubeUrl'],
            'OFFRE_NOM' => ["Nom de l’offre", 'offres.*.nom'], 'OFFRE_PRIX' => ["Prix de l’offre", 'offres.*.prix'],
            'PHOTO' => ['Photos', 'ressources'],
        ];
        foreach ($map as $code => [$label, $path]) { $fields[] = $this->field($type, $code, $label, $path); }
        $fields = [...$fields, ...$this->localisation($type, 'modeIntervention', 'fixe', 'Applicable pour une localisation fixe')];
        foreach (['paysMobiles' => 'Pays mobiles', 'regionsMobiles' => 'Régions mobiles', 'departementsMobiles' => 'Départements mobiles', 'touteFrance' => 'Toute la France'] as $path => $label) {
            $fields[] = $this->field($type, 'RAYON_ACTION_'.self::code($path), $label, $path, null, 'modeIntervention', 'mobile', 'Applicable pour une activité mobile');
        }

        return $fields;
    }

    /** @return list<CompletenessFieldDefinition> */
    private function restaurant(): array
    {
        $type = TypeFiche::Restaurant;
        $fields = $this->common($type);
        $map = [
            'TYPE_RESTAURANT' => ['Types de restaurant', 'typesRestaurant'], 'TYPE_CUISINE' => ['Types de cuisine', 'typesCuisine'],
            'SPECIFICITE_ALIMENTAIRE' => ['Spécificités alimentaires', 'specificitesAlimentaires'], 'TYPE_EVENEMENT' => ["Types d’événements", 'typesEvenement'],
            'GENERALE_WEBSITE_URL' => ['Site officiel', 'siteOfficiel'], 'DISPO_PRIVATISATION_TOTALE' => ['Privatisation totale', 'privatisationTotale'],
            'DISPO_PRIVATISATION_PARTIELLE' => ['Privatisation partielle', 'privatisationPartielle'], 'DISPO_JOUR_OUVERTURE' => ["Jours d’ouverture", 'joursOuverture'],
            'DISPO_HEURE_OUVERTURE' => ["Heure d’ouverture", 'heureOuverture'], 'DISPO_HEURE_FERMETURE' => ['Heure de fermeture', 'heureFermeture'],
            'PMR_ACCES' => ['Accès PMR', 'accesPmr'], 'PMR_TOILETTE' => ['Toilettes PMR', 'toilettesPmr'],
            'GENERALE_DESC' => ['Description générale', 'descriptionGenerale'], 'RESTAURANT_PLUS' => ['Atouts', 'atouts'],
            'CAP_ASSISE_MAX' => ['Capacité assise maximale', 'capaciteAssiseMax'], 'CAP_ASSISE_SALLE_PRIVATISABLE' => ['Capacité privatisable', 'capaciteEspacePrivatisable'],
            'CAP_BANQUET' => ['Capacité banquet', 'capaciteBanquet'], 'CAP_COCKTAIL' => ['Capacité cocktail', 'capaciteCocktail'],
            'SERVICE_RESTAURANT' => ['Services', 'services'], 'EQUIPEMENT_RESTAURANT' => ['Équipements', 'equipements'],
            'ENGAGEMENT_RSE_RESTAURANT' => ['Engagements RSE', 'engagementsRse'], 'GENERALE_YOUTUBE' => ['Lien vidéo', 'youtubeUrl'],
            'PHOTO' => ['Photos', 'ressources'],
        ];
        foreach ($map as $code => [$label, $path]) {
            $target = 'siteOfficiel' === $path ? $this->target(Restaurant::class, 'siteOfficiel') : null;
            $fields[] = $this->field($type, $code, $label, $path, $target);
        }
        $fields = [...$fields, ...$this->localisation($type)];
        foreach (['nom' => 'Nom de la période', 'dateDebut' => 'Début de fermeture', 'dateFin' => 'Fin de fermeture'] as $name => $label) { $fields[] = $this->field($type, 'DISPO_'.self::code($name), $label, 'periodesFermeture.*.'.$name); }
        foreach (['nom' => 'Nom de l’accès'] as $name => $label) { $fields[] = $this->field($type, 'ACCESSIBILITE_'.self::code($name), $label, 'acces.*.'.$name); }
        foreach (['nom' => 'Nom de la salle', 'superficie' => 'Superficie', 'capaciteReunion' => 'Capacité réunion', 'capaciteU' => 'Capacité en U', 'capaciteClasse' => 'Capacité classe', 'capaciteTheatre' => 'Capacité théâtre', 'capaciteCabaret' => 'Capacité cabaret', 'capaciteBanquet' => 'Capacité banquet', 'capaciteCocktail' => 'Capacité cocktail', 'capaciteAuditorium' => 'Capacité auditorium', 'lumiereJour' => 'Lumière du jour', 'accesPmr' => 'Accès PMR', 'espaceDansant' => 'Dansant', 'climatisee' => 'Climatisée'] as $name => $label) { $fields[] = $this->field($type, 'CONFIG_SALLE_'.self::code($name), $label, 'salles.*.'.$name); }

        return $fields;
    }

    /** @return list<CompletenessFieldDefinition> */
    private function service(): array
    {
        $type = TypeFiche::ServiceEvenementiel;
        $fields = $this->common($type);
        $map = [
            'TYPE_PRESTATAIRE' => ['Prestations', 'prestations'], 'SOUS_PRESTATION' => ['Sous-prestations', 'sousPrestations'],
            'GENERAL_DESC' => ['Description générale', 'descriptionGenerale'],
            'TYPE_PRESTATAIRE_ESAT' => ['Prestataire ESAT', 'prestataireEsat'], 'TYPE_PRESTATAIRE_RSE' => ['Démarche RSE', 'demarcheRse'],
            'PRESTATION_FEMME_ENCEINTE' => ['Adapté aux femmes enceintes', 'adapteFemmesEnceintes'], 'PRESTATION_MAL_ENTENDENT' => ['Adapté aux personnes malentendantes', 'adapteMalentendants'],
            'PRESTATION_MAL_VOYANTE' => ['Adapté aux personnes malvoyantes', 'adapteMalvoyants'], 'MATERIEL_LIST' => ['Matériel inclus', 'materielInclus'],
            'MATERIEL_A_PREVOIR_PARTICIPANT' => ['Équipement participants requis', 'equipementParticipantsRequis'], 'MATERIEL_A_PREVOIR_RECEPTION' => ['Équipement réception requis', 'equipementReceptionRequis'],
            'MATERIEL_CONTRAINTE' => ['Contraintes logistiques', 'contraintesLogistiques'], 'RAYON_ACTION' => ["Rayon d’action", 'modeIntervention'],
            'PRESTATION_PERSONNE_MIN' => ['Prestation à partir de (personnes)', 'participantsMin'], 'PRESTATION_PERSONNE_MAX' => ["Prestation jusqu'à (personnes)", 'participantsMax'],
            'PRESTATION_DUREE' => ['Durée de la prestation', 'dureeMinutes'],
            'PAR_PRESTATION' => ['Tarif par prestation', 'tarifParPrestation'], 'PAR_PERSONNE' => ['Tarif par personne', 'tarifParPersonne'],
            'PAR_JOUR' => ['Tarif par jour', 'tarifParJour'], 'PAR_DEMI_JOURNNEE' => ['Tarif par demi-journée', 'tarifParDemiJournee'],
            'PAR_HEURE' => ['Tarif par heure', 'tarifParHeure'], 'SUR_DEVIS' => ['Sur devis', 'surDevis'],
            'GENERALE_YOUTUBE' => ['Lien vidéo', 'youtubeUrl'], 'PHOTO' => ['Photos', 'ressources'],
        ];
        foreach ($map as $code => [$label, $path]) { $fields[] = $this->field($type, $code, $label, $path); }
        $fields = [...$fields, ...$this->localisation($type, 'modeIntervention', 'fixe', 'Applicable pour une localisation fixe')];
        foreach (['paysMobiles' => 'Pays mobiles', 'regionsMobiles' => 'Régions mobiles', 'departementsMobiles' => 'Départements mobiles'] as $path => $label) { $fields[] = $this->field($type, 'RAYON_ACTION_'.self::code($path), $label, $path, null, 'modeIntervention', 'mobile', 'Applicable pour un service mobile'); }

        return $fields;
    }

    /** @return list<CompletenessFieldDefinition> */
    private function common(TypeFiche $type, ?int $labelTarget = null): array
    {
        return [
            $this->field($type, 'CODE', 'Code', 'code'),
            $this->field($type, 'LABEL', 'Libellé', 'label', $labelTarget),
        ];
    }

    /** @return list<CompletenessFieldDefinition> */
    private function localisation(TypeFiche $type, ?string $conditionPath = null, mixed $conditionValue = null, ?string $conditionLabel = null): array
    {
        $fields = [];
        foreach (['pays' => 'Pays', 'region' => 'Région', 'departement' => 'Département', 'ruePostale' => 'Rue postale', 'codePostal' => 'Code postal', 'ville' => 'Ville', 'arrondissement' => 'Arrondissement', 'latitude' => 'Latitude', 'longitude' => 'Longitude'] as $name => $label) {
            $fields[] = $this->field($type, 'LOC_'.self::code($name), $label, 'localisation.'.$name, null, $conditionPath, $conditionValue, $conditionLabel);
        }

        return $fields;
    }

    /** @param array<string, array<string, mixed>> $map
     *  @return list<CompletenessFieldDefinition>
     */
    private function fromMap(TypeFiche $type, array $map, string $prefix = '', ?string $conditionPath = null, mixed $conditionValue = null, ?string $conditionLabel = null): array
    {
        $fields = [];
        foreach ($map as $name => $options) {
            $target = '' === $prefix && in_array($name, ['generaleWebsiteUrl', 'pmrDetails', 'descGenerale', 'chambreDescGenerale', 'atout1', 'atout2', 'atout3', 'atout4', 'atout5'], true)
                ? $this->target(Lieu::class, $name)
                : null;
            $fields[] = $this->field($type, self::code($name), (string) ($options['label'] ?? $name), $prefix.$name, $target, $conditionPath, $conditionValue, $conditionLabel);
        }

        return $fields;
    }

    private function field(TypeFiche $type, string $code, string $label, string $path, ?int $target = null, ?string $conditionPath = null, mixed $conditionValue = null, ?string $conditionLabel = null): CompletenessFieldDefinition
    {
        return new CompletenessFieldDefinition($type, strtoupper($code), $label, $path, $target, $conditionPath, $conditionValue, $conditionLabel);
    }

    /** @param class-string $class */
    private function target(string $class, string $property): ?int
    {
        $reflection = new \ReflectionProperty($class, $property);
        $attribute = $reflection->getAttributes(CompletenessTarget::class)[0] ?? null;

        return $attribute?->newInstance()->length;
    }

    private static function code(string $name): string
    {
        return strtoupper((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    /** @param list<CompletenessFieldDefinition> $fields
     *  @return list<CompletenessFieldDefinition>
     */
    private function unique(array $fields): array
    {
        $unique = [];
        foreach ($fields as $field) { $unique[$field->code] = $field; }

        return array_values($unique);
    }
}
