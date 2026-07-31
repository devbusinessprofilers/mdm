<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729153000 extends AbstractMigration
{
    /** @var array<string, string> */
    private const ADMIN_COLUMNS = [
        'info_legale_nom' => 'VARCHAR(255) DEFAULT NULL',
        'info_legale_forme_juridique' => 'VARCHAR(255) DEFAULT NULL',
        'info_legale_rue_postal' => 'VARCHAR(255) DEFAULT NULL',
        'info_legale_adresse_2' => 'VARCHAR(255) DEFAULT NULL',
        'info_legale_code_postal' => 'VARCHAR(255) DEFAULT NULL',
        'info_legale_ville' => 'VARCHAR(255) DEFAULT NULL',
        'infor_legale_pays' => 'VARCHAR(255) DEFAULT NULL',
        'info_legale_siret' => 'VARCHAR(255) DEFAULT NULL',
        'info_legale_num_tva' => 'VARCHAR(255) DEFAULT NULL',
        'info_legale_assujetti_tva' => 'TINYINT DEFAULT NULL',
        'info_legale_tva' => 'VARCHAR(255) DEFAULT NULL',
        'info_legale_type_de_procedure_judiciaire' => 'VARCHAR(255) DEFAULT NULL',
        'adresse_facturation_nom' => 'VARCHAR(255) DEFAULT NULL',
        'adresse_facturation_rue_postal' => 'VARCHAR(255) DEFAULT NULL',
        'adresse_facturation_code_postal' => 'VARCHAR(255) DEFAULT NULL',
        'adresse_facturation_ville' => 'VARCHAR(255) DEFAULT NULL',
        'adresse_facturation_pays' => 'VARCHAR(255) DEFAULT NULL',
        'adresse_facturation_num_tva' => 'VARCHAR(255) DEFAULT NULL',
        'contact_facturation_nom' => 'VARCHAR(255) DEFAULT NULL',
        'contact_facturation_prenom' => 'VARCHAR(255) DEFAULT NULL',
        'contact_facturation_email' => 'VARCHAR(255) DEFAULT NULL',
        'contact_facturation_telephone' => 'VARCHAR(255) DEFAULT NULL',
        'mode_paiement_bic' => 'VARCHAR(255) DEFAULT NULL',
        'mode_paiement_iban' => 'VARCHAR(255) DEFAULT NULL',
        'mode_paiement_accept_deduction_com' => 'TINYINT DEFAULT NULL',
        'mode_paiement_affacturage' => 'TINYINT DEFAULT NULL',
        'affacturage_bic' => 'VARCHAR(255) DEFAULT NULL',
        'affacturage_iban' => 'VARCHAR(255) DEFAULT NULL',
        'cond_paie_acc_signature' => 'VARCHAR(255) DEFAULT NULL',
        'cond_paie_ann_signature' => 'VARCHAR(255) DEFAULT NULL',
        'commission_applicable' => 'VARCHAR(255) DEFAULT NULL',
        'date_paiement_sold' => 'VARCHAR(255) DEFAULT NULL',
        'conv_part_signee_le' => 'VARCHAR(255) DEFAULT NULL',
        'conv_part_taux' => 'VARCHAR(255) DEFAULT NULL',
        'signataire_email' => 'VARCHAR(255) DEFAULT NULL',
        'signataire_prenom' => 'VARCHAR(255) DEFAULT NULL',
        'signataire_nom' => 'VARCHAR(255) DEFAULT NULL',
    ];

    /** @var array<string, string> */
    private const PRICING_COLUMNS = [
        'seminaire_journee_demi_journee_etude' => 'NUMERIC(12, 2) DEFAULT NULL',
        'seminaire_journee_journee_etude' => 'NUMERIC(12, 2) DEFAULT NULL',
        'seminaire_journee_demi_journee_etude_cocktail' => 'NUMERIC(12, 2) DEFAULT NULL',
        'seminaire_journee_journee_etude_cocktail' => 'NUMERIC(12, 2) DEFAULT NULL',
        'seminaire_nuitee_semi_residentiel' => 'NUMERIC(12, 2) DEFAULT NULL',
        'seminaire_nuitee_residentiel' => 'NUMERIC(12, 2) DEFAULT NULL',
        'seminaire_nuitee_residentiel_all_inclusive' => 'NUMERIC(12, 2) DEFAULT NULL',
        'loc_salle_seul_demi_journee' => 'NUMERIC(12, 2) DEFAULT NULL',
        'loc_salle_seul_journee' => 'NUMERIC(12, 2) DEFAULT NULL',
        'loc_salle_seul_soiree' => 'NUMERIC(12, 2) DEFAULT NULL',
        'cs_cocktail_dejeunatoire_10_pers' => 'NUMERIC(12, 2) DEFAULT NULL',
        'cs_cocktail_dinatoire' => 'NUMERIC(12, 2) DEFAULT NULL',
        'cs_soiree_dansante' => 'NUMERIC(12, 2) DEFAULT NULL',
        'cs_soiree_diner_assis' => 'NUMERIC(12, 2) DEFAULT NULL',
        'tarif_rest_dejeuner_assis' => 'NUMERIC(12, 2) DEFAULT NULL',
        'tarif_rest_diner_assis' => 'NUMERIC(12, 2) DEFAULT NULL',
        'tarif_rest_opt_vin' => 'NUMERIC(12, 2) DEFAULT NULL',
        'tarif_rest_opt_alcool' => 'NUMERIC(12, 2) DEFAULT NULL',
        'tarif_rest_forfait_personalise' => 'NUMERIC(12, 2) DEFAULT NULL',
        'heberg_group_tarif_chambre_single' => 'NUMERIC(12, 2) DEFAULT NULL',
        'heberg_group_tarif_chambre_twin' => 'NUMERIC(12, 2) DEFAULT NULL',
        'heberg_group_tarif_chambre_double' => 'NUMERIC(12, 2) DEFAULT NULL',
    ];

    public function getDescription(): string
    {
        return 'Découpe les données administratives et tarifaires froides de la table large pim_lieu.';
    }

    public function up(Schema $schema): void
    {
        $this->createBlockTable('pim_lieu_administratif', self::ADMIN_COLUMNS);
        $this->createBlockTable('pim_lieu_tarification', self::PRICING_COLUMNS);
        $this->copyFromLieu('pim_lieu_administratif', self::ADMIN_COLUMNS);
        $this->copyFromLieu('pim_lieu_tarification', self::PRICING_COLUMNS);

        $columns = [
            ...array_keys(self::ADMIN_COLUMNS),
            ...array_keys(self::PRICING_COLUMNS),
        ];
        $this->addSql(
            'ALTER TABLE pim_lieu '.
                implode(
                    ', ',
                    array_map(
                        static fn (string $column): string => 'DROP COLUMN '.
                            $column,
                        $columns,
                    ),
                ),
        );
    }

    public function down(Schema $schema): void
    {
        $columns = self::ADMIN_COLUMNS + self::PRICING_COLUMNS;
        $this->addSql(
            'ALTER TABLE pim_lieu '.
                implode(
                    ', ',
                    array_map(
                        static fn (
                            string $column,
                            string $definition,
                        ): string => sprintf('ADD %s %s', $column, $definition),
                        array_keys($columns),
                        $columns,
                    ),
                ),
        );

        foreach (
            [
                'pim_lieu_administratif' => self::ADMIN_COLUMNS,
                'pim_lieu_tarification' => self::PRICING_COLUMNS,
            ] as $table => $blockColumns
        ) {
            $assignments = implode(
                ', ',
                array_map(
                    static fn (string $column): string => sprintf(
                        'l.%1$s = b.%1$s',
                        $column,
                    ),
                    array_keys($blockColumns),
                ),
            );
            $this->addSql(
                sprintf(
                    'UPDATE pim_lieu l INNER JOIN %s b ON b.lieu_id = l.id SET %s',
                    $table,
                    $assignments,
                ),
            );
        }

        $this->addSql('DROP TABLE pim_lieu_administratif');
        $this->addSql('DROP TABLE pim_lieu_tarification');
    }

    /** @param array<string, string> $columns */
    private function createBlockTable(string $table, array $columns): void
    {
        $definitions = implode(
            ', ',
            array_map(
                static fn (
                    string $column,
                    string $definition,
                ): string => sprintf('%s %s', $column, $definition),
                array_keys($columns),
                $columns,
            ),
        );
        $this->addSql(
            sprintf(
                'CREATE TABLE %s (lieu_id BINARY(16) NOT NULL, %s, PRIMARY KEY (lieu_id), CONSTRAINT FK_%s_LIEU FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
                $table,
                $definitions,
                strtoupper($table),
            ),
        );
    }

    /** @param array<string, string> $columns */
    private function copyFromLieu(string $table, array $columns): void
    {
        $columnList = implode(', ', array_keys($columns));
        $this->addSql(
            sprintf(
                'INSERT INTO %s (lieu_id, %s) SELECT id, %s FROM pim_lieu',
                $table,
                $columnList,
                $columnList,
            ),
        );
    }
}
