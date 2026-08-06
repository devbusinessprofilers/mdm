<?php

declare(strict_types=1);

namespace App\Etl\Command;

use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Entity\Fiche;
use App\Pim\Import\Legacy\LegacySqlDumpReader;
use App\Pim\Import\Legacy\LegacyTranslationRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Ulid;

/**
 * Import manuel des traductions legacy depuis le DUMP SQL de production
 * (aucune base annexe requise : rejouable en production au déploiement).
 * Les traductions dont la source française correspond au PIM sont disponibles
 * (origin manual) ; les autres sont importées en obsolète, traduction
 * conservée, pour re-validation humaine. Idempotent : les couples
 * (fiche, champ, locale) déjà présents sont ignorés.
 */
#[AsCommand(name: 'app:legacy:import-translations', description: 'Importe les traductions legacy depuis le dump SQL de production.')]
final class ImportLegacyTranslationsCommand extends Command
{
    private const DEFAULT_FILE = '/var/import/dump-production.sql';
    private const ACTOR = 'import-legacy';

    /** field CSV legacy (i18n_translation_lieu) → [field_path, label, colonne source bp_lieu]. */
    private const LIEU_FIELDS = [
        'chambresFr' => ['lieu.chambreDescGenerale', 'Description de l’hébergement', 'chambres_fr'],
        'sallesFr' => ['lieu.salleReunionDescSalleSeminaire', 'Description des salles de séminaire', 'salles_fr'],
    ];

    /** type de fiche → [field_path, label] pour descriptionFr (niveau produit). */
    private const DESCRIPTION_FIELDS = [
        'lieu' => ['lieu.descGenerale', 'Description générale'],
        'activite' => ['activite.descriptionGenerale', 'Description générale'],
        'restaurant' => ['restaurant.descriptionGenerale', 'Description générale'],
        'service_evenementiel' => ['service.descriptionGenerale', 'Description générale'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LegacySqlDumpReader $reader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Chemin du dump SQL de production.', self::DEFAULT_FILE)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyse sans rien écrire en base.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximum de traductions à importer.')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Ne traite que cette locale (ex. en).')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots de flush.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getOption('file');
        $dryRun = (bool) $input->getOption('dry-run');
        $limit = null === $input->getOption('limit') ? null : max(1, (int) $input->getOption('limit'));
        $onlyLocale = null === $input->getOption('locale') ? null : strtolower((string) $input->getOption('locale'));
        $batchSize = max(1, (int) $input->getOption('batch-size'));

        if (!is_file($file)) {
            $io->error(sprintf('Dump SQL introuvable : %s', $file));

            return Command::INVALID;
        }

        $connection = $this->entityManager->getConnection();
        // Référentiels PIM préchargés (id binaire → hex pour les clés de tableau).
        $fichesByCode = [];
        foreach ($connection->fetchAllAssociative('SELECT id, code, type FROM pim_fiche') as $row) {
            $fichesByCode[(int) $row['code']] = [Ulid::fromBinary($row['id']), (string) $row['type'], strtolower(bin2hex($row['id']))];
        }
        $pimSources = [];
        foreach ($connection->fetchAllAssociative('SELECT id, desc_generale, chambre_desc_generale, salle_reunion_desc_salle_seminaire FROM pim_lieu') as $row) {
            $pimSources[strtolower(bin2hex($row['id']))] = [
                'description' => $row['desc_generale'],
                'lieu.chambreDescGenerale' => $row['chambre_desc_generale'],
                'lieu.salleReunionDescSalleSeminaire' => $row['salle_reunion_desc_salle_seminaire'],
            ];
        }
        foreach (['pim_activite', 'pim_restaurant', 'pim_service_evenementiel'] as $table) {
            foreach ($connection->fetchAllAssociative("SELECT id, description_generale FROM $table") as $row) {
                $pimSources[strtolower(bin2hex($row['id']))] = ['description' => $row['description_generale']];
            }
        }
        $existing = [];
        foreach ($connection->fetchAllAssociative('SELECT LOWER(HEX(fiche_id)) AS fiche, field_path, locale FROM enrichment_fiche_translation') as $row) {
            $existing[$row['fiche'].'|'.$row['field_path'].'|'.$row['locale']] = true;
        }

        $counters = ['disponibles' => 0, 'obsolètes (à revalider)' => 0, 'déjà présentes' => 0, 'fiche introuvable' => 0, 'source PIM vide' => 0, 'champ non mappé' => 0, 'locale ou contenu invalide' => 0];
        // Le code fiche = « Id syspad » du CSV = bp_produit.syspad_id (PAS
        // bp_produit.id). bp_lieu arrive avant bp_produit dans le dump : on
        // conserve ses lignes brutes et on résout produit_id → syspad au
        // moment des traductions.
        $produitSyspad = [];
        $legacyProduits = [];
        $legacyLieux = [];
        $imported = 0;
        $pendingInBatch = 0;

        foreach ($this->reader->rows($file, ['bp_lieu', 'bp_produit', 'i18n_translation_lieu', 'i18n_translation_produit']) as [$table, $row]) {
            if ('bp_produit' === $table) {
                $syspad = null === $row['syspad_id'] ? null : (int) $row['syspad_id'];
                if (null !== $syspad && isset($fichesByCode[$syspad])) {
                    $produitSyspad[(int) $row['id']] = $syspad;
                    $legacyProduits[(int) $row['id']] = $row['description_fr'];
                }
                continue;
            }
            if ('bp_lieu' === $table) {
                $legacyLieux[(int) $row['id']] = [(int) $row['produit_id'], $row['chambres_fr'], $row['salles_fr']];
                continue;
            }
            if (null !== $limit && $imported >= $limit) {
                break;
            }

            $locale = SupportedLocale::tryFrom(strtolower((string) $row['locale']));
            $content = trim((string) $row['content']);
            if (null === $locale || SupportedLocale::Fr === $locale || '' === $content) {
                ++$counters['locale ou contenu invalide'];
                continue;
            }
            if (null !== $onlyLocale && $locale->value !== $onlyLocale) {
                continue;
            }

            if ('i18n_translation_produit' === $table) {
                $produitId = (int) $row['produit_id'];
                $code = $produitSyspad[$produitId] ?? null;
                if (null === $code) {
                    ++$counters['fiche introuvable'];
                    continue;
                }
                if ('descriptionFr' !== $row['field'] || !isset(self::DESCRIPTION_FIELDS[$fichesByCode[$code][1]])) {
                    ++$counters['champ non mappé'];
                    continue;
                }
                [$fieldPath, $label] = self::DESCRIPTION_FIELDS[$fichesByCode[$code][1]];
                $pimSource = $pimSources[$fichesByCode[$code][2]]['description'] ?? null;
                $legacySource = $legacyProduits[$produitId] ?? null;
            } else {
                $lieuLegacy = $legacyLieux[(int) $row['lieu_id']] ?? null;
                $code = null === $lieuLegacy ? null : ($produitSyspad[$lieuLegacy[0]] ?? null);
                if (null === $code) {
                    ++$counters['fiche introuvable'];
                    continue;
                }
                [, $chambresFr, $sallesFr] = $lieuLegacy;
                $mapping = self::LIEU_FIELDS[(string) $row['field']] ?? null;
                if (null === $mapping) {
                    ++$counters['champ non mappé'];
                    continue;
                }
                [$fieldPath, $label] = $mapping;
                $pimSource = $pimSources[$fichesByCode[$code][2]][$fieldPath] ?? null;
                $legacySource = 'chambresFr' === $row['field'] ? $chambresFr : $sallesFr;
            }

            $key = $fichesByCode[$code][2].'|'.$fieldPath.'|'.$locale->value;
            if (isset($existing[$key])) {
                ++$counters['déjà présentes'];
                continue;
            }
            $decision = LegacyTranslationRule::decide($pimSource, $legacySource);
            if (LegacyTranslationRule::SKIP === $decision) {
                ++$counters['source PIM vide'];
                continue;
            }
            $existing[$key] = true;
            ++$imported;
            if (LegacyTranslationRule::AVAILABLE === $decision) {
                ++$counters['disponibles'];
            } else {
                ++$counters['obsolètes (à revalider)'];
            }
            if ($dryRun) {
                continue;
            }

            $fiche = $this->entityManager->getReference(Fiche::class, $fichesByCode[$code][0]);
            if (LegacyTranslationRule::AVAILABLE === $decision) {
                $translation = new FicheTranslation($fiche, $fieldPath, $label, $locale, trim((string) $pimSource));
                $translation->applyManual($content, self::ACTOR);
            } else {
                // Traduction d'une source française qui a changé : elle est
                // conservée mais marquée obsolète (schedule bascule une
                // traduction manuelle existante en obsolète).
                $translation = new FicheTranslation($fiche, $fieldPath, $label, $locale, trim((string) $legacySource));
                $translation->applyManual($content, self::ACTOR);
                $translation->schedule($label, trim((string) $pimSource), (string) new Ulid());
            }
            $this->entityManager->persist($translation);
            if (++$pendingInBatch >= $batchSize) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $pendingInBatch = 0;
                $io->write(sprintf("\r%d traductions importées…", $imported));
            }
        }
        if (!$dryRun && $pendingInBatch > 0) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
        $io->newLine();

        $io->table(['Compteur', 'Valeur'], array_map(null, array_keys($counters), array_values($counters)));
        if ($dryRun) {
            $io->note('Dry-run : aucune écriture effectuée.');
        }

        return Command::SUCCESS;
    }
}
