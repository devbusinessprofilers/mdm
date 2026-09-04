<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Facturation & partenariat pour toutes les gammes (maquette portail) : le
 * bloc administratif quitte le Lieu (pim_lieu_administratif, clé lieu_id)
 * pour la Fiche (pim_fiche_administratif, clé fiche_id), données reprises ;
 * nouveaux champs : paiement par carte, 3 acomptes (date LOV + %), un
 * pourcentage par tranche d'annulation.
 */
final class Version20260904120000 extends AbstractMigration
{
    private const COLONNES = [
        'info_legale_nom', 'info_legale_forme_juridique', 'info_legale_rue_postal', 'info_legale_adresse_2',
        'info_legale_code_postal', 'info_legale_ville', 'infor_legale_pays', 'info_legale_siret', 'info_legale_num_tva',
        'info_legale_assujetti_tva', 'info_legale_tva', 'info_legale_type_de_procedure_judiciaire',
        'adresse_facturation_nom', 'adresse_facturation_rue_postal', 'adresse_facturation_code_postal',
        'adresse_facturation_ville', 'adresse_facturation_pays', 'adresse_facturation_num_tva',
        'contact_facturation_nom', 'contact_facturation_prenom', 'contact_facturation_email', 'contact_facturation_telephone',
        'mode_paiement_bic', 'mode_paiement_iban', 'mode_paiement_accept_deduction_com', 'mode_paiement_affacturage',
        'affacturage_bic', 'affacturage_iban', 'cond_paie_acc_signature', 'cond_paie_ann_signature',
        'commission_applicable', 'date_paiement_sold', 'conv_part_signee_le', 'conv_part_taux',
        'signataire_email', 'signataire_prenom', 'signataire_nom',
    ];

    private const BOOLEENS = ['info_legale_assujetti_tva', 'mode_paiement_accept_deduction_com', 'mode_paiement_affacturage'];

    public function getDescription(): string
    {
        return 'Facturation & partenariat porté par la fiche (pim_fiche_administratif) + paiement carte, acomptes, annulation par tranche.';
    }

    public function up(Schema $schema): void
    {
        $definitions = [];
        foreach (self::COLONNES as $colonne) {
            $definitions[] = sprintf('%s %s DEFAULT NULL', $colonne, in_array($colonne, self::BOOLEENS, true) ? 'TINYINT(1)' : 'VARCHAR(255)');
        }
        $definitions[] = 'mode_paiement_carte TINYINT(1) DEFAULT NULL';
        foreach ([1, 2, 3] as $i) {
            $definitions[] = sprintf('cond_paie_acc_date_%d VARCHAR(255) DEFAULT NULL', $i);
            $definitions[] = sprintf('cond_paie_acc_pourcentage_%d INT DEFAULT NULL', $i);
        }
        foreach (range(1, 9) as $i) {
            $definitions[] = sprintf('cond_paie_ann_pourcentage_%d INT DEFAULT NULL', $i);
        }
        $this->addSql(sprintf(
            'CREATE TABLE pim_fiche_administratif (fiche_id BINARY(16) NOT NULL, %s, PRIMARY KEY(fiche_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            implode(', ', $definitions),
        ));
        $this->addSql('ALTER TABLE pim_fiche_administratif ADD CONSTRAINT FK_FICHE_ADMINISTRATIF_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE');
        $colonnes = implode(', ', self::COLONNES);
        $this->addSql(sprintf(
            'INSERT INTO pim_fiche_administratif (fiche_id, %s) SELECT l.fiche_id, %s FROM pim_lieu_administratif a INNER JOIN pim_lieu l ON l.id = a.lieu_id',
            $colonnes,
            implode(', ', array_map(static fn (string $c): string => 'a.'.$c, self::COLONNES)),
        ));
        $this->addSql('DROP TABLE pim_lieu_administratif');
    }

    public function down(Schema $schema): void
    {
        $definitions = [];
        foreach (self::COLONNES as $colonne) {
            $definitions[] = sprintf('%s %s DEFAULT NULL', $colonne, in_array($colonne, self::BOOLEENS, true) ? 'TINYINT(1)' : 'VARCHAR(255)');
        }
        $this->addSql(sprintf(
            'CREATE TABLE pim_lieu_administratif (lieu_id BINARY(16) NOT NULL, %s, PRIMARY KEY(lieu_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            implode(', ', $definitions),
        ));
        $this->addSql('ALTER TABLE pim_lieu_administratif ADD CONSTRAINT FK_LIEU_ADMINISTRATIF_LIEU FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE CASCADE');
        $colonnes = implode(', ', self::COLONNES);
        $this->addSql(sprintf(
            'INSERT INTO pim_lieu_administratif (lieu_id, %s) SELECT l.id, %s FROM pim_fiche_administratif a INNER JOIN pim_lieu l ON l.fiche_id = a.fiche_id',
            $colonnes,
            implode(', ', array_map(static fn (string $c): string => 'a.'.$c, self::COLONNES)),
        ));
        $this->addSql('DROP TABLE pim_fiche_administratif');
    }
}
