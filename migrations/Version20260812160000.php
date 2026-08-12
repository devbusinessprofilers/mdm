<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20260812160000 extends AbstractMigration
{
    private const NOMS = [
        'photos.min_lieu',
        'photos.max_lieu',
        'photos.min_autres',
        'photos.max_autres',
        'dam.image_poids_max_mo',
        'dam.image_largeur_min',
        'dam.image_hauteur_min',
        'dam.document_poids_max_mo',
        'dam.support_commercial_poids_max_mo',
        'dam.delai_alerte_droits_jours',
        'ocr.pdf_poids_max_mo',
        'ocr.pdf_pages_max',
        'compte.invitation_validite_heures',
        'compte.reset_validite_heures',
        'compte.purge_jetons_jours',
        'compte.mot_de_passe_longueur_min',
        'compte.max_destinataires_demandes',
    ];

    public function getDescription(): string
    {
        return 'Ajoute au catalogue des paramètres les réglages photos, médias, OCR et comptes jusqu\'ici codés en dur.';
    }

    public function up(Schema $schema): void
    {
        // Valeur NULL = non surchargé, la variable d'env reste la valeur
        // effective : le déploiement est un no-op métier.
        $catalogue = [
            ['photos.min_lieu', 'int', 'Nombre minimum de photos pour soumettre et diffuser un Lieu. Les fiches déjà publiées ne sont réévaluées qu\'à leur prochaine modification.'],
            ['photos.max_lieu', 'int', 'Nombre maximum de photos d\'un Lieu.'],
            ['photos.min_autres', 'int', 'Nombre minimum de photos pour soumettre et diffuser une Activité, un Restaurant ou un Service.'],
            ['photos.max_autres', 'int', 'Nombre maximum de photos d\'une Activité, d\'un Restaurant ou d\'un Service.'],
            ['dam.image_poids_max_mo', 'int', 'Poids maximum d\'une image envoyée, en Mo.'],
            ['dam.image_largeur_min', 'int', 'Largeur minimale d\'une image envoyée, en pixels. En dessous de 960, le rendu « large » (960×480) sera étiré.'],
            ['dam.image_hauteur_min', 'int', 'Hauteur minimale d\'une image envoyée, en pixels. En dessous de 480, le rendu « large » (960×480) sera étiré.'],
            ['dam.document_poids_max_mo', 'int', 'Poids maximum d\'un document (hors supports commerciaux), en Mo.'],
            ['dam.support_commercial_poids_max_mo', 'int', 'Poids maximum d\'un support commercial, en Mo.'],
            ['dam.delai_alerte_droits_jours', 'int', 'Nombre de jours avant l\'échéance des droits d\'une ressource à partir duquel elle est signalée « à échéance ».'],
            ['ocr.pdf_poids_max_mo', 'int', 'Poids maximum d\'un PDF soumis à l\'extraction documentaire, en Mo.'],
            ['ocr.pdf_pages_max', 'int', 'Nombre maximum de pages d\'un PDF soumis à l\'extraction documentaire.'],
            ['compte.invitation_validite_heures', 'int', 'Durée de validité d\'une invitation interne, en heures. Un nouveau lien invalide les précédents.'],
            ['compte.reset_validite_heures', 'int', 'Durée de validité d\'un lien de réinitialisation de mot de passe, en heures.'],
            ['compte.purge_jetons_jours', 'int', 'Ancienneté d\'expiration, en jours, au-delà de laquelle app:account:purge-expired-tokens supprime invitations et resets.'],
            ['compte.mot_de_passe_longueur_min', 'int', 'Longueur minimale des mots de passe des comptes internes.'],
            ['compte.max_destinataires_demandes', 'int', 'Nombre maximum de destinataires des demandes d\'affiliation d\'une fiche.'],
        ];
        foreach ($catalogue as [$nom, $type, $description]) {
            $this->addSql(
                'INSERT INTO parametre (id, nom, description, type, valeur, updated_at) VALUES (?, ?, ?, ?, NULL, NULL)',
                [(new Ulid())->toBinary(), $nom, $description, $type],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DELETE FROM parametre WHERE nom IN ('.implode(', ', array_fill(0, count(self::NOMS), '?')).')',
            self::NOMS,
        );
    }
}
