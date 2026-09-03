<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\LieuLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bible « VERSION BP » (Clem, 2026-09) — listes de valeurs de la gamme Lieu.
 *
 *  1. « Événements de prédilection » alignée sur la bible : 9 valeurs ajoutées
 *     (codes _9 à _17 du catalogue), les données des 3 valeurs disparues sont
 *     reportées vers leur équivalent (« Convention / Congrès » → « Convention »,
 *     « Réunion / Comité de direction » → « Comité de direction »,
 *     « Soirée / Réception » → « Afterwork »), en dédoublonnant si la fiche
 *     porte déjà la cible, puis les 3 anciennes valeurs sont désactivées
 *     (conservées pour l'historique, plus proposées).
 *  2. Typologie, groupe/chaîne hôtelière et événements : positions réécrites
 *     dans l'ordre alphabétique des libellés (valeurs actives en base, y
 *     compris celles créées en admin) — l'ordre d'affichage suit `position`.
 *
 * La synchro LOV vers la marketplace propage le référentiel ; les payloads
 * de fiches déjà poussés ne changent qu'au prochain push de chaque fiche.
 */
final class Version20260903100000 extends AbstractMigration
{
    private const ATTRIBUT_EVENEMENTS = 'GENERALE_EVENEMENTS_PREDILECTION';

    /** ancien code => code cible */
    private const REMAP = [
        'GENERALE_EVENEMENTS_PREDILECTION_2' => 'GENERALE_EVENEMENTS_PREDILECTION_11',
        'GENERALE_EVENEMENTS_PREDILECTION_3' => 'GENERALE_EVENEMENTS_PREDILECTION_12',
        'GENERALE_EVENEMENTS_PREDILECTION_7' => 'GENERALE_EVENEMENTS_PREDILECTION_13',
    ];

    private const NOUVEAUX = [
        'GENERALE_EVENEMENTS_PREDILECTION_9', 'GENERALE_EVENEMENTS_PREDILECTION_10', 'GENERALE_EVENEMENTS_PREDILECTION_11',
        'GENERALE_EVENEMENTS_PREDILECTION_12', 'GENERALE_EVENEMENTS_PREDILECTION_13', 'GENERALE_EVENEMENTS_PREDILECTION_14',
        'GENERALE_EVENEMENTS_PREDILECTION_15', 'GENERALE_EVENEMENTS_PREDILECTION_16', 'GENERALE_EVENEMENTS_PREDILECTION_17',
    ];

    private const ALPHABETIQUES = ['GENERALE_TYPOLOGIE', 'GENERALE_CHAINES_GROUPE_HOT', self::ATTRIBUT_EVENEMENTS];

    public function getDescription(): string
    {
        return 'Bible VERSION BP : LOV Événements de prédilection alignée (seed, remap, désactivation) et tri alphabétique des 3 LOV.';
    }

    public function up(Schema $schema): void
    {
        $attributeId = LieuLovCatalog::attributeId(self::ATTRIBUT_EVENEMENTS);
        $catalogue = LieuLovCatalog::allChoices()[self::ATTRIBUT_EVENEMENTS];
        $position = 100;
        foreach (self::NOUVEAUX as $code) {
            $this->addSql(
                'INSERT INTO pim_attribute_value (id, attribute_id, code, label, position, active) VALUES (?, ?, ?, ?, ?, 1) '
                .'ON DUPLICATE KEY UPDATE label = VALUES(label), active = 1',
                [LieuLovCatalog::valueId(self::ATTRIBUT_EVENEMENTS, $code), $attributeId, $code, $catalogue[$code], $position++],
            );
        }

        foreach (self::REMAP as $ancien => $cible) {
            $ancienId = LieuLovCatalog::valueId(self::ATTRIBUT_EVENEMENTS, $ancien);
            $cibleId = LieuLovCatalog::valueId(self::ATTRIBUT_EVENEMENTS, $cible);
            // Report vers la cible pour les fiches qui ne la portent pas déjà
            // (table dérivée : MySQL refuse la sous-requête directe sur la
            // table mise à jour), puis purge des doublons restants.
            $this->addSql(
                'UPDATE pim_fiche_attribute_value v SET v.value_id = ? WHERE v.value_id = ? AND v.fiche_id NOT IN ('
                .'SELECT t.fiche_id FROM (SELECT fiche_id FROM pim_fiche_attribute_value WHERE value_id = ?) t)',
                [$cibleId, $ancienId, $cibleId],
            );
            $this->addSql('DELETE FROM pim_fiche_attribute_value WHERE value_id = ?', [$ancienId]);
            $this->addSql('UPDATE pim_attribute_value SET active = 0 WHERE id = ?', [$ancienId]);
        }

        foreach (self::ALPHABETIQUES as $attribut) {
            foreach ($this->ordreAlphabetique($attribut) as $position => $code) {
                $this->addSql('UPDATE pim_attribute_value SET position = ? WHERE code = ?', [$position, $code]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_keys(self::REMAP) as $ancien) {
            $this->addSql('UPDATE pim_attribute_value SET active = 1 WHERE code = ?', [$ancien]);
        }
        // Les données reportées ne sont pas restaurées (perte d'information
        // acceptée) ; les valeurs nouvelles sont retirées si aucune fiche ne
        // les porte encore.
        $this->addSql(
            'DELETE v FROM pim_attribute_value v WHERE v.code IN ('.implode(', ', array_fill(0, count(self::NOUVEAUX), '?')).') '
            .'AND NOT EXISTS (SELECT 1 FROM pim_fiche_attribute_value f WHERE f.value_id = v.id)',
            self::NOUVEAUX,
        );
    }

    /**
     * Codes de l'attribut dans l'ordre alphabétique de leurs libellés : lignes
     * actives en base (l'admin peut en avoir créé), complétées des valeurs
     * insérées par cette migration, moins celles qu'elle désactive.
     *
     * @return list<string>
     */
    private function ordreAlphabetique(string $attribut): array
    {
        $labels = [];
        /** @var list<array{code: string, label: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT v.code, v.label FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id '
            .'WHERE a.code = ? AND v.active = 1',
            [$attribut],
        );
        foreach ($rows as $row) {
            $labels[$row['code']] = $row['label'];
        }
        if (self::ATTRIBUT_EVENEMENTS === $attribut) {
            foreach (self::NOUVEAUX as $code) {
                $labels[$code] = LieuLovCatalog::allChoices()[$attribut][$code];
            }
            foreach (array_keys(self::REMAP) as $ancien) {
                unset($labels[$ancien]);
            }
        }
        $collator = new \Collator('fr_FR');
        $collator->setStrength(\Collator::SECONDARY); // insensible à la casse, accents respectés
        uasort($labels, static fn (string $a, string $b): int => $collator->compare($a, $b) ?: 0);

        return array_keys($labels);
    }
}
