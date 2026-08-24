<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\AccesLieu;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeAccesLieu;
use App\Pim\Enum\TypeFiche;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\SiteDiffusionRepository;

/**
 * Génère le CSV « Produits » de l'intégration Salesforce (une ligne par
 * prestataire). En-tête et format (virgule, guillemets, UTF-8, décimales à la
 * virgule) repris à l'identique de l'ancien export extranet.
 *
 * Le mapping est établi sur les fiches Lieu (les seules connues de l'ancien
 * export). Les colonnes sans source PIM fiable sont laissées vides ou à « 0 »
 * (drapeaux marketing legacy), à recenser avec Théofane avant la migration :
 * S_CLASSIFICATION_NAME, S_NEWREGION_TRANSLATION_NAME, S_PRODUCT_SURFACE_TOTALE,
 * les drapeaux B_PRODUCT_ACCUEIL_PROPRIERTAIRE / PARIS_MEETING / CITY_MEETING /
 * UTIMATE_CHATEAU / SKI_RESORT / SEA_MEETING / DESTI_CHANTILLY, les blocs de
 * texte TXT_SERVICES / TXT_RESTAURANT_GASTRONOMIE / TXT_EXEMPLE_PROGRAMME /
 * TXT_DETENTE_LOISIRS / TXT_INCENTIVES / TXT_ROUTES / TXT_EQUIPEMENT /
 * TXT_PARKING / TXT_POINTS_FORTS / TXT_REFERENCES / TXT_RAYON_ACTION, les
 * S_INFO* et METRO.
 */
final class SalesforceProduitsCsvExporter
{
    /** @var list<string> En-tête exact du fichier export_sales_force_products.csv */
    public const ENTETES = [
        'ID_PRODUCT', 'S_LANGUE_NAME', 'S_VISIBILITY_NAME', 'S_PRODUCT_TRANSLATION_NAME',
        'S_CLASSIFICATION_NAME', 'B_PRODUCT_VALID', 'B_PRODUCT_PUBLISHED', 'B_PRODUCT_CONTACT',
        'S_GAMME_NAME', 'S_THEME_TRANSLATION_NAME', 'S_LIEUTYPE_TRANSLATION_NAME',
        'S_VILLE_TRANSLATION_NAME', 'S_ARRONDISSEMENT_TRANSLATION_NAME', 'S_COUNTRY_TRANSLATION_NAME',
        'S_REGION_TRANSLATION_NAME', 'S_NEWREGION_TRANSLATION_NAME', 'S_DEPARTEMENT_TRANSLATION_NAME',
        'S_DEPARTEMENT_NUM', 'S_PRODUCT_ADRESSE', 'S_PRODUCT_CODEPOSTAL', 'S_PRODUCT_VILLE',
        'S_PRODUCT_TEL', 'S_PRODUCT_WEB', 'S_PRODUCT_LATTITUDE', 'S_PRODUCT_LONGITUDE',
        'I_PRODUCT_NB_RESTAURANTS', 'I_PRODUCT_NB_SALLE_REUNION', 'S_PRODUCT_CAPACITE_MIN',
        'S_PRODUCT_CAPACITE_GDE_SALLE', 'I_PRODUCT_NB_CHAMBRES', 'I_PRODUCT_NB_CHAMBRES_TWIN',
        'S_PRODUCT_CAPACITE_HEBERGEMENT', 'S_PRODUCT_CAPACITE_SEMINAIRE', 'S_PRODUCT_CAPACITE_RESTAURATION',
        'S_PRODUCT_SURFACE_DE', 'S_PRODUCT_SURFACE_A', 'S_PRODUCT_SURFACE_TOTALE',
        'B_PRODUCT_BUSINESS_PREMIUM', 'B_PRODUCT_ACCUEIL_PROPRIERTAIRE', 'B_PRODUCT_PARIS_MEETING',
        'B_PRODUCT_CITY_MEETING', 'B_PRODUCT_UTIMATE_CHATEAU', 'B_PRODUCT_SKI_RESORT',
        'B_PRODUCT_SEA_MEETING', 'B_PRODUCT_DESTI_CHANTILLY', 'B_PRODUCT_AFF_PROMO',
        'D_PRODUCT_PROMOTION_START', 'D_PRODUCT_PROMOTION_END',
        'S_PRODUCT_TRANSLATION_TXT_SITUATION_LOCALISATION', 'S_PRODUCT_TRANSLATION_TXT_SERVICES',
        'S_PRODUCT_TRANSLATION_TXT_CHAMBRES', 'S_PRODUCT_TRANSLATION_TXT_RESTAURANT_GASTRONOMIE',
        'S_PRODUCT_TRANSLATION_TXT_DESCRIPTIF', 'S_PRODUCT_TRANSLATION_TXT_EXEMPLE_PROGRAMME',
        'S_PRODUCT_TRANSLATION_TXT_OFFRE_SPECIALE', 'S_PRODUCT_TRANSLATION_TXT_SALLE',
        'S_PRODUCT_TRANSLATION_TXT_DETENTE_LOISIRS', 'S_PRODUCT_TRANSLATION_TXT_INCENTIVES',
        'S_PRODUCT_TRANSLATION_TXT_PRINT_LES_PLUS_1', 'S_PRODUCT_TRANSLATION_TXT_PRINT_LES_PLUS_2',
        'S_PRODUCT_TRANSLATION_TXT_PRINT_LES_PLUS_3', 'S_PRODUCT_TRANSLATION_TXT_PRINT_LES_PLUS_4',
        'S_PRODUCT_TRANSLATION_TXT_PRINT_LES_PLUS_5', 'S_PRODUCT_TRANSLATION_TXT_PRINT_LES_PLUS_6',
        'S_PRODUCT_TRANSLATION_TXT_PRINT_LES_PLUS_7',
        'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_1', 'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_2',
        'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_3', 'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_4',
        'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_5', 'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_6',
        'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_7', 'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_8',
        'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_9', 'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_10',
        'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_11', 'S_PRODUCT_TRANSLATION_TXT_PRINT_LOISIRS_INCENTIVES_12',
        'S_PRODUCT_TRANSLATION_PRINT_VILLE_NAME', 'F_PRODUCT_TRANSLATION_PRINT_VILLE_DISTANCE',
        'S_PRODUCT_TRANSLATION_TXT_ROUTES', 'I_PRODUCT_TRANSLATION_PRIX_JOURNEE_ETUDE',
        'I_PRODUCT_TRANSLATION_PRIX_SEMINAIRE_RESIDENTIEL', 'S_PRODUCT_TRANSLATION_TXT_EQUIPEMENT',
        'S_PRODUCT_TRANSLATION_AEROPORT_NAME1', 'F_PRODUCT_TRANSLATION_AEROPORT_DISTANCE1',
        'S_PRODUCT_TRANSLATION_AEROPORT_NAME2', 'F_PRODUCT_TRANSLATION_AEROPORT_DISTANCE2',
        'S_PRODUCT_TRANSLATION_GARE_NAME1', 'F_PRODUCT_TRANSLATION_GARE_DISTANCE1',
        'S_PRODUCT_TRANSLATION_GARE_NAME2', 'F_PRODUCT_TRANSLATION_GARE_DISTANCE2',
        'S_PRODUCT_TRANSLATION_TXT_PARKING',
        'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_1', 'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_2',
        'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_3', 'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_4',
        'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_5', 'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_6',
        'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_7', 'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_8',
        'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_9', 'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_10',
        'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_11', 'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_12',
        'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_13', 'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_14',
        'S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_15',
        'S_PRODUCT_TRANSLATION_TXT_POINTS_FORTS', 'S_PRODUCT_TRANSLATION_TXT_REFERENCES',
        'S_PRODUCT_TRANSLATION_TXT_RAYON_ACTION', 'S_INFOPARKING', 'S_INFOINTERNET',
        'S_INFOPISCINEBIENETRE', 'S_INFOTRANSPORT', 'S_INFOACTIVITE', 'S_INFODIVERS',
        'S_PRODUCT_TRANSLATION_METRO',
    ];

    /** @var array<int, string>|null Cache id de site → libellé (une fois par process). */
    private ?array $sitesLabels = null;

    public function __construct(
        private LieuRepository $lieux,
        private SiteDiffusionRepository $sites,
    ) {
    }

    /**
     * @param iterable<Fiche> $fiches
     */
    public function csv(iterable $fiches): string
    {
        return SalesforceCsvBuilder::build(self::ENTETES, $this->lignes($fiches));
    }

    /**
     * @param iterable<Fiche> $fiches
     *
     * @return iterable<list<string>>
     */
    private function lignes(iterable $fiches): iterable
    {
        foreach ($fiches as $fiche) {
            $lieu = TypeFiche::Lieu === $fiche->type() ? $this->lieux->find($fiche->id()) : null;
            yield $this->ligne($fiche, $lieu instanceof Lieu ? $lieu : null);
        }
    }

    /** @return list<string> */
    private function ligne(Fiche $fiche, ?Lieu $lieu): array
    {
        $loc = $fiche->localisation();
        $plus = $lieu instanceof Lieu
            ? array_values(array_filter([$lieu->atout1(), $lieu->atout2(), $lieu->atout3(), $lieu->atout4(), $lieu->atout5()], static fn (?string $v): bool => null !== $v))
            : [];
        $loisirs = $lieu instanceof Lieu
            ? array_merge($lieu->loisirExterneNomActivite(), $lieu->loisirExterneNomPresta(), $lieu->loisirInterne())
            : [];
        $aeroports = $this->acces($lieu, TypeAccesLieu::Aeroport);
        $gares = $this->acces($lieu, TypeAccesLieu::Gare);
        $grandeVille = $this->acces($lieu, TypeAccesLieu::GrandeVille)[0] ?? null;
        $technique = $lieu instanceof Lieu ? $this->lovLabels('TECHNIQUE_REUNION', $lieu->techniqueReunion()) : [];

        $ligne = [
            (string) $fiche->code(),                                    // ID_PRODUCT
            'Français',                                                 // S_LANGUE_NAME
            $this->visibilite($fiche),                                  // S_VISIBILITY_NAME
            $fiche->label() ?? '',                                      // S_PRODUCT_TRANSLATION_NAME
            '',                                                         // S_CLASSIFICATION_NAME (pas de source PIM)
            in_array($fiche->status(), [StatutFiche::Validee, StatutFiche::Publiee], true) ? '1' : '0', // B_PRODUCT_VALID
            StatutFiche::Publiee === $fiche->status() ? '1' : '0',      // B_PRODUCT_PUBLISHED
            $lieu instanceof Lieu && $lieu->afficherContact() ? '1' : '0', // B_PRODUCT_CONTACT
            $lieu?->generaleGammeLibelle() ?? '',                       // S_GAMME_NAME
            $lieu instanceof Lieu ? implode(';', $this->lovLabels('TA_THEMATIQUE', $lieu->taThematique())) : '', // S_THEME_TRANSLATION_NAME
            $lieu instanceof Lieu ? implode(';', $this->lovLabels('GENERALE_TYPOLOGIE', $lieu->generaleTypologie())) : '', // S_LIEUTYPE_TRANSLATION_NAME
            $loc?->ville() ?? '',                                       // S_VILLE_TRANSLATION_NAME
            $loc?->arrondissement() ?? '',                              // S_ARRONDISSEMENT_TRANSLATION_NAME
            $loc?->pays() ?? '',                                        // S_COUNTRY_TRANSLATION_NAME
            $loc?->region() ?? '',                                      // S_REGION_TRANSLATION_NAME
            '',                                                         // S_NEWREGION_TRANSLATION_NAME (pas de champ distinct)
            $loc?->departement() ?? '',                                 // S_DEPARTEMENT_TRANSLATION_NAME
            self::departementNum($loc),                                 // S_DEPARTEMENT_NUM
            $loc?->ruePostale() ?? '',                                  // S_PRODUCT_ADRESSE
            $loc?->codePostal() ?? '',                                  // S_PRODUCT_CODEPOSTAL
            $loc?->ville() ?? '',                                       // S_PRODUCT_VILLE
            $fiche->telephone() ?? '',                                  // S_PRODUCT_TEL
            $lieu?->generaleWebsiteUrl() ?? '',                         // S_PRODUCT_WEB
            self::coord($loc?->latitude()),                             // S_PRODUCT_LATTITUDE
            self::coord($loc?->longitude()),                            // S_PRODUCT_LONGITUDE
            self::i($lieu?->restaurantTotal()),                         // I_PRODUCT_NB_RESTAURANTS
            self::i($lieu?->salleReunionNbTotal()),                     // I_PRODUCT_NB_SALLE_REUNION
            self::i($lieu?->salleReunionCapaciteMinTheatre()),          // S_PRODUCT_CAPACITE_MIN
            self::i($lieu?->salleReunionCapaciteMaxTheatre()),          // S_PRODUCT_CAPACITE_GDE_SALLE
            self::i($lieu?->chambreNbTotal()),                          // I_PRODUCT_NB_CHAMBRES
            self::i($lieu?->chambreNbTotalTwin()),                      // I_PRODUCT_NB_CHAMBRES_TWIN
            self::i($lieu?->chambreCapaciteTotale()),                   // S_PRODUCT_CAPACITE_HEBERGEMENT
            self::i($lieu?->salleReunionCapaciteMaxCocktail()),         // S_PRODUCT_CAPACITE_SEMINAIRE
            self::i($lieu?->restaurantCapaciteAssis()),                 // S_PRODUCT_CAPACITE_RESTAURATION
            self::i($lieu?->salleReunionSurfaceMinReunion()),           // S_PRODUCT_SURFACE_DE
            self::i($lieu?->salleReunionSurfaceMaxReunion()),           // S_PRODUCT_SURFACE_A
            '',                                                         // S_PRODUCT_SURFACE_TOTALE (pas de source PIM)
            $fiche->businessPremium() ? '1' : '0',                      // B_PRODUCT_BUSINESS_PREMIUM
            '0', '0', '0', '0', '0', '0', '0',                          // ACCUEIL_PROP, PARIS/CITY_MEETING, UTIMATE_CHATEAU, SKI, SEA, DESTI_CHANTILLY
            $lieu instanceof Lieu && null !== $lieu->tarification()->promotionDebut() ? '1' : '0', // B_PRODUCT_AFF_PROMO
            $lieu?->tarification()->promotionDebut()?->format('Y-m-d') ?? '', // D_PRODUCT_PROMOTION_START
            $lieu?->tarification()->promotionFin()?->format('Y-m-d') ?? '',   // D_PRODUCT_PROMOTION_END
            $lieu?->descGeneralePointInteret() ?? '',                   // TXT_SITUATION_LOCALISATION
            '',                                                         // TXT_SERVICES (pas de texte libre PIM)
            $lieu?->chambreDescGenerale() ?? '',                        // TXT_CHAMBRES
            '',                                                         // TXT_RESTAURANT_GASTRONOMIE
            $lieu?->descGenerale() ?? '',                               // TXT_DESCRIPTIF
            '',                                                         // TXT_EXEMPLE_PROGRAMME
            $lieu?->tarification()->offreSpeciale() ?? '',              // TXT_OFFRE_SPECIALE
            $lieu?->salleReunionDescSalleSeminaire() ?? '',             // TXT_SALLE
            '',                                                         // TXT_DETENTE_LOISIRS
            '',                                                         // TXT_INCENTIVES
        ];

        // PRINT_LES_PLUS_1..7 (5 atouts PIM, puis vides)
        for ($i = 0; $i < 7; ++$i) {
            $ligne[] = $plus[$i] ?? '';
        }
        // PRINT_LOISIRS_INCENTIVES_1..12
        for ($i = 0; $i < 12; ++$i) {
            $ligne[] = $loisirs[$i] ?? '';
        }

        $ligne[] = $grandeVille?->nom() ?? '';                          // PRINT_VILLE_NAME
        $ligne[] = self::montant($grandeVille?->distanceKilometres());  // PRINT_VILLE_DISTANCE
        $ligne[] = '';                                                  // TXT_ROUTES
        $ligne[] = self::montant($lieu?->tarification()->seminaireJourneeJourneeEtude());   // PRIX_JOURNEE_ETUDE
        $ligne[] = self::montant($lieu?->tarification()->seminaireNuiteeResidentiel());     // PRIX_SEMINAIRE_RESIDENTIEL
        $ligne[] = '';                                                  // TXT_EQUIPEMENT
        $aero1 = $aeroports[0] ?? null;
        $aero2 = $aeroports[1] ?? null;
        $gare1 = $gares[0] ?? null;
        $gare2 = $gares[1] ?? null;
        $ligne[] = $aero1?->nom() ?? '';                                // AEROPORT_NAME1
        $ligne[] = self::montant($aero1?->distanceKilometres());        // AEROPORT_DISTANCE1
        $ligne[] = $aero2?->nom() ?? '';                                // AEROPORT_NAME2
        $ligne[] = self::montant($aero2?->distanceKilometres());        // AEROPORT_DISTANCE2
        $ligne[] = $gare1?->nom() ?? '';                                // GARE_NAME1
        $ligne[] = self::montant($gare1?->distanceKilometres());        // GARE_DISTANCE1
        $ligne[] = $gare2?->nom() ?? '';                                // GARE_NAME2
        $ligne[] = self::montant($gare2?->distanceKilometres());        // GARE_DISTANCE2
        $ligne[] = '';                                                  // TXT_PARKING
        // EQUIP_TECHNIQUE_1..15 (libellés de la LOV technique & réunion)
        for ($i = 0; $i < 15; ++$i) {
            $ligne[] = $technique[$i] ?? '';
        }
        $ligne[] = '';                                                  // TXT_POINTS_FORTS
        $ligne[] = '';                                                  // TXT_REFERENCES
        $ligne[] = '';                                                  // TXT_RAYON_ACTION
        $ligne[] = '';                                                  // S_INFOPARKING
        $ligne[] = '';                                                  // S_INFOINTERNET
        $ligne[] = '';                                                  // S_INFOPISCINEBIENETRE
        $ligne[] = '';                                                  // S_INFOTRANSPORT
        $ligne[] = '';                                                  // S_INFOACTIVITE
        $ligne[] = '';                                                  // S_INFODIVERS
        $ligne[] = '';                                                  // METRO

        return $ligne;
    }

    /**
     * Libellés d'une LOV pour une liste de codes (code inconnu conservé tel quel).
     *
     * @param list<string> $codes
     *
     * @return list<string>
     */
    private function lovLabels(string $attribut, array $codes): array
    {
        $choix = LieuLovCatalog::choicesFor($attribut);

        return array_map(
            static fn (string $code): string => $choix[$code] ?? $code,
            $codes,
        );
    }

    private function visibilite(Fiche $fiche): string
    {
        if (null === $this->sitesLabels) {
            $map = [];
            foreach ($this->sites->findAll() as $site) {
                if (null !== $site->id()) {
                    $map[$site->id()] = $site->label();
                }
            }
            $this->sitesLabels = $map;
        }
        $labels = [];
        foreach ($fiche->siteDiffusionIds() as $id) {
            if (isset($this->sitesLabels[$id])) {
                $labels[] = $this->sitesLabels[$id];
            }
        }

        return implode(';', $labels);
    }

    /**
     * @return list<AccesLieu>
     */
    private function acces(?Lieu $lieu, TypeAccesLieu $type): array
    {
        if (!$lieu instanceof Lieu) {
            return [];
        }

        return array_values(array_filter(
            $lieu->acces()->toArray(),
            static fn (AccesLieu $a): bool => $a->type() === $type,
        ));
    }

    private static function departementNum(?Localisation $loc): string
    {
        if (null === $loc || 'FR' !== $loc->countryCode()) {
            return '';
        }
        $cp = $loc->codePostal();

        return null === $cp || '' === $cp ? '' : substr($cp, 0, 2);
    }

    private static function i(?int $value): string
    {
        return null === $value ? '' : (string) $value;
    }

    /** Coordonnée : décimale à la virgule, précision conservée. */
    private static function coord(?string $value): string
    {
        return null === $value || '' === $value ? '' : str_replace('.', ',', $value);
    }

    /** Montant/distance DECIMAL : zéros non significatifs retirés, virgule décimale. */
    private static function montant(?string $value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return str_replace('.', ',', $value);
    }
}
