<?php

declare(strict_types=1);

namespace App\GoLive;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Construit les étapes de `app:mdm:setup`. Outillage de mise en place jetable :
 * les contrôles « déjà fait » sont des requêtes DBAL directes (aucune méthode
 * ajoutée aux repositories du cœur) — supprimer src/GoLive/ suffit à retirer
 * l'outillage une fois le MDM en place.
 */
final readonly class SetupEtapesProvider
{
    private const FICHIER_CSV = 'lists_infos_produits_v2_06-08-2026_02H24.csv';
    private const FICHIER_DUMP = 'dump-production.sql';
    private const FICHIER_XLSX = 'listes_fiches_produits_06-08-2026_17H31.xlsx';

    public function __construct(
        private Connection $connection,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%env(S3_ACCESS_KEY)%')] private string $s3AccessKey,
        #[Autowire('%env(S3_SECRET_KEY)%')] private string $s3SecretKey,
        #[Autowire('%env(MARKETPLACE_SYNC_API_URL)%')] private string $marketplaceUrl,
        #[Autowire('%env(SALESFORCE_LOGIN_URL)%')] private string $salesforceLoginUrl,
        #[Autowire('%env(GEOAPIFY_API_KEY)%')] private string $geoapifyKey,
        #[Autowire('%env(GOOGLE_TRANSLATE_API_KEY)%')] private string $googleTranslateKey,
    ) {
    }

    /** @return list<Etape> */
    public function socle(bool $avecImport): array
    {
        return [
            new Etape(
                'base',
                'Base de données joignable',
                function (): EtapeEtat {
                    try {
                        $this->connection->executeQuery('SELECT 1');

                        return new EtapeEtat(EtapeStatut::Fait);
                    } catch (\Throwable $e) {
                        return new EtapeEtat(EtapeStatut::Bloquee, $e->getMessage());
                    }
                },
                instructions: 'Vérifier DATABASE_URL (relation Upsun ou .env.local).',
            ),
            new Etape(
                'migrations',
                'Migrations de schéma à jour',
                function (): EtapeEtat {
                    $fichiers = glob($this->projectDir.'/migrations/Version*.php');
                    $attendues = false === $fichiers ? 0 : count($fichiers);
                    try {
                        $jouees = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM doctrine_migration_versions');
                    } catch (\Throwable) {
                        return new EtapeEtat(EtapeStatut::Bloquee, 'table doctrine_migration_versions absente');
                    }
                    if ($jouees < $attendues) {
                        return new EtapeEtat(EtapeStatut::Bloquee, sprintf('%d migration(s) non jouée(s)', $attendues - $jouees));
                    }

                    return new EtapeEtat(EtapeStatut::Fait, sprintf('%d migration(s) jouée(s)', $jouees));
                },
                instructions: 'Jouer `php bin/console doctrine:migrations:migrate -n` (le hook deploy Upsun le fait automatiquement).',
            ),
            new Etape(
                's3',
                'Stockage S3 configuré',
                function () use ($avecImport): EtapeEtat {
                    if ('' !== trim($this->s3AccessKey) && '' !== trim($this->s3SecretKey)) {
                        return new EtapeEtat(EtapeStatut::Fait);
                    }

                    return new EtapeEtat(
                        $avecImport ? EtapeStatut::Bloquee : EtapeStatut::NonConfiguree,
                        'S3_ACCESS_KEY / S3_SECRET_KEY vides'.($avecImport ? ' — indispensables à l\'import des photos' : ''),
                    );
                },
                instructions: 'Poser S3_ACCESS_KEY / S3_SECRET_KEY (variables Upsun sensibles).',
            ),
            $this->variableOptionnelle('var-marketplace', 'Variable marketplace', $this->marketplaceUrl, 'MARKETPLACE_SYNC_API_URL vide — diffusion marketplace désactivée', 'Poser MARKETPLACE_SYNC_API_URL / _LOGIN / _PASSWORD pour brancher la marketplace (voir docs/exploitation/go-live-marketplace.md).'),
            $this->variableOptionnelle('var-salesforce', 'Variables Salesforce', $this->salesforceLoginUrl, 'SALESFORCE_LOGIN_URL vide — lecture Salesforce désactivée', 'Poser les secrets SALESFORCE_* puis lancer `app:salesforce:refresh-fiches`.'),
            $this->variableOptionnelle('var-geoapify', 'Clé Geoapify', $this->geoapifyKey, 'GEOAPIFY_API_KEY vide — vérification étrangère, autocomplétion et enrichissement Places désactivés', 'Poser GEOAPIFY_API_KEY.'),
            $this->variableOptionnelle('var-google', 'Clé Google Translate', $this->googleTranslateKey, 'GOOGLE_TRANSLATE_API_KEY vide — traductions automatiques désactivées', 'Poser GOOGLE_TRANSLATE_API_KEY.'),
            new Etape(
                'super-admin',
                'Compte super administrateur',
                fn (): EtapeEtat => $this->compter("SELECT COUNT(*) FROM account_user WHERE roles LIKE '%ROLE_SUPER_ADMIN%'") > 0
                    ? new EtapeEtat(EtapeStatut::Fait)
                    : new EtapeEtat(EtapeStatut::AFaire, 'aucun compte ROLE_SUPER_ADMIN'),
                instructions: 'Lancer `php bin/console app:user:create-super-admin <email>` (mot de passe demandé interactivement — non orchestrable).',
            ),
            new Etape(
                'sites-diffusion',
                'Référentiel des sites de diffusion',
                fn (): EtapeEtat => $this->compter("SELECT COUNT(*) FROM pim_site_diffusion WHERE code = 'marketplace_bp'") > 0
                    ? new EtapeEtat(EtapeStatut::Fait, sprintf('%d site(s) présents', $this->compter('SELECT COUNT(*) FROM pim_site_diffusion')))
                    : new EtapeEtat(EtapeStatut::AFaire),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:sites-diffusion:sync'),
            ),
            new Etape(
                'aeroports',
                'Référentiel des aéroports (OurAirports)',
                fn (): EtapeEtat => $this->referentielNonVide('pim_aeroport_reference'),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:acces:importer-aeroports'),
            ),
            new Etape(
                'grandes-villes',
                'Référentiel des grandes villes (GeoNames)',
                fn (): EtapeEtat => $this->referentielNonVide('pim_grande_ville_reference'),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:acces:importer-grandes-villes'),
            ),
            new Etape(
                'atout-france',
                'Référentiel des classements Atout France',
                fn (): EtapeEtat => $this->referentielNonVide('pim_classement_atout_france'),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:pim:importer-classements-atout-france'),
            ),
            new Etape(
                'traductions-planification',
                'Planification des traductions',
                static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::AFaire, 'volontairement hors orchestration (API Google payante)'),
                instructions: 'Une fois le stock de fiches stabilisé : `php bin/console app:translations:schedule`.',
            ),
            new Etape(
                'dashboard',
                'Tableau de bord recalculé',
                static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::AFaire, 'recalculé à chaque exécution'),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:dashboard:recompute'),
                toujoursExecuter: true,
            ),
        ];
    }

    /** @return list<Etape> */
    public function importLegacy(): array
    {
        $csv = fn (): ?string => $this->resoudreFichier(self::FICHIER_CSV);
        $dump = fn (): ?string => $this->resoudreFichier(self::FICHIER_DUMP);
        $xlsx = fn (): ?string => $this->resoudreFichier(self::FICHIER_XLSX);

        return [
            $this->etapeImportFichier('import-lieux', 'Import des fiches Lieu', 'pim_lieu', 'app:legacy:import-lieux', $csv, self::FICHIER_CSV),
            $this->etapeImportFichier('import-activites', 'Import des fiches Activité', 'pim_activite', 'app:legacy:import-activites', $csv, self::FICHIER_CSV),
            $this->etapeImportFichier('import-services', 'Import des fiches Service', 'pim_service_evenementiel', 'app:legacy:import-services', $csv, self::FICHIER_CSV),
            $this->etapeImportFichier('import-restaurants', 'Import des fiches Restaurant', 'pim_restaurant', 'app:legacy:import-restaurants', $csv, self::FICHIER_CSV),
            new Etape(
                'photos-semis',
                'Photos legacy — semis du suivi',
                fn (): EtapeEtat => $this->compter('SELECT COUNT(*) FROM etl_legacy_photo') > 0
                    ? new EtapeEtat(EtapeStatut::Fait, 'suivi déjà semé — jamais rejoué (unicité syspad/chemin)')
                    : new EtapeEtat(EtapeStatut::AFaire),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:legacy:import-photos', ['--seed-only' => true]),
            ),
            new Etape(
                'photos-import',
                'Photos legacy — import',
                function (): EtapeEtat {
                    /** @var array<string, int|string> $parStatut */
                    $parStatut = $this->connection->fetchAllKeyValue('SELECT status, COUNT(*) FROM etl_legacy_photo GROUP BY status');
                    $total = array_sum(array_map(intval(...), $parStatut));
                    if (0 === $total) {
                        return new EtapeEtat(EtapeStatut::AFaire, 'semis requis d\'abord');
                    }
                    $enAttente = (int) ($parStatut['pending'] ?? 0);
                    $detail = implode(', ', array_map(static fn (string $s, int|string $n): string => sprintf('%s : %d', $s, (int) $n), array_keys($parStatut), $parStatut));
                    if (0 === $enAttente) {
                        return new EtapeEtat(EtapeStatut::Fait, $detail);
                    }

                    return new EtapeEtat(EtapeStatut::AFaire, $detail);
                },
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:legacy:import-photos'),
                instructions: 'Import long (plusieurs heures en mono-processus) — parallélisable à la main avec `app:legacy:import-photos --shard=i/n` après le semis.',
            ),
            new Etape(
                'import-traductions',
                'Import des traductions legacy',
                fn (): EtapeEtat => $this->compter('SELECT COUNT(*) FROM enrichment_fiche_translation') > 0
                    ? new EtapeEtat(EtapeStatut::Fait)
                    : $this->etatFichier($dump(), self::FICHIER_DUMP),
                function (SousCommandeRunnerInterface $runner) use ($dump): bool {
                    $fichier = $dump();

                    return null !== $fichier && 0 === $runner->run('app:legacy:import-translations', ['--file' => $fichier]);
                },
                instructions: sprintf('Déposer `%s` dans var/import/ (ou var/tmp/import/).', self::FICHIER_DUMP),
            ),
            new Etape(
                'import-collaborateurs',
                'Import des collaborateurs legacy',
                fn (): EtapeEtat => $this->compter('SELECT COUNT(*) FROM pim_fiche_collaborateur') > 0
                    ? new EtapeEtat(EtapeStatut::Fait)
                    : $this->etatFichier($xlsx(), self::FICHIER_XLSX),
                function (SousCommandeRunnerInterface $runner) use ($xlsx): bool {
                    $fichier = $xlsx();

                    return null !== $fichier && 0 === $runner->run('app:legacy:import-collaborateurs', ['--file' => $fichier]);
                },
                instructions: sprintf('Déposer `%s` dans var/import/ (ou var/tmp/import/). Requiert les fiches, les sites et un super admin.', self::FICHIER_XLSX),
            ),
            new Etape(
                'conformite-photos',
                'Conformité photos des fiches publiées',
                static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::AFaire, 'idempotent, rejoué à chaque exécution'),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:fiches:conformite-photos', ['--appliquer' => true]),
                toujoursExecuter: true,
            ),
            new Etape(
                'visibilite-geo',
                'Attribution de la visibilité géographique',
                static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::AFaire, 'ajout seul, idempotent'),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:pim:attribuer-visibilite-geo'),
                toujoursExecuter: true,
            ),
            new Etape(
                'normalisation-localisation',
                'Normalisation des localisations',
                static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::AFaire, 'idempotent'),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:localisation:normaliser'),
                toujoursExecuter: true,
            ),
            new Etape(
                'analyse-textes',
                'Analyse des doublons de textes',
                static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::AFaire, 'enfile des messages, idempotent'),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:pim:analyze-texts'),
                toujoursExecuter: true,
            ),
            new Etape(
                'analyse-medias',
                'Analyse des médias (pHash)',
                static fn (): EtapeEtat => new EtapeEtat(EtapeStatut::AFaire, 'enfile des messages, idempotent'),
                static fn (SousCommandeRunnerInterface $runner): bool => 0 === $runner->run('app:dam:analyze-media'),
                toujoursExecuter: true,
            ),
        ];
    }

    private function variableOptionnelle(string $id, string $label, string $valeur, string $detailSiVide, string $instructions): Etape
    {
        return new Etape(
            $id,
            $label,
            static fn (): EtapeEtat => '' !== trim($valeur)
                ? new EtapeEtat(EtapeStatut::Fait)
                : new EtapeEtat(EtapeStatut::NonConfiguree, $detailSiVide),
            instructions: $instructions,
        );
    }

    /** @param \Closure(): ?string $fichier */
    private function etapeImportFichier(string $id, string $label, string $table, string $commande, \Closure $fichier, string $nomFichier): Etape
    {
        return new Etape(
            $id,
            $label,
            function () use ($table, $fichier, $nomFichier): EtapeEtat {
                $lignes = $this->compter(sprintf('SELECT COUNT(*) FROM %s', $table));
                if ($lignes > 0) {
                    return new EtapeEtat(EtapeStatut::Fait, sprintf('%d fiche(s)', $lignes));
                }

                return $this->etatFichier($fichier(), $nomFichier);
            },
            function (SousCommandeRunnerInterface $runner) use ($commande, $fichier): bool {
                $chemin = $fichier();

                return null !== $chemin && 0 === $runner->run($commande, ['--file' => $chemin]);
            },
            instructions: sprintf('Déposer `%s` dans var/import/ (ou var/tmp/import/).', $nomFichier),
        );
    }

    private function etatFichier(?string $chemin, string $nomFichier): EtapeEtat
    {
        if (null === $chemin) {
            return new EtapeEtat(EtapeStatut::Bloquee, sprintf('`%s` absent de var/import/ et var/tmp/import/', $nomFichier));
        }

        return new EtapeEtat(EtapeStatut::AFaire, sprintf('source : %s', $chemin));
    }

    private function resoudreFichier(string $nom): ?string
    {
        foreach ([$this->projectDir.'/var/import/'.$nom, $this->projectDir.'/var/tmp/import/'.$nom] as $chemin) {
            if (is_file($chemin)) {
                return $chemin;
            }
        }

        return null;
    }

    private function referentielNonVide(string $table): EtapeEtat
    {
        $lignes = $this->compter(sprintf('SELECT COUNT(*) FROM %s', $table));

        return $lignes > 0
            ? new EtapeEtat(EtapeStatut::Fait, sprintf('%d ligne(s)', $lignes))
            : new EtapeEtat(EtapeStatut::AFaire);
    }

    private function compter(string $sql): int
    {
        return (int) $this->connection->fetchOne($sql);
    }
}
