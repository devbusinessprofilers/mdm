<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\ActiviteLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les seeds historiques ont laissé `label = code` sur les définitions de LOV
 * des lieux et des sous-thématiques d'activité (les `INSERT IGNORE` suivants
 * n'ont jamais corrigé les lignes existantes). Pose les libellés officiels de
 * la Bible (onglet « Bible », colonne Libellé) ; le garde `label = code`
 * préserve tout libellé déjà personnalisé en admin.
 */
final class Version20260820110000 extends AbstractMigration
{
    /** @var array<string, string> code d'attribut => libellé Bible */
    private const LABELS = [
        'GENERALE_TYPOLOGIE' => 'Typologie',
        'GENERALE_CHAINES_GROUPE_HOT' => 'Groupe et Chaîne hôtelière',
        'GENERALE_EVENEMENTS_PREDILECTION' => 'Événements de prédilection',
        'DISPO_JOUR_OUVERTURE' => 'Jours d’ouverture',
        'COND_PAIE_ACC_SIGNATURE' => 'Conditions de paiement acompte',
        'COND_PAIE_ANN_SIGNATURE' => 'Conditions de paiement annulation',
        'DATE_PAIEMENT_SOLD' => 'Date du paiement du solde',
        'TA_THEMATIQUE' => 'Thématique',
        'TA_CADRE_ENV' => 'Cadre / Environnement',
        'TA_AMBIANCE' => 'Ambiance',
        'EQUIPEMENTS' => 'Équipements',
        'SERVICES' => 'Services',
        'TECHNIQUE_REUNION' => 'Technique & Réunion',
        'INSTALLATION' => 'Installation',
        'BIEN_ETRE' => 'Bien-être',
        'ACHAT_RESPONSABLE' => 'Achats responsables',
        'IMPACT_ENV' => 'Impact environnemental',
        'IMPACT_SOCIAL' => 'Impact social',
        'VOLUME_ACHAT_CAT_ESAT_STPA' => '% de volume d’achat par catégorie ESAT STPA',
        'MOBILITE' => 'Mobilité',
        'CERTIFICATION' => 'Certification',
        'TYPE_CUISINE' => 'Type de cuisine',
        'SERVICE_RESTAURATION' => 'Service(s) de restauration',
        'INFO_LEGALE_TVA' => 'TVA au débit ou à l’encaissement',
        'MODE_PAIEMENT_CARTE_LISTE' => 'Cartes bancaires acceptées',
        'SITE_PREMIUM' => 'Activation sites premium',
        'MICE_STATUT' => 'Statut Premium',
    ];

    public function getDescription(): string
    {
        return 'Pose les libellés Bible sur les définitions de LOV restées avec label = code.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->labels() as $code => $label) {
            $this->addSql(
                'UPDATE pim_attribute_definition SET label = ? WHERE code = ? AND label = code',
                [$label, $code],
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_keys($this->labels()) as $code) {
            $this->addSql(
                'UPDATE pim_attribute_definition SET label = code WHERE code = ?',
                [$code],
            );
        }
    }

    /** @return array<string, string> */
    private function labels(): array
    {
        $labels = self::LABELS;
        foreach (ActiviteLovCatalog::sousThematiqueAttributes() as $attribute => $thematique) {
            $labels[$attribute] = 'Sous-thématiques — '.$thematique;
        }

        return $labels;
    }
}
