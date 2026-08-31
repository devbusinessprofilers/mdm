<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La photo principale n'est plus une catégorie exclusive (usage_code
 * PHOTO_PRINCIPALE) mais la première photo de l'ordre (position, id).
 * Les principales flaguées passent en tête de leurs fiches (renumérotation
 * 0..n-1, ordre relatif conservé) puis la catégorie disparaît au profit de
 * la catégorie neutre. SQL brut sans événement Doctrine : updated_at
 * intact, aucune resynchronisation ni transition de workflow déclenchée.
 */
final class Version20260831140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photo principale dérivée de l’ordre : principales flaguées renumérotées en tête, usage PHOTO_PRINCIPALE reversé en PHOTO_DIVERSE.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE pim_ressource_lieu r
            JOIN (
                SELECT id,
                       ROW_NUMBER() OVER (
                           PARTITION BY fiche_id
                           ORDER BY (usage_code = 'PHOTO_PRINCIPALE') DESC, position ASC, id ASC
                       ) - 1 AS nouvelle_position
                FROM pim_ressource_lieu
                WHERE nature = 'photo'
                  AND fiche_id IN (
                      SELECT fiche_id FROM (
                          SELECT DISTINCT fiche_id FROM pim_ressource_lieu
                          WHERE nature = 'photo' AND usage_code = 'PHOTO_PRINCIPALE'
                      ) f
                  )
            ) ordre ON ordre.id = r.id
            SET r.position = ordre.nouvelle_position
            SQL);
        $this->addSql("UPDATE pim_ressource_lieu SET usage_code = 'PHOTO_DIVERSE' WHERE usage_code = 'PHOTO_PRINCIPALE'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('L’information « photo flaguée principale » n’existe plus : la principale est dérivée de l’ordre.');
    }
}
