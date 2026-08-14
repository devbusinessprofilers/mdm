<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

/**
 * Application automatique des suggestions OCR : au-dessus de ce seuil de
 * confiance (en %), les valeurs lues s'appliquent seules à la fin de
 * l'extraction, sans transition de workflow. 0 = tout reste manuel
 * (comportement historique, défaut).
 */
final class Version20260813240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le paramètre ocr.seuil_application_auto (0 = arbitrage manuel).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'INSERT INTO parametre (id, nom, description, type, valeur, updated_at) VALUES (?, ?, ?, ?, NULL, NULL)',
            [
                (new Ulid())->toBinary(),
                'ocr.seuil_application_auto',
                'Seuil de confiance (en %) au-dessus duquel les valeurs lues par l\'OCR s\'appliquent automatiquement à la fiche. 0 = application manuelle uniquement.',
                'int',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM parametre WHERE nom = 'ocr.seuil_application_auto'");
    }
}
