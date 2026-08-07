<?php

declare(strict_types=1);

namespace App\Etl\Command;

use App\Enrichment\Entity\AttributeValueTranslation;
use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Import\Legacy\LegacySqlDumpReader;
use App\Pim\Import\Legacy\LegacyTranslationRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Ulid;

/**
 * Import manuel des traductions legacy depuis le DUMP SQL de production
 * (aucune base annexe requise : rejouable en production au déploiement).
 *
 * Trois familles :
 *  - descriptions par fiche (i18n_translation_produit / i18n_translation_lieu),
 *    jointes par bp_produit.syspad_id = code fiche ;
 *  - atouts et loisirs : référentiels partagés (bp_atout / bp_loisir) → un
 *    dictionnaire texte français → traductions, appliqué aux champs libres du
 *    PIM (atout1..5, plus, atouts, loisirInterne) avec les variantes de
 *    troncature de l'import des fiches ;
 *  - libellés de LOV (thèmes, types, objectifs, équipements) → traductions
 *    des valeurs d'attributs PIM, appariées par libellé français.
 *
 * Les traductions cohérentes avec la source française actuelle sont
 * disponibles (origin manual) ; les descriptions divergentes sont importées
 * en obsolète pour re-validation. Idempotent sur les clés uniques du PIM.
 */
#[AsCommand(name: 'app:legacy:import-translations', description: 'Importe les traductions legacy depuis le dump SQL de production.')]
final class ImportLegacyTranslationsCommand extends Command
{
    private const DEFAULT_FILE = 'var/tmp/import/dump-production.sql';
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

    /** Référentiels legacy de textes libres : table du nom → table i18n + clé étrangère. */
    private const TEXT_REFERENTIALS = [
        'bp_atout' => ['i18n_translation_atout', 'atout_id'],
        'bp_loisir' => ['i18n_translation_loisir', 'loisir_id'],
    ];

    /** Référentiels legacy de libellés LOV : table du nom → table i18n + clé étrangère. */
    private const LOV_REFERENTIALS = [
        'bp_theme' => ['i18n_translation_theme', 'theme_id'],
        'bp_type_lieu' => ['i18n_translation_type_lieu', 'type_lieu_id'],
        'bp_type_prestataire' => ['i18n_translation_type_prestataire', 'type_prestataire_id'],
        'bp_type_activite' => ['i18n_translation_type_activite', 'type_activite_id'],
        'bp_objectif' => ['i18n_translation_objectif', 'objectif_id'],
        'bp_equipement' => ['i18n_translation_equipement', 'equipement_id'],
    ];

    /** @var array<string, array{0: Ulid, 1: string, 2: string}> code → [id fiche, type, hex] */
    private array $fichesByCode = [];
    /** @var array<string, array<string, ?string>> hex fiche → sources françaises PIM */
    private array $pimSources = [];
    /** @var array<string, true> clés (fiche|path|locale) existantes */
    private array $existingFiche = [];
    /** @var array<string, array<string, string>> texte français → [locale => traduction] */
    private array $textDictionary = [];
    /** @var array<string, int> compteurs du rapport */
    private array $counters = [];
    private int $pendingInBatch = 0;
    private int $batchSize = 100;
    private bool $dryRun = false;
    private ?string $onlyLocale = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LegacySqlDumpReader $reader,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Chemin du dump SQL de production.', $this->projectDir.'/'.self::DEFAULT_FILE)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyse sans rien écrire en base.')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Ne traite que cette locale (ex. en).')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots de flush.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getOption('file');
        $this->dryRun = (bool) $input->getOption('dry-run');
        $this->onlyLocale = null === $input->getOption('locale') ? null : strtolower((string) $input->getOption('locale'));
        $this->batchSize = max(1, (int) $input->getOption('batch-size'));
        $this->counters = [
            'descriptions disponibles' => 0, 'descriptions obsolètes (à revalider)' => 0,
            'atouts / loisirs traduits' => 0, 'atouts / loisirs sans traduction' => 0,
            'libellés LOV traduits' => 0, 'libellés LOV sans traduction' => 0,
            'déjà présentes' => 0, 'fiche introuvable' => 0, 'source PIM vide' => 0,
            'champ non mappé' => 0, 'locale ou contenu invalide' => 0,
        ];
        $this->pendingInBatch = 0;

        if (!is_file($file)) {
            $io->error(sprintf('Dump SQL introuvable : %s', $file));

            return Command::INVALID;
        }

        $this->loadPimReferentials();
        $this->importDescriptions($file, $io);
        $this->importFreeTexts($io);
        $this->importLovLabels($io);
        $this->flushPending(true);
        $io->newLine();
        $io->table(['Compteur', 'Valeur'], array_map(null, array_keys($this->counters), array_values($this->counters)));
        if ($this->dryRun) {
            $io->note('Dry-run : aucune écriture effectuée.');
        }

        return Command::SUCCESS;
    }

    private function loadPimReferentials(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach ($connection->fetchAllAssociative('SELECT id, code, type FROM pim_fiche') as $row) {
            $this->fichesByCode[(int) $row['code']] = [Ulid::fromBinary($row['id']), (string) $row['type'], strtolower(bin2hex($row['id']))];
        }
        foreach ($connection->fetchAllAssociative('SELECT id, desc_generale, chambre_desc_generale, salle_reunion_desc_salle_seminaire, atout_1, atout_2, atout_3, atout_4, atout_5, loisir_interne FROM pim_lieu') as $row) {
            $this->pimSources[strtolower(bin2hex($row['id']))] = [
                'description' => $row['desc_generale'],
                'lieu.chambreDescGenerale' => $row['chambre_desc_generale'],
                'lieu.salleReunionDescSalleSeminaire' => $row['salle_reunion_desc_salle_seminaire'],
                'atouts' => [$row['atout_1'], $row['atout_2'], $row['atout_3'], $row['atout_4'], $row['atout_5']],
                'liste' => $row['loisir_interne'],
            ];
        }
        foreach ([['pim_activite', 'plus'], ['pim_restaurant', 'atouts'], ['pim_service_evenementiel', null]] as [$table, $listColumn]) {
            $columns = 'id, description_generale'.(null === $listColumn ? '' : ", $listColumn");
            foreach ($connection->fetchAllAssociative("SELECT $columns FROM $table") as $row) {
                $this->pimSources[strtolower(bin2hex($row['id']))] = [
                    'description' => $row['description_generale'],
                    'liste' => null === $listColumn ? null : $row[$listColumn],
                ];
            }
        }
        foreach ($connection->fetchAllAssociative('SELECT LOWER(HEX(fiche_id)) AS fiche, field_path, locale FROM enrichment_fiche_translation') as $row) {
            $this->existingFiche[$row['fiche'].'|'.$row['field_path'].'|'.$row['locale']] = true;
        }
    }

    private function importDescriptions(string $file, SymfonyStyle $io): void
    {
        // Le code fiche = « Id syspad » du CSV = bp_produit.syspad_id (PAS
        // bp_produit.id). bp_lieu arrive avant bp_produit dans le dump : on
        // conserve ses lignes brutes et on résout produit_id → syspad au
        // moment des traductions.
        $produitSyspad = [];
        $legacyProduits = [];
        $legacyLieux = [];
        $textNoms = [];
        $lovNoms = [];
        $tables = array_merge(
            ['bp_lieu', 'bp_produit', 'i18n_translation_lieu', 'i18n_translation_produit'],
            array_keys(self::TEXT_REFERENTIALS),
            array_column(self::TEXT_REFERENTIALS, 0),
            array_keys(self::LOV_REFERENTIALS),
            array_column(self::LOV_REFERENTIALS, 0),
        );
        $i18nTextTables = [];
        foreach (self::TEXT_REFERENTIALS as $referentialTable => [$i18nTable, $foreignKey]) {
            $i18nTextTables[$i18nTable] = [$referentialTable, $foreignKey];
        }
        $i18nLovTables = [];
        foreach (self::LOV_REFERENTIALS as $referentialTable => [$i18nTable, $foreignKey]) {
            $i18nLovTables[$i18nTable] = [$referentialTable, $foreignKey];
        }
        $this->lovDictionary = [];

        $processed = 0;
        foreach ($this->reader->rows($file, $tables) as [$table, $row]) {
            if ('bp_produit' === $table) {
                $syspad = null === $row['syspad_id'] ? null : (int) $row['syspad_id'];
                if (null !== $syspad && isset($this->fichesByCode[$syspad])) {
                    $produitSyspad[(int) $row['id']] = $syspad;
                    $legacyProduits[(int) $row['id']] = $row['description_fr'];
                }
                continue;
            }
            if ('bp_lieu' === $table) {
                $legacyLieux[(int) $row['id']] = [(int) $row['produit_id'], $row['chambres_fr'], $row['salles_fr']];
                continue;
            }
            if (isset(self::TEXT_REFERENTIALS[$table]) || isset(self::LOV_REFERENTIALS[$table])) {
                $textNoms[$table][(int) $row['id']] = trim((string) $row['nom']);
                continue;
            }
            if (isset($i18nTextTables[$table]) || isset($i18nLovTables[$table])) {
                [$referentialTable, $foreignKey] = $i18nTextTables[$table] ?? $i18nLovTables[$table];
                $locale = $this->locale($row);
                $content = trim((string) $row['content']);
                $nom = $textNoms[$referentialTable][(int) $row[$foreignKey]] ?? '';
                if (null === $locale || '' === $content || '' === $nom) {
                    continue;
                }
                if (isset($i18nTextTables[$table])) {
                    $this->textDictionary[$nom][$locale->value] = $content;
                } else {
                    $this->lovDictionary[$nom][$locale->value] = $content;
                }
                continue;
            }

            // i18n_translation_produit / i18n_translation_lieu : descriptions par fiche.
            $locale = $this->locale($row);
            $content = trim((string) $row['content']);
            if (null === $locale || '' === $content) {
                ++$this->counters['locale ou contenu invalide'];
                continue;
            }
            if ('i18n_translation_produit' === $table) {
                $produitId = (int) $row['produit_id'];
                $code = $produitSyspad[$produitId] ?? null;
                if (null === $code) {
                    ++$this->counters['fiche introuvable'];
                    continue;
                }
                if ('descriptionFr' !== $row['field'] || !isset(self::DESCRIPTION_FIELDS[$this->fichesByCode[$code][1]])) {
                    ++$this->counters['champ non mappé'];
                    continue;
                }
                [$fieldPath, $label] = self::DESCRIPTION_FIELDS[$this->fichesByCode[$code][1]];
                $pimSource = $this->pimSources[$this->fichesByCode[$code][2]]['description'] ?? null;
                $legacySource = $legacyProduits[$produitId] ?? null;
            } else {
                $lieuLegacy = $legacyLieux[(int) $row['lieu_id']] ?? null;
                $code = null === $lieuLegacy ? null : ($produitSyspad[$lieuLegacy[0]] ?? null);
                if (null === $code) {
                    ++$this->counters['fiche introuvable'];
                    continue;
                }
                [, $chambresFr, $sallesFr] = $lieuLegacy;
                $mapping = self::LIEU_FIELDS[(string) $row['field']] ?? null;
                if (null === $mapping) {
                    ++$this->counters['champ non mappé'];
                    continue;
                }
                [$fieldPath, $label] = $mapping;
                $pimSource = $this->pimSources[$this->fichesByCode[$code][2]][$fieldPath] ?? null;
                $legacySource = 'chambresFr' === $row['field'] ? $chambresFr : $sallesFr;
            }

            $key = $this->fichesByCode[$code][2].'|'.$fieldPath.'|'.$locale->value;
            if (isset($this->existingFiche[$key])) {
                ++$this->counters['déjà présentes'];
                continue;
            }
            $decision = LegacyTranslationRule::decide($pimSource, $legacySource);
            if (LegacyTranslationRule::SKIP === $decision) {
                ++$this->counters['source PIM vide'];
                continue;
            }
            $this->existingFiche[$key] = true;
            if (LegacyTranslationRule::AVAILABLE === $decision) {
                ++$this->counters['descriptions disponibles'];
            } else {
                ++$this->counters['descriptions obsolètes (à revalider)'];
            }
            if ($this->dryRun) {
                continue;
            }
            $fiche = $this->entityManager->getReference(Fiche::class, $this->fichesByCode[$code][0]);
            if (LegacyTranslationRule::AVAILABLE === $decision) {
                $translation = new FicheTranslation($fiche, $fieldPath, $label, $locale, trim((string) $pimSource));
                $translation->applyManual($content, self::ACTOR);
            } else {
                // Traduction d'une source française qui a changé : conservée
                // mais marquée obsolète pour re-validation humaine.
                $translation = new FicheTranslation($fiche, $fieldPath, $label, $locale, trim((string) $legacySource));
                $translation->applyManual($content, self::ACTOR);
                $translation->schedule($label, trim((string) $pimSource), (string) new Ulid());
            }
            $this->entityManager->persist($translation);
            $this->flushPending();
            if (0 === ++$processed % 5000) {
                $io->write(sprintf("\r%d descriptions traitées…", $processed));
            }
        }
        if ($processed > 0) {
            $io->newLine();
        }
    }

    /** @var array<string, array<string, string>> libellé français → [locale => traduction] */
    private array $lovDictionary = [];

    /**
     * Atouts et loisirs : champs libres du PIM appariés au dictionnaire de
     * textes français (avec les variantes de troncature de l'import des fiches).
     */
    private function importFreeTexts(SymfonyStyle $io): void
    {
        // Index des variantes tronquées → texte complet du dictionnaire.
        $truncated = [];
        foreach (array_keys($this->textDictionary) as $nom) {
            $nom = (string) $nom; // les clés numériques deviennent des int en PHP
            if (mb_strlen($nom) > 34) {
                $truncated[mb_substr($nom, 0, 34).'…'] = $nom; // atouts lieu (35)
            }
            if (mb_strlen($nom) > 254) {
                $truncated[mb_substr($nom, 0, 254).'…'] = $nom; // atouts restaurant (255)
            }
            if (mb_strlen($nom) > 255) {
                $truncated[mb_substr($nom, 0, 255)] = $nom; // plus activité (255 sans ellipse)
            }
        }

        foreach ($this->fichesByCode as [$ulid, $type, $hex]) {
            $sources = $this->pimSources[$hex] ?? null;
            if (null === $sources) {
                continue;
            }
            $fields = [];
            if ('lieu' === $type) {
                foreach ($sources['atouts'] ?? [] as $index => $text) {
                    $fields[] = ['lieu.atout'.($index + 1), 'Atout '.($index + 1), $text];
                }
                foreach ($this->decodeList($sources['liste'] ?? null) as $index => $text) {
                    $fields[] = [sprintf('lieu.loisirInterne[%d]', $index), 'Loisir interne '.($index + 1), $text];
                }
            } elseif ('activite' === $type) {
                foreach ($this->decodeList($sources['liste'] ?? null) as $index => $text) {
                    $fields[] = [sprintf('activite.plus[%d]', $index), 'Atout '.($index + 1), $text];
                }
            } elseif ('restaurant' === $type) {
                foreach ($this->decodeList($sources['liste'] ?? null) as $index => $text) {
                    $fields[] = [sprintf('restaurant.atouts[%d]', $index), 'Atout '.($index + 1), $text];
                }
            }
            foreach ($fields as [$fieldPath, $label, $text]) {
                $text = trim((string) $text);
                if ('' === $text) {
                    continue;
                }
                $translations = $this->textDictionary[$text] ?? $this->textDictionary[$truncated[$text] ?? ''] ?? null;
                if (null === $translations) {
                    ++$this->counters['atouts / loisirs sans traduction'];
                    continue;
                }
                foreach ($translations as $localeValue => $content) {
                    if (null !== $this->onlyLocale && $localeValue !== $this->onlyLocale) {
                        continue;
                    }
                    $key = $hex.'|'.$fieldPath.'|'.$localeValue;
                    if (isset($this->existingFiche[$key])) {
                        ++$this->counters['déjà présentes'];
                        continue;
                    }
                    $this->existingFiche[$key] = true;
                    ++$this->counters['atouts / loisirs traduits'];
                    if ($this->dryRun) {
                        continue;
                    }
                    $fiche = $this->entityManager->getReference(Fiche::class, $ulid);
                    $translation = new FicheTranslation($fiche, $fieldPath, $label, SupportedLocale::from($localeValue), $text);
                    $translation->applyManual($content, self::ACTOR);
                    $this->entityManager->persist($translation);
                    $this->flushPending();
                }
            }
        }
        $io->text(sprintf('%d traductions d’atouts / loisirs.', $this->counters['atouts / loisirs traduits']));
    }

    /** Libellés des LOV PIM appariés par libellé français aux référentiels legacy. */
    private function importLovLabels(SymfonyStyle $io): void
    {
        if ([] === $this->lovDictionary) {
            return;
        }
        $connection = $this->entityManager->getConnection();
        $existing = [];
        foreach ($connection->fetchAllAssociative('SELECT value_id, locale FROM pim_attribute_value_translation') as $row) {
            $existing[$row['value_id'].'|'.$row['locale']] = true;
        }
        foreach ($connection->fetchAllAssociative('SELECT id, label FROM pim_attribute_value') as $row) {
            $label = trim((string) $row['label']);
            $translations = $this->lovDictionary[$label] ?? null;
            if (null === $translations) {
                ++$this->counters['libellés LOV sans traduction'];
                continue;
            }
            foreach ($translations as $localeValue => $content) {
                if (null !== $this->onlyLocale && $localeValue !== $this->onlyLocale) {
                    continue;
                }
                if (isset($existing[$row['id'].'|'.$localeValue])) {
                    ++$this->counters['déjà présentes'];
                    continue;
                }
                $existing[$row['id'].'|'.$localeValue] = true;
                ++$this->counters['libellés LOV traduits'];
                if ($this->dryRun) {
                    continue;
                }
                $value = $this->entityManager->getReference(ValeurAttribut::class, (int) $row['id']);
                $translation = new AttributeValueTranslation($value, SupportedLocale::from($localeValue), $label);
                $translation->applyManual($content, self::ACTOR);
                $this->entityManager->persist($translation);
                $this->flushPending();
            }
        }
        $io->text(sprintf('%d traductions de libellés LOV.', $this->counters['libellés LOV traduits']));
    }

    /** @param array<string, ?string> $row */
    private function locale(array $row): ?SupportedLocale
    {
        $locale = SupportedLocale::tryFrom(strtolower((string) $row['locale']));
        if (null === $locale || SupportedLocale::Fr === $locale) {
            return null;
        }
        if (null !== $this->onlyLocale && $locale->value !== $this->onlyLocale) {
            return null;
        }

        return $locale;
    }

    /** @return list<string> */
    private function decodeList(?string $json): array
    {
        if (null === $json || '' === $json) {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_filter($decoded, is_string(...))) : [];
    }

    private function flushPending(bool $force = false): void
    {
        if ($this->dryRun) {
            return;
        }
        if ($force ? $this->pendingInBatch > 0 : ++$this->pendingInBatch >= $this->batchSize) {
            $this->entityManager->flush();
            $this->entityManager->clear();
            $this->pendingInBatch = 0;
        }
    }
}
