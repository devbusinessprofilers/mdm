<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\ActiviteLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Éclate SOUS_THEMATIQUE_ACTIVITE en un attribut par thématique
 * (`{THEMATIQUE}_SS`), comme les listes des lieux. Les codes de valeurs ne
 * changent pas ; les identifiants stables (dérivés du code d'attribut) et
 * les valeurs de fiches sont remappés. Sans effet sur une base neuve où
 * l'ancien attribut n'existe plus.
 */
final class Version20260813110000 extends AbstractMigration
{
    private const OLD_ATTRIBUTE = 'SOUS_THEMATIQUE_ACTIVITE';

    public function getDescription(): string
    {
        return 'Éclate les sous-thématiques d\'activité en un attribut par thématique.';
    }

    public function up(Schema $schema): void
    {
        foreach (ActiviteLovCatalog::sousThematiqueAttributes() as $attribute => $thematique) {
            $this->addSql(
                "INSERT IGNORE INTO pim_attribute_definition (id, code, label, translatable, filterable, master_application) VALUES (?, ?, ?, 0, 1, 'pim')",
                [ActiviteLovCatalog::attributeId($attribute), $attribute, 'Sous-thématiques — '.$thematique],
            );
            $position = 0;
            foreach (ActiviteLovCatalog::choicesFor($attribute) as $code => $label) {
                $oldId = self::stableId('value:'.self::OLD_ATTRIBUTE.':'.$code);
                $newId = ActiviteLovCatalog::valueId($attribute, $code);
                // Ligne du dictionnaire : déplacée si elle existe sous
                // l'ancien attribut, créée sinon (base neuve).
                $this->addSql(
                    'UPDATE IGNORE pim_attribute_value SET id = ?, attribute_id = ? WHERE id = ?',
                    [$newId, ActiviteLovCatalog::attributeId($attribute), $oldId],
                );
                $this->addSql(
                    'INSERT IGNORE INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (?, ?, ?, ?, ?)',
                    [$newId, ActiviteLovCatalog::attributeId($attribute), $code, $label, $position],
                );
                ++$position;
                $this->addSql(
                    'UPDATE pim_fiche_attribute_value SET attribute_code = ?, value_id = ? WHERE attribute_code = ? AND value_id = ?',
                    [$attribute, $newId, self::OLD_ATTRIBUTE, $oldId],
                );
            }
            // Valeurs ajoutées par l'admin sous l'ancien attribut : simple
            // rattachement par préfixe, leurs identifiants restent valables.
            $this->addSql(
                "UPDATE pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id SET v.attribute_id = ? WHERE a.code = ? AND v.code LIKE ?",
                [ActiviteLovCatalog::attributeId($attribute), self::OLD_ATTRIBUTE, str_replace('_', '\\_', $attribute).'\\_%'],
            );
            $this->addSql(
                'UPDATE pim_fiche_attribute_value f SET f.attribute_code = ? WHERE f.attribute_code = ? AND f.value_id IN (SELECT id FROM pim_attribute_value WHERE attribute_id = ?)',
                [$attribute, self::OLD_ATTRIBUTE, ActiviteLovCatalog::attributeId($attribute)],
            );
        }
        // L'ancienne définition disparaît si plus rien ne s'y rattache.
        $this->addSql(
            'DELETE d FROM pim_attribute_definition d LEFT JOIN pim_attribute_value v ON v.attribute_id = d.id WHERE d.code = ? AND v.id IS NULL',
            [self::OLD_ATTRIBUTE],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "INSERT IGNORE INTO pim_attribute_definition (id, code, label, translatable, filterable, master_application) VALUES (?, ?, ?, 0, 1, 'pim')",
            [self::stableId('attribute:'.self::OLD_ATTRIBUTE), self::OLD_ATTRIBUTE, 'Sous-thématiques de l’activité'],
        );
        foreach (array_keys(ActiviteLovCatalog::sousThematiqueAttributes()) as $attribute) {
            foreach (array_keys(ActiviteLovCatalog::choicesFor($attribute)) as $code) {
                $oldId = self::stableId('value:'.self::OLD_ATTRIBUTE.':'.$code);
                $newId = ActiviteLovCatalog::valueId($attribute, $code);
                $this->addSql(
                    'UPDATE IGNORE pim_attribute_value SET id = ?, attribute_id = ? WHERE id = ?',
                    [$oldId, self::stableId('attribute:'.self::OLD_ATTRIBUTE), $newId],
                );
                $this->addSql(
                    'UPDATE pim_fiche_attribute_value SET attribute_code = ?, value_id = ? WHERE attribute_code = ? AND value_id = ?',
                    [self::OLD_ATTRIBUTE, $oldId, $attribute, $newId],
                );
            }
            $this->addSql('DELETE FROM pim_attribute_definition WHERE code = ?', [$attribute]);
        }
    }

    private static function stableId(string $value): int
    {
        return (int) sprintf('%u', crc32($value));
    }
}
