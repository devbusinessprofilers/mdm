<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\ActiviteLovCatalog;
use App\Pim\Lov\LieuLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les valeurs LOV décidées avec le métier (2026-08-17) au registre
 * runtime `pim_attribute_value`, avec les identifiants stables du catalogue
 * (mêmes helpers que le seed initial) :
 *  - types de lieu : « Lieu avec incentive intégré », « Résidence /
 *    Appart'hôtel », « Salle / Bureau » (repliés jusqu'ici sur des codes
 *    approximatifs) ;
 *  - thématique d'activité : « Insolites » (490 fiches activité legacy).
 *
 * Idempotent : ON DUPLICATE KEY UPDATE. La définition d'attribut existe déjà
 * pour les deux référentiels — seules les valeurs sont ajoutées.
 */
final class Version20260817100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute au registre LOV les types de lieu incentive/résidence/salle-bureau et la thématique d\'activité Insolites.';
    }

    public function up(Schema $schema): void
    {
        $position = 40;
        foreach (['GENERALE_TYPOLOGIE_41', 'GENERALE_TYPOLOGIE_42', 'GENERALE_TYPOLOGIE_43'] as $code) {
            $this->seedValue(
                LieuLovCatalog::valueId('GENERALE_TYPOLOGIE', $code),
                LieuLovCatalog::attributeId('GENERALE_TYPOLOGIE'),
                $code,
                LieuLovCatalog::allChoices()['GENERALE_TYPOLOGIE'][$code],
                $position++,
            );
        }

        $this->seedValue(
            ActiviteLovCatalog::valueId('THEMATIQUE_ACTIVITE', 'TA_INSOLITE'),
            ActiviteLovCatalog::attributeId('THEMATIQUE_ACTIVITE'),
            'TA_INSOLITE',
            ActiviteLovCatalog::allChoices()['THEMATIQUE_ACTIVITE']['TA_INSOLITE'],
            9,
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DELETE FROM pim_attribute_value WHERE code IN (?, ?, ?, ?)',
            ['GENERALE_TYPOLOGIE_41', 'GENERALE_TYPOLOGIE_42', 'GENERALE_TYPOLOGIE_43', 'TA_INSOLITE'],
        );
    }

    private function seedValue(int $id, int $attributeId, string $code, string $label, int $position): void
    {
        $this->addSql(
            'INSERT INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (?, ?, ?, ?, ?) '
            .'ON DUPLICATE KEY UPDATE label = VALUES(label), position = VALUES(position)',
            [$id, $attributeId, $code, $label, $position],
        );
    }
}
