<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reprise Salesforce → PIM : les données fiche issues de Salesforce
 * (notes RSE, évaluation client, procédure judiciaire, contrats avec
 * comptes) sont rafraîchies par le PIM (app:salesforce:refresh-fiches)
 * puis poussées vers la marketplace via la sync existante. Une ligne par
 * fiche connue de Salesforce (match code = Product2.ID__c).
 */
final class Version20260813210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée etl_fiche_salesforce (données Salesforce des fiches, alimentées par le refresh).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE etl_fiche_salesforce ('
            .' fiche_id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\','
            .' code INT UNSIGNED NOT NULL,'
            .' note_achats_responsables DOUBLE PRECISION DEFAULT NULL,'
            .' note_impact_environnemental DOUBLE PRECISION DEFAULT NULL,'
            .' note_impact_social DOUBLE PRECISION DEFAULT NULL,'
            .' note_mobilite DOUBLE PRECISION DEFAULT NULL,'
            .' note_labels_et_demarche_qualite DOUBLE PRECISION DEFAULT NULL,'
            .' note_globale DOUBLE PRECISION DEFAULT NULL,'
            .' evaluation_client DOUBLE PRECISION DEFAULT NULL,'
            .' procedure_judiciaire VARCHAR(255) DEFAULT NULL,'
            .' contrats_comptes JSON NOT NULL,'
            .' refreshed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\','
            .' PRIMARY KEY(fiche_id)'
            .') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE etl_fiche_salesforce');
    }
}
