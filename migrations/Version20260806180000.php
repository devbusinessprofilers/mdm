<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\ActiviteLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Historiquement : seed de l'attribut unique SOUS_THEMATIQUE_ACTIVITE.
 * Réécrite le 2026-08-13 lors de l'éclatement en un attribut par thématique
 * (`{THEMATIQUE}_SS`) : une base neuve reçoit directement l'état final, une
 * base déjà migrée est remappée par Version20260813110000.
 */
final class Version20260806180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les listes de sous-thématiques d\'activité (Bible, multi-sélection par thématique).';
    }

    public function up(Schema $schema): void
    {
        foreach (ActiviteLovCatalog::sousThematiqueAttributes() as $attribute => $thematique) {
            $this->addSql(
                "INSERT IGNORE INTO pim_attribute_definition (id, code, label, translatable, filterable, master_application) VALUES (?, ?, ?, 0, 1, 'pim')",
                [ActiviteLovCatalog::attributeId($attribute), $attribute, 'Sous-thématiques — '.$thematique],
            );
            $position = 0;
            foreach (ActiviteLovCatalog::choicesFor($attribute) as $valueCode => $label) {
                $this->addSql(
                    'INSERT IGNORE INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (?, ?, ?, ?, ?)',
                    [
                        ActiviteLovCatalog::valueId($attribute, $valueCode),
                        ActiviteLovCatalog::attributeId($attribute),
                        $valueCode,
                        $label,
                        $position++,
                    ],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_keys(ActiviteLovCatalog::sousThematiqueAttributes()) as $attribute) {
            $this->addSql('DELETE FROM pim_fiche_attribute_value WHERE attribute_code = ?', [$attribute]);
            $this->addSql('DELETE v FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id WHERE a.code = ?', [$attribute]);
            $this->addSql('DELETE FROM pim_attribute_definition WHERE code = ?', [$attribute]);
        }
    }
}
