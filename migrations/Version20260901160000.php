<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Horaires d'ouverture : le tableau par jour devient la source de vérité
 * unique. L'amplitude globale du Lieu (4 colonnes dérivées min/max) et les
 * deux heures globales du Restaurant sont supprimées — les contrats sortants
 * (payload marketplace, API portail, complétude) dérivent désormais
 * l'amplitude du JSON. La colonne texte legacy dispo_jour_ouverture (0 valeur
 * en réel) part avec.
 */
final class Version20260901160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Horaires par jour = source unique : drop amplitude Lieu + heures globales Restaurant, ajout pim_restaurant.horaires_jours.';
    }

    public function up(Schema $schema): void
    {
        // Sauvegarde idempotente avant suppression : un lieu n'ayant que
        // l'amplitude globale (0 en réel au 2026-09-01) la voit déclinée sur
        // ses jours d'ouverture LOV, comme le faisait le repli du getter.
        $this->addSql(<<<'SQL'
            UPDATE pim_lieu l
            SET l.dispo_horaires_jours = (
                SELECT JSON_OBJECTAGG(av.code, JSON_OBJECT(
                    'ouverture', CASE WHEN l.dispo_heure_ouverture_heure IS NULL THEN NULL
                        ELSE CONCAT(LPAD(l.dispo_heure_ouverture_heure, 2, '0'), ':', LPAD(COALESCE(l.dispo_heure_ouverture_minutes, 0), 2, '0')) END,
                    'fermeture', CASE WHEN l.dispo_heure_fermeture_heure IS NULL THEN NULL
                        ELSE CONCAT(LPAD(l.dispo_heure_fermeture_heure, 2, '0'), ':', LPAD(COALESCE(l.dispo_heure_fermeture_minutes, 0), 2, '0')) END
                ))
                FROM pim_fiche_attribute_value fav
                JOIN pim_attribute_value av ON av.id = fav.value_id
                WHERE fav.fiche_id = l.fiche_id AND fav.attribute_code = 'DISPO_JOUR_OUVERTURE'
            )
            WHERE l.dispo_horaires_jours IS NULL
              AND (l.dispo_heure_ouverture_heure IS NOT NULL OR l.dispo_heure_fermeture_heure IS NOT NULL)
            SQL);
        $this->addSql('ALTER TABLE pim_lieu DROP dispo_jour_ouverture, DROP dispo_heure_ouverture_heure, DROP dispo_heure_ouverture_minutes, DROP dispo_heure_fermeture_heure, DROP dispo_heure_fermeture_minutes');
        $this->addSql('ALTER TABLE pim_restaurant ADD horaires_jours JSON DEFAULT NULL, DROP heure_ouverture, DROP heure_fermeture');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu ADD dispo_jour_ouverture VARCHAR(255) DEFAULT NULL, ADD dispo_heure_ouverture_heure INT DEFAULT NULL, ADD dispo_heure_ouverture_minutes INT DEFAULT NULL, ADD dispo_heure_fermeture_heure INT DEFAULT NULL, ADD dispo_heure_fermeture_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pim_restaurant ADD heure_ouverture TIME DEFAULT NULL, ADD heure_fermeture TIME DEFAULT NULL, DROP horaires_jours');
    }
}
