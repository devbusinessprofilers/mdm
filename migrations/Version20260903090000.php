<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bible « VERSION BP » (Clem, 2026-09) — taux de complétude de la gamme Lieu.
 *
 * Barème décidé : Obligatoire = 3.00, « 2 points » = 2.00, « 1 point » et
 * colonne vide = 1.00 (défaut, inchangé). Les codes sont ceux de
 * pim_completeness_field_configuration (dérivés des noms de champs :
 * EVENEMENTS_PREDILECTION, JOURS_OUVERTURE…).
 *
 * Les accès sont désormais pondérés par type (ACCESS_AEROPORT, ACCESS_GARE,
 * ACCESS_METRO, ACCESS_TRAMWAY, ACCESS_GRANDE_VILLE) : ces lignes sont
 * insérées ici avec leur poids, avant que la synchro du catalogue
 * (app:completeness:sync-config --type=lieu) ne les rencontre — elle
 * désactivera les quatre anciens codes ACCESS_NOM/DISTANCE/DUREE/MODE et
 * planifiera le recalcul.
 */
final class Version20260903090000 extends AbstractMigration
{
    private const OBLIGATOIRES = [
        'LABEL', 'GENERALE_TYPOLOGIE', 'DESC_GENERALE', 'PHOTO', 'ACCESS_AEROPORT', 'ACCESS_GARE',
        'CHAMBRE_HEBERGEMENT', 'CHAMBRE_NB_TOTAL', 'CHAMBRE_CAPACITE_TOTALE', 'CHAMBRE_DESC_GENERALE',
        'SALLE_REUNION_EXIST', 'SALLE_REUNION_NB_TOTAL', 'SALLE_REUNION_CAPACITE_MAX_COCKTAIL', 'SALLE_REUNION_CAPACITE_MAX_THEATRE',
        'SALLE_REUNION_CAPACITE_MIN_THEATRE', 'SALLE_REUNION_SURFACE_MIN_REUNION', 'SALLE_REUNION_SURFACE_MAX_REUNION',
        'SALLE_REUNION_DESC_SALLE_SEMINAIRE', 'RESTAURANT_TOTAL', 'RESTAURANT_CAPACITE_ASSIS',
    ];

    private const DEUX_POINTS = [
        'DISPO_LIEU_PRIVATISABLE', 'JOURS_OUVERTURE', 'DISPO_HORAIRES_JOURS', 'DISPO_NOM', 'DISPO_DATE_DEBUT', 'DISPO_DATE_FIN',
        'ACCESS_METRO', 'ACCESS_TRAMWAY', 'ACCESS_GRANDE_VILLE', 'DESC_GENERALE_POINT_INTERET', 'PMR_ACCES', 'PMR_DETAILS',
        'TA_THEMATIQUE', 'TA_CADRE_ENV', 'TA_AMBIANCE', 'ATOUT1', 'ATOUT2', 'ATOUT3', 'ATOUT4', 'ATOUT5',
        'CHAMBRE_NB_TOTAL_SINGLE', 'CHAMBRE_NB_TOTAL_TWIN', 'CHAMBRE_DOUBLE',
        'CONFIG_NOM', 'CONFIG_SUPERFICIE', 'CONFIG_CAPACITE_REUNION', 'CONFIG_CAPACITE_U', 'CONFIG_CAPACITE_CLASSE', 'CONFIG_CAPACITE_THEATRE',
        'CONFIG_CAPACITE_CABARET', 'CONFIG_CAPACITE_BANQUET', 'CONFIG_CAPACITE_COCKTAIL', 'CONFIG_CAPACITE_AUDITORIUM',
        'CONFIG_LUMIERE_JOUR', 'CONFIG_ACCES_PMR', 'CONFIG_ESPACE_DANSANT', 'CONFIG_CLIMATISEE',
        'SERVICES', 'TECHNIQUE_REUNION', 'INSTALLATION', 'BIEN_ETRE',
        'RSE_DESC_GENERALE', 'ACHAT_RESPONSABLE', 'IMPACT_ENV', 'IMPACT_SOCIAL', 'VOLUME_ACHAT_CAT_ESAT_STPA', 'MOBILITE', 'CERTIFICATION',
        'LOISIR_INTERNE', 'RESTAURANT_CAPACITE_DEBOUT', 'RESTAURANT_SOIREE_DANSANTE', 'RESTAURANT_COCKTAIL_DINATOIRE',
        'RESTAURANT_TRAITEUR_SUR_PLACE', 'RESTAURANT_INTERVENTION_TRAITEUR_EXTERNE', 'RESTAURANT_TRATEUR_EXTERNE_CLIENT',
        'RESTAURANT_PRIVATISABLE', 'RESTAURANT_HEURE_INTERRUPTION_MUSIQUE', 'TYPE_CUISINE', 'GENERALE_YOUTUBE',
        'SEMINAIRE_JOURNEE_DEMI_JOURNEE_ETUDE',
    ];

    /** Nouvelles définitions d'accès par type : code => [libellé, poids]. */
    private const ACCES = [
        'ACCESS_AEROPORT' => ['Accès aéroport', '3.00'],
        'ACCESS_GARE' => ['Accès gare', '3.00'],
        'ACCESS_METRO' => ['Accès métro', '2.00'],
        'ACCESS_TRAMWAY' => ['Accès tramway', '2.00'],
        'ACCESS_GRANDE_VILLE' => ['Accès depuis les grandes villes à proximité', '2.00'],
    ];

    public function getDescription(): string
    {
        return 'Bible VERSION BP : poids de complétude de la gamme Lieu (3/2/1) et accès pondérés par type.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::ACCES as $code => [$label, $poids]) {
            // Idempotent : la ligne existe déjà si la synchro a tourné avant.
            $this->addSql(
                'INSERT INTO pim_completeness_field_configuration (fiche_type, field_code, label, formula, weight) '
                .'SELECT ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS ('
                .'SELECT 1 FROM pim_completeness_field_configuration WHERE fiche_type = ? AND field_code = ?)',
                ['lieu', $code, $label, 'presence', $poids, 'lieu', $code],
            );
        }
        $this->poids(self::OBLIGATOIRES, '3.00');
        $this->poids(self::DEUX_POINTS, '2.00');
    }

    public function down(Schema $schema): void
    {
        $this->poids([...self::OBLIGATOIRES, ...self::DEUX_POINTS], '1.00');
        $this->addSql(
            'DELETE FROM pim_completeness_field_configuration WHERE fiche_type = ? AND field_code IN (?, ?, ?, ?, ?)',
            ['lieu', ...array_keys(self::ACCES)],
        );
    }

    /** @param list<string> $codes */
    private function poids(array $codes, string $poids): void
    {
        $this->addSql(
            sprintf(
                'UPDATE pim_completeness_field_configuration SET weight = ? WHERE fiche_type = ? AND field_code IN (%s)',
                implode(', ', array_fill(0, count($codes), '?')),
            ),
            [$poids, 'lieu', ...$codes],
        );
    }
}
