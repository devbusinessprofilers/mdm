<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\ServiceLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sous-prestations des services événementiels (CDC « Typologie de
 * prestataire ») : un attribut de dictionnaire par famille — comme les
 * listes des lieux — plus les champs capacité/durée de la prestation prévus
 * au CDC et absents jusqu'ici.
 */
final class Version20260813100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les dictionnaires de sous-prestations par famille et les champs participants/durée des services.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_service_evenementiel'
            .' ADD participants_min INT DEFAULT NULL,'
            .' ADD participants_max INT DEFAULT NULL,'
            .' ADD duree_minutes INT DEFAULT NULL',
        );

        foreach (ServiceLovCatalog::sousPrestationAttributes() as $attribute => $famille) {
            $this->addSql(
                "INSERT IGNORE INTO pim_attribute_definition (id, code, label, translatable, filterable, master_application) VALUES (?, ?, ?, 0, 1, 'pim')",
                [ServiceLovCatalog::sousPrestationAttributeId($attribute), $attribute, 'Sous-prestations — '.$famille],
            );
            $position = 0;
            foreach (ServiceLovCatalog::sousPrestationsFor($attribute) as $valueCode => $label) {
                $this->addSql(
                    'INSERT IGNORE INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (?, ?, ?, ?, ?)',
                    [
                        ServiceLovCatalog::sousPrestationValueId($attribute, $valueCode),
                        ServiceLovCatalog::sousPrestationAttributeId($attribute),
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
        foreach (array_keys(ServiceLovCatalog::sousPrestationAttributes()) as $attribute) {
            $this->addSql('DELETE FROM pim_fiche_attribute_value WHERE attribute_code = ?', [$attribute]);
            $this->addSql('DELETE v FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id WHERE a.code = ?', [$attribute]);
            $this->addSql('DELETE FROM pim_attribute_definition WHERE code = ?', [$attribute]);
        }
        $this->addSql(
            'ALTER TABLE pim_service_evenementiel'
            .' DROP COLUMN participants_min,'
            .' DROP COLUMN participants_max,'
            .' DROP COLUMN duree_minutes',
        );
    }
}
