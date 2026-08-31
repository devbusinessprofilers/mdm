<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le téléphone de fiche disparaît du MDM : le numéro affiché sur la
 * marketplace (bp_produit.telephone) est un numéro interne géré là-bas, le
 * référentiel n'a pas à le porter. Le payload de synchro n'envoie plus la clé
 * (la marketplace ne touche la colonne que si la clé est présente). Les
 * suggestions Geoapify « Téléphone » encore en attente deviennent sans objet.
 */
final class Version20260831130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire pim_fiche.telephone (numéro interne marketplace) et purge les suggestions téléphone en attente.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM pim_fiche_suggestion WHERE champ IN ('lieu_telephone', 'restaurant_telephone') AND statut = 'en_attente'");
        $this->addSql('ALTER TABLE pim_fiche DROP telephone');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche ADD telephone VARCHAR(20) DEFAULT NULL');
    }
}
