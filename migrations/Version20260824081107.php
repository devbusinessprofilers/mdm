<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

/**
 * Synchro Salesforce sortante (CSV e-mail, système de transition) : table de
 * suivi par fiche + paramètres applicatifs éditables dans /admin/parametres.
 */
final class Version20260824081107 extends AbstractMigration
{
    /** @var list<array{0: string, 1: string, 2: string}> nom, type, description */
    private const PARAMETRES = [
        ['salesforce.csv_actif', 'bool', 'Active la synchronisation Salesforce par CSV e-mail (système de transition). Désactivé = aucun envoi.'],
        ['salesforce.csv_destinataire', 'string', 'Adresse e-mail d’intégration Salesforce (email → intégration) recevant les CSV Produits et Salles.'],
        ['salesforce.csv_token', 'string', 'Jeton d’intégration Salesforce présent dans l’objet des e-mails (integration=<jeton>;interface=…).'],
        ['salesforce.csv_expediteur', 'string', 'Expéditeur des e-mails Salesforce (doit être autorisé par Salesforce). Vide = MAILER_FROM.'],
    ];

    public function getDescription(): string
    {
        return 'Crée etl_fiche_salesforce_export et les paramètres salesforce.csv_* (synchro Salesforce CSV e-mail).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE etl_fiche_salesforce_export (fiche_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', code INT UNSIGNED NOT NULL, dirty_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', salles_sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', last_error LONGTEXT DEFAULT NULL, INDEX IDX_ETL_SF_EXPORT_DIRTY (dirty_at), PRIMARY KEY (fiche_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`");

        // Catalogue : valeur NULL = non surchargé, la variable d'env reste la
        // valeur effective (voir Version20260812100000).
        foreach (self::PARAMETRES as [$nom, $type, $description]) {
            $this->addSql(
                'INSERT INTO parametre (id, nom, description, type, valeur, updated_at) VALUES (?, ?, ?, ?, NULL, NULL)',
                [(new Ulid())->toBinary(), $nom, $description, $type],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM parametre WHERE nom IN ('salesforce.csv_actif', 'salesforce.csv_destinataire', 'salesforce.csv_token', 'salesforce.csv_expediteur')");
        $this->addSql('DROP TABLE etl_fiche_salesforce_export');
    }
}
