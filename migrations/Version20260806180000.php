<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\ActiviteLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la liste des sous-thématiques d\'activité (Bible, multi-sélection par thématique).';
    }

    public function up(Schema $schema): void
    {
        $code = 'SOUS_THEMATIQUE_ACTIVITE';
        $this->addSql(
            "INSERT IGNORE INTO pim_attribute_definition (id, code, label, translatable, filterable, master_application) VALUES (?, ?, ?, 0, 1, 'pim')",
            [ActiviteLovCatalog::attributeId($code), $code, 'Sous-thématiques de l’activité'],
        );
        $position = 0;
        foreach (ActiviteLovCatalog::allChoices()[$code] as $valueCode => $label) {
            $this->addSql(
                'INSERT IGNORE INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (?, ?, ?, ?, ?)',
                [
                    ActiviteLovCatalog::valueId($code, $valueCode),
                    ActiviteLovCatalog::attributeId($code),
                    $valueCode,
                    $label,
                    $position++,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM pim_fiche_attribute_value WHERE attribute_code = 'SOUS_THEMATIQUE_ACTIVITE'");
        $this->addSql("DELETE v FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id WHERE a.code = 'SOUS_THEMATIQUE_ACTIVITE'");
        $this->addSql("DELETE FROM pim_attribute_definition WHERE code = 'SOUS_THEMATIQUE_ACTIVITE'");
    }
}
