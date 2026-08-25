<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime pim_lieu.chaine_hoteliere : la chaîne hôtelière n'a qu'un seul
 * champ, le sélecteur LOV GENERALE_CHAINES_GROUPE_HOT (décision 2026-08-25 —
 * la colonne libre du PT4 Wikidata doublonnait le champ de l'éditeur).
 * Avant le drop, reprise best-effort : les valeurs dont le libellé correspond
 * à une entrée de la LOV sont converties en lien d'attribut.
 */
final class Version20260825160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime pim_lieu.chaine_hoteliere (la LOV GENERALE_CHAINES_GROUPE_HOT est le seul champ chaîne), avec reprise best-effort.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO pim_fiche_attribute_value (fiche_id, attribute_code, value_id)
            SELECT l.fiche_id, 'GENERALE_CHAINES_GROUPE_HOT', v.id
            FROM pim_lieu l
            INNER JOIN pim_attribute_definition a ON a.code = 'GENERALE_CHAINES_GROUPE_HOT'
            INNER JOIN pim_attribute_value v ON v.attribute_id = a.id AND LOWER(v.label) = LOWER(l.chaine_hoteliere)
            WHERE l.chaine_hoteliere IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM pim_fiche_attribute_value x WHERE x.fiche_id = l.fiche_id AND x.value_id = v.id)");
        $this->addSql('ALTER TABLE pim_lieu DROP chaine_hoteliere');
    }

    public function down(Schema $schema): void
    {
        // Les valeurs libres d'origine ne sont pas restaurées (reprises dans la LOV au up).
        $this->addSql('ALTER TABLE pim_lieu ADD chaine_hoteliere VARCHAR(120) DEFAULT NULL');
    }
}
