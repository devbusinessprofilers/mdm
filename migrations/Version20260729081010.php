<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729081010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée les entités PIM Lieu et Localisation à partir du dictionnaire des attributs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                CREATE TABLE pim_lieu (
                  id VARCHAR(26) NOT NULL,
                  code INT DEFAULT NULL,
                  label VARCHAR(255) DEFAULT NULL,
                  generale_typologie JSON NOT NULL,
                  generale_chaines_groupe_hot JSON NOT NULL,
                  generale_etab_rp TINYINT DEFAULT 0 NOT NULL,
                  generale_website_url VARCHAR(255) DEFAULT NULL,
                  generale_gamme VARCHAR(255) DEFAULT NULL,
                  generale_gamme_libelle VARCHAR(255) DEFAULT NULL,
                  dispo_lieu_privatisable TINYINT DEFAULT 0 NOT NULL,
                  dispo_jour_ouverture VARCHAR(255) DEFAULT NULL,
                  dispo_heure_ouverture_heure INT DEFAULT NULL,
                  dispo_heure_ouverture_minutes INT DEFAULT NULL,
                  dispo_heure_fermeture_heure INT DEFAULT NULL,
                  dispo_heure_fermeture_minutes INT DEFAULT NULL,
                  dispo_nom_periode VARCHAR(255) DEFAULT NULL,
                  dispo_date_fermeture_debut DATE DEFAULT NULL,
                  dispo_date_fermeture_fin DATE DEFAULT NULL,
                  access_aeroport JSON NOT NULL,
                  access_gare JSON NOT NULL,
                  access_metro JSON NOT NULL,
                  access_tramway JSON NOT NULL,
                  access_grande_ville JSON NOT NULL,
                  desc_generale_point_interet JSON NOT NULL,
                  pmr_acces TINYINT DEFAULT 0 NOT NULL,
                  pmr_details LONGTEXT DEFAULT NULL,
                  ta_thematique JSON NOT NULL,
                  ta_cadre_env JSON NOT NULL,
                  ta_ambiance JSON NOT NULL,
                  desc_generale LONGTEXT DEFAULT NULL,
                  atout_1 VARCHAR(255) DEFAULT NULL,
                  atout_2 VARCHAR(255) DEFAULT NULL,
                  atout_3 VARCHAR(255) DEFAULT NULL,
                  atout_4 VARCHAR(255) DEFAULT NULL,
                  atout_5 VARCHAR(255) DEFAULT NULL,
                  chambre_hebergement TINYINT DEFAULT 1 NOT NULL,
                  chambre_nb_total INT DEFAULT NULL,
                  chambre_nb_total_single INT DEFAULT NULL,
                  chambre_nb_total_twin INT DEFAULT NULL,
                  chambre_double INT DEFAULT NULL,
                  chambre_capacite_totale INT DEFAULT NULL,
                  chambre_desc_generale LONGTEXT DEFAULT NULL,
                  equipements JSON NOT NULL,
                  salle_reunion_exist TINYINT DEFAULT 1 NOT NULL,
                  salle_reunion_nb_total INT DEFAULT NULL,
                  salle_reunion_capacite_max_cocktail INT DEFAULT NULL,
                  salle_reunion_capacite_max_theatre INT DEFAULT NULL,
                  salle_reunion_capacite_min_theatre INT DEFAULT NULL,
                  salle_reunion_surface_min_reunion INT DEFAULT NULL,
                  salle_reunion_surface_max_reunion INT DEFAULT NULL,
                  salle_reunion_desc_salle_seminaire LONGTEXT DEFAULT NULL,
                  config_superficie INT DEFAULT NULL,
                  config_nom_salle VARCHAR(255) DEFAULT NULL,
                  config_superficie_reunion INT DEFAULT NULL,
                  config_superficie_u INT DEFAULT NULL,
                  config_superficie_classe INT DEFAULT NULL,
                  config_superficie_theatre INT DEFAULT NULL,
                  config_superficie_cabaret INT DEFAULT NULL,
                  config_superficie_banquet INT DEFAULT NULL,
                  config_superficie_cocktail INT DEFAULT NULL,
                  config_supericie_auditorium INT DEFAULT NULL,
                  config_lumiere_jour TINYINT DEFAULT NULL,
                  config_acces_pmr TINYINT DEFAULT NULL,
                  config_dansant TINYINT DEFAULT NULL,
                  config_climatisee TINYINT DEFAULT NULL,
                  config_plan_salle VARCHAR(255) DEFAULT NULL,
                  config_photo_salle VARCHAR(255) DEFAULT NULL,
                  services JSON NOT NULL,
                  technique_reunion JSON NOT NULL,
                  installation JSON NOT NULL,
                  bien_etre JSON NOT NULL,
                  rse_desc_generale LONGTEXT DEFAULT NULL,
                  achat_responsable JSON NOT NULL,
                  impact_env JSON NOT NULL,
                  impact_social JSON NOT NULL,
                  volume_achat_cat_esat_stpa JSON NOT NULL,
                  mobilite JSON NOT NULL,
                  certification JSON NOT NULL,
                  loisir_interne JSON NOT NULL,
                  loisir_externe_nom_presta JSON NOT NULL,
                  loisir_externe_nom_activite JSON NOT NULL,
                  loisir_externe_photo JSON NOT NULL,
                  restaurant_total INT DEFAULT NULL,
                  restaurant_salle_restauration INT DEFAULT NULL,
                  restaurant_capacite_debout INT DEFAULT NULL,
                  restaurant_capacite_assis INT DEFAULT NULL,
                  restaurant_soiree_dansante TINYINT DEFAULT NULL,
                  restaurant_cocktail_dinatoire TINYINT DEFAULT NULL,
                  restaurant_traiteur_sur_place TINYINT DEFAULT NULL,
                  restaurant_intervention_traiteur_externe TINYINT DEFAULT NULL,
                  restaurant_trateur_externe_client TINYINT DEFAULT NULL,
                  restaurant_privatisable TINYINT DEFAULT NULL,
                  restaurant_heure_interruption_musique VARCHAR(255) DEFAULT NULL,
                  type_cuisine JSON NOT NULL,
                  service_restauration JSON NOT NULL,
                  photo JSON NOT NULL,
                  pj_plan_general VARCHAR(255) DEFAULT NULL,
                  pj_support_commerciaux VARCHAR(255) DEFAULT NULL,
                  generale_youtube VARCHAR(255) DEFAULT NULL,
                  info_legale_attestation_vigilance_urssaf VARCHAR(255) DEFAULT NULL,
                  info_legale_assurance_rc_pro VARCHAR(255) DEFAULT NULL,
                  info_legale_nom VARCHAR(255) DEFAULT NULL,
                  info_legale_forme_juridique VARCHAR(255) DEFAULT NULL,
                  info_legale_rue_postal VARCHAR(255) DEFAULT NULL,
                  info_legale_adresse_2 VARCHAR(255) DEFAULT NULL,
                  info_legale_code_postal VARCHAR(255) DEFAULT NULL,
                  info_legale_ville VARCHAR(255) DEFAULT NULL,
                  infor_legale_pays VARCHAR(255) DEFAULT NULL,
                  info_legale_siret VARCHAR(255) DEFAULT NULL,
                  info_legale_num_tva VARCHAR(255) DEFAULT NULL,
                  info_legale_assujetti_tva TINYINT DEFAULT NULL,
                  info_legale_tva VARCHAR(255) DEFAULT NULL,
                  info_legale_type_de_procedure_judiciaire VARCHAR(255) DEFAULT NULL,
                  adresse_facturation_nom VARCHAR(255) DEFAULT NULL,
                  adresse_facturation_rue_postal VARCHAR(255) DEFAULT NULL,
                  adresse_facturation_code_postal VARCHAR(255) DEFAULT NULL,
                  adresse_facturation_ville VARCHAR(255) DEFAULT NULL,
                  adresse_facturation_pays VARCHAR(255) DEFAULT NULL,
                  adresse_facturation_num_tva VARCHAR(255) DEFAULT NULL,
                  contact_facturation_nom VARCHAR(255) DEFAULT NULL,
                  contact_facturation_prenom VARCHAR(255) DEFAULT NULL,
                  contact_facturation_email VARCHAR(255) DEFAULT NULL,
                  contact_facturation_telephone VARCHAR(255) DEFAULT NULL,
                  mode_paiement_bic VARCHAR(255) DEFAULT NULL,
                  mode_paiement_iban VARCHAR(255) DEFAULT NULL,
                  mode_paiement_rib VARCHAR(255) DEFAULT NULL,
                  mode_paiement_accept_deduction_com TINYINT DEFAULT NULL,
                  mode_paiement_carte_liste JSON NOT NULL,
                  mode_paiement_affacturage TINYINT DEFAULT NULL,
                  affacturage_bic VARCHAR(255) DEFAULT NULL,
                  affacturage_iban VARCHAR(255) DEFAULT NULL,
                  affacturage_rib VARCHAR(255) DEFAULT NULL,
                  cond_paie_acc_signature VARCHAR(255) DEFAULT NULL,
                  cond_paie_ann_signature VARCHAR(255) DEFAULT NULL,
                  commission_applicable VARCHAR(255) DEFAULT NULL,
                  date_paiement_sold VARCHAR(255) DEFAULT NULL,
                  cond_gen_vente_depot_doc VARCHAR(255) DEFAULT NULL,
                  conv_part_signee_le VARCHAR(255) DEFAULT NULL,
                  conv_part_taux VARCHAR(255) DEFAULT NULL,
                  conv_part_fichier VARCHAR(255) DEFAULT NULL,
                  signataire_email VARCHAR(255) DEFAULT NULL,
                  signataire_prenom VARCHAR(255) DEFAULT NULL,
                  signataire_nom VARCHAR(255) DEFAULT NULL,
                  seminaire_journee_demi_journee_etude NUMERIC(12, 2) DEFAULT NULL,
                  seminaire_journee_journee_etude NUMERIC(12, 2) DEFAULT NULL,
                  seminaire_journee_demi_journee_etude_cocktail NUMERIC(12, 2) DEFAULT NULL,
                  seminaire_journee_journee_etude_cocktail NUMERIC(12, 2) DEFAULT NULL,
                  seminaire_nuitee_semi_residentiel NUMERIC(12, 2) DEFAULT NULL,
                  seminaire_nuitee_residentiel NUMERIC(12, 2) DEFAULT NULL,
                  seminaire_nuitee_residentiel_all_inclusive NUMERIC(12, 2) DEFAULT NULL,
                  loc_salle_seul_demi_journee NUMERIC(12, 2) DEFAULT NULL,
                  loc_salle_seul_journee NUMERIC(12, 2) DEFAULT NULL,
                  loc_salle_seul_soiree NUMERIC(12, 2) DEFAULT NULL,
                  cs_cocktail_dejeunatoire_10_pers NUMERIC(12, 2) DEFAULT NULL,
                  cs_cocktail_dinatoire NUMERIC(12, 2) DEFAULT NULL,
                  cs_soiree_dansante NUMERIC(12, 2) DEFAULT NULL,
                  cs_soiree_diner_assis NUMERIC(12, 2) DEFAULT NULL,
                  tarif_rest_dejeuner_assis NUMERIC(12, 2) DEFAULT NULL,
                  tarif_rest_diner_assis NUMERIC(12, 2) DEFAULT NULL,
                  tarif_rest_opt_vin NUMERIC(12, 2) DEFAULT NULL,
                  tarif_rest_opt_alcool NUMERIC(12, 2) DEFAULT NULL,
                  tarif_rest_forfait_personalise NUMERIC(12, 2) DEFAULT NULL,
                  heberg_group_tarif_chambre_single NUMERIC(12, 2) DEFAULT NULL,
                  heberg_group_tarif_chambre_twin NUMERIC(12, 2) DEFAULT NULL,
                  heberg_group_tarif_chambre_double NUMERIC(12, 2) DEFAULT NULL,
                  site_premium JSON NOT NULL,
                  mice_statut VARCHAR(255) DEFAULT NULL,
                  afficher_contact TINYINT DEFAULT 0 NOT NULL,
                  published TINYINT DEFAULT 0 NOT NULL,
                  created_at DATETIME NOT NULL,
                  updated_at DATETIME NOT NULL,
                  localisation_id VARCHAR(26) DEFAULT NULL,
                  UNIQUE INDEX UNIQ_D766A114C68BE09C (localisation_id),
                  INDEX IDX_PIM_LIEU_PUBLISHED (published),
                  UNIQUE INDEX UNIQ_PIM_LIEU_CODE (code),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL,
        );
        $this->addSql(
            <<<'SQL'
                CREATE TABLE pim_localisation (
                  id VARCHAR(26) NOT NULL,
                  pays VARCHAR(255) DEFAULT NULL,
                  region VARCHAR(255) DEFAULT NULL,
                  departement VARCHAR(255) DEFAULT NULL,
                  rue_postale VARCHAR(255) DEFAULT NULL,
                  code_postal VARCHAR(32) DEFAULT NULL,
                  ville VARCHAR(255) DEFAULT NULL,
                  arrondissement VARCHAR(32) DEFAULT NULL,
                  latitude NUMERIC(18, 15) DEFAULT NULL,
                  longitude NUMERIC(18, 15) DEFAULT NULL,
                  created_at DATETIME NOT NULL,
                  updated_at DATETIME NOT NULL,
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL,
        );
        $this->addSql(
            <<<'SQL'
                ALTER TABLE
                  pim_lieu
                ADD
                  CONSTRAINT FK_D766A114C68BE09C FOREIGN KEY (localisation_id) REFERENCES pim_localisation (id) ON DELETE
                SET
                  NULL
            SQL,
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_lieu DROP FOREIGN KEY FK_D766A114C68BE09C',
        );
        $this->addSql('DROP TABLE pim_lieu');
        $this->addSql('DROP TABLE pim_localisation');
    }
}
