<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

/**
 * Catalogue les paramètres des sources d'enrichissement dans /admin/parametres :
 * ils existaient comme défauts d'environnement (services.yaml) mais n'avaient
 * jamais été insérés dans la table parametre — impossibles à surcharger à chaud.
 * Ces gates conditionnent les scans batch ET le bouton « Enrichir ce qui
 * manque » de la fiche. Les 4 gates d'enrichissement sont insérées ACTIVES
 * (surcharge '1' — décision 2026-08-25) : les sources marchent d'emblée, la
 * désactivation reste possible dans /admin/parametres. openai.actif reste au
 * défaut env (off), en attente du compte OpenAI.
 */
final class Version20260825120000 extends AbstractMigration
{
    /** @var list<array{0: string, 1: string, 2: string, 3: ?string}> nom, type, description, valeur insérée (null = défaut env) */
    private const PARAMETRES = [
        ['sirene.verif_statut_actif', 'bool', 'Active l’enrichissement Sirene : statut d’établissement, backfill SIRET/TVA (batch + bouton « Enrichir ce qui manque »).', '1'],
        ['sirene.rescan_apres_jours', 'int', 'Délai avant re-scan Sirene d’une fiche inchangée (jours).', null],
        ['geoapify.enrichissement_places', 'bool', 'Active l’enrichissement Geoapify Places des restaurants : cuisine, équipements, accessibilité (batch + bouton « Enrichir ce qui manque »).', '1'],
        ['geoapify.rescan_apres_jours', 'int', 'Délai avant re-scan Geoapify d’une fiche inchangée (jours).', null],
        // DATAtourisme reste au défaut env (off) : le code est prêt mais la
        // source ne sera pas utilisée tant que le flux n'est pas mis en place.
        ['datatourisme.import_actif', 'bool', 'Active l’enrichissement DATAtourisme des lieux et activités : descriptions, équipements (batch + bouton « Enrichir ce qui manque »).', null],
        ['datatourisme.rescan_apres_jours', 'int', 'Délai avant re-scan DATAtourisme d’une fiche inchangée (jours).', null],
        ['wikidata.detection_chaine', 'bool', 'Active la détection de chaîne hôtelière (référentiel interne + Wikidata) des lieux (batch + bouton « Enrichir ce qui manque »).', '1'],
        ['wikidata.rescan_apres_jours', 'int', 'Délai avant re-scan Wikidata d’une fiche inchangée (jours).', null],
        ['openai.actif', 'bool', 'Active les fonctions IA OpenAI : pilule « Suggérer », reco médias, source IA du bouton « Enrichir ce qui manque ».', null],
    ];

    public function getDescription(): string
    {
        return 'Catalogue les paramètres d’enrichissement dans /admin/parametres, gates actives par défaut.';
    }

    public function up(Schema $schema): void
    {
        // Catalogue : valeur NULL = non surchargé (la variable d'env reste la
        // valeur effective, voir Version20260812100000) ; '1' = gate activée
        // d'emblée, quel que soit l'environnement.
        foreach (self::PARAMETRES as [$nom, $type, $description, $valeur]) {
            $this->addSql(
                'INSERT INTO parametre (id, nom, description, type, valeur, updated_at) VALUES (?, ?, ?, ?, ?, NULL)',
                [(new Ulid())->toBinary(), $nom, $description, $type, $valeur],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $noms = array_map(static fn (array $parametre): string => "'".$parametre[0]."'", self::PARAMETRES);
        $this->addSql('DELETE FROM parametre WHERE nom IN ('.implode(', ', $noms).')');
    }
}
