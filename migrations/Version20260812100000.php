<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20260812100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table parametre (réglages métier surchargeables dans /admin) et son catalogue initial.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE parametre ('
            ."id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', "
            .'nom VARCHAR(64) NOT NULL, '
            .'description VARCHAR(255) NOT NULL, '
            .'type VARCHAR(8) NOT NULL, '
            .'valeur VARCHAR(255) DEFAULT NULL, '
            ."updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', "
            .'UNIQUE INDEX UNIQ_PARAMETRE_NOM (nom), '
            .'PRIMARY KEY (id)'
            .') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        // Catalogue initial : valeur NULL = non surchargé, la variable d'env
        // reste la valeur effective. Le déploiement est donc un no-op métier.
        $catalogue = [
            ['alerte.email', 'string', 'Destinataire des alertes applicatives (file en échec, erreurs 5xx, workers). Vide = alertes désactivées.'],
            ['alerte.seuil_file_echec', 'int', 'Nombre de messages en échec (queue failed Messenger + outbox) à partir duquel une alerte est envoyée.'],
            ['box.ocr_active', 'bool', 'Active l’extraction OCR des documents PDF via Box (dépôt, relecture et application des suggestions).'],
            ['completude.delai_rappel_jours', 'int', 'Nombre de jours minimum entre deux relances de complétude pour une même fiche.'],
            ['completude.seuil_rappel', 'int', 'Score de complétude (%) en dessous duquel une fiche déclenche une relance email. 0 désactive les relances.'],
            ['dam.seuil_distance_phash', 'int', 'Distance pHash maximale pour signaler deux images comme doublons visuels (plus élevé = plus sensible).'],
        ];
        foreach ($catalogue as [$nom, $type, $description]) {
            $this->addSql(
                'INSERT INTO parametre (id, nom, description, type, valeur, updated_at) VALUES (?, ?, ?, ?, NULL, NULL)',
                [(new Ulid())->toBinary(), $nom, $description, $type],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE parametre');
    }
}
