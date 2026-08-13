<?php

declare(strict_types=1);

namespace App\Etl\Command;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Account\Repository\UserRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheCollaborateurRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Import manuel des collaborateurs legacy depuis le XLSX d'export production
 * (« listes_fiches_produits_* »). Une ligne = une fiche (ID Syspad) ; les
 * collaborateurs sont empilés DANS les cellules (une entrée par retour-ligne)
 * sur trois colonnes parallèles : « Identifiant email », « Nom », « Prénom ».
 *
 * ATTENTION à l'alignement : les trois listes sont appariées PAR INDEX et
 * contiennent des entrées vides quand une information manque (ex. un email
 * sans nom ni prénom laisse une ligne vide dans les colonnes Nom et Prénom).
 * Les listes ne doivent donc JAMAIS être filtrées de leurs vides avant
 * l'appariement, sous peine de mélanger les identités.
 *
 * La colonne « Adhérent Business Premium » alimente Fiche.businessPremium
 * (sans transition de workflow). La colonne « Attribution visibilité » (un
 * site par retour-ligne) remplace la sélection de sites de diffusion de la
 * fiche — les libellés sont résolus sur le référentiel seedé par
 * app:sites-diffusion:sync (« Business Profilers » = marketplace_bp), une
 * cellule vide ne touche à rien. Idempotent : collaborateur unique par
 * email, affiliation unique par (collaborateur, fiche).
 */
#[AsCommand(name: 'app:legacy:import-collaborateurs', description: 'Importe les collaborateurs legacy et le statut Business Premium depuis le XLSX production.')]
final class ImportLegacyCollaborateursCommand extends Command
{
    private const DEFAULT_FILE = 'var/tmp/import/listes_fiches_produits_06-08-2026_17H31.xlsx';
    private const HEADER_SYSPAD = 'ID Syspad';
    private const HEADER_PREMIUM = 'Adhérent Business Premium';
    private const HEADER_VISIBILITE = 'Attribution visibilité';
    private const HEADER_EMAILS = 'Identifiant email';
    private const HEADER_NOMS = 'Nom';
    private const HEADER_PRENOMS = 'Prénom';
    private const PREMIUM_VALUE = 'Adhérent';

    /** @var array<string, int>|null Libellé de site → id (référentiel chargé une fois). */
    private ?array $siteIdsParLabel = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FicheRepository $fiches,
        private readonly FicheCollaborateurRepository $collaborateurs,
        private readonly FicheAffiliationRepository $affiliations,
        private readonly UserRepository $users,
        private readonly SiteDiffusionRepository $sites,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('file', null, InputOption::VALUE_REQUIRED, 'Chemin du XLSX production.', $this->projectDir.'/'.self::DEFAULT_FILE)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyse sans rien écrire en base.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximum de lignes à traiter.')
            ->addOption('only-syspad', null, InputOption::VALUE_REQUIRED, 'Ne traite que cet Id syspad (debug).')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots de flush.', '50')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Rôle des affiliations créées (manager, administrateur, utilisateur).', FicheAffiliationRole::Utilisateur->value)
            ->addOption('created-by', null, InputOption::VALUE_REQUIRED, 'Email du compte interne auteur des affiliations (défaut : premier super admin).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getOption('file');
        if (!is_readable($file)) {
            $io->error(sprintf('Fichier introuvable : %s', $file));

            return Command::FAILURE;
        }
        $role = FicheAffiliationRole::tryFrom((string) $input->getOption('role'));
        if (null === $role) {
            $io->error('Rôle attendu : manager, administrateur ou utilisateur.');

            return Command::INVALID;
        }
        $dryRun = (bool) $input->getOption('dry-run');
        $limit = null === $input->getOption('limit') ? null : max(1, (int) $input->getOption('limit'));
        $onlySyspad = null === $input->getOption('only-syspad') ? null : (int) $input->getOption('only-syspad');
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $createdBy = $this->resolveCreatedBy($input->getOption('created-by'));
        if (null === $createdBy) {
            $io->error('Aucun compte auteur : préciser --created-by=<email> (compte interne existant).');

            return Command::FAILURE;
        }

        $stats = [
            'lignes' => 0, 'fiches inconnues' => 0, 'premium activés' => 0, 'premium désactivés' => 0,
            'visibilités appliquées' => 0, 'visibilités inchangées' => 0, 'visibilités absentes' => 0,
            'collaborateurs créés' => 0, 'collaborateurs réutilisés' => 0, 'affiliations créées' => 0,
            'affiliations existantes' => 0, 'entrées sans email' => 0, 'emails invalides' => 0,
        ];
        $columns = null;
        $sinceFlush = 0;
        $createdThisBatch = [];
        // Valeurs « Adhérent Business Premium » non vides et non reconnues :
        // elles désactivent le premium, un warning final permet de repérer
        // une variante de libellé (casse, libellé long) avant de conclure.
        $unknownPremiumValues = [];
        // Libellés de sites absents du référentiel (occurrences) : listés en
        // warning final — relancer app:sites-diffusion:sync ou créer le site
        // dans l'admin puis réimporter.
        $unknownSites = [];
        // Paires (email, fiche) déjà traitées pendant ce run : le fichier
        // contient des emails dupliqués au sein d'une même cellule.
        $seenPairs = [];
        $createdByEmail = $createdBy->email();

        $reader = new Reader();
        $reader->open($file);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_values(array_map(
                    static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                    $row->toArray(),
                ));
                if (null === $columns) {
                    $columns = $this->mapColumns($cells);
                    if (null === $columns) {
                        $io->error('Entêtes attendues introuvables (ID Syspad, Identifiant email, Nom, Prénom, Adhérent Business Premium, Attribution visibilité).');
                        $reader->close();

                        return Command::FAILURE;
                    }
                    continue;
                }
                $syspad = (int) trim($cells[$columns[self::HEADER_SYSPAD]] ?? '');
                if ($syspad < 1 || (null !== $onlySyspad && $syspad !== $onlySyspad)) {
                    continue;
                }
                if (null !== $limit && $stats['lignes'] >= $limit) {
                    break 2;
                }
                ++$stats['lignes'];
                $fiche = $this->fiches->findOneBy(['code' => $syspad]);
                if (!$fiche instanceof Fiche) {
                    ++$stats['fiches inconnues'];
                    continue;
                }
                $this->applyBusinessPremium($fiche, $cells[$columns[self::HEADER_PREMIUM]] ?? '', $dryRun, $stats, $unknownPremiumValues);
                $this->applyVisibilite($fiche, $cells[$columns[self::HEADER_VISIBILITE]] ?? '', $dryRun, $stats, $unknownSites);
                foreach ($this->entries($cells, $columns) as $entry) {
                    $this->importEntry($fiche, $entry, $role, $createdBy, $dryRun, $stats, $createdThisBatch, $seenPairs);
                }
                if (!$dryRun && ++$sinceFlush >= $batchSize) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                    $createdThisBatch = [];
                    $sinceFlush = 0;
                    $createdBy = $this->users->findOneByEmail($createdByEmail)
                        ?? throw new \RuntimeException('Compte auteur perdu en cours d\'import.');
                }
            }
            break;
        }
        $reader->close();
        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->table(array_keys($stats), [array_values($stats)]);
        if ([] !== $unknownSites) {
            arsort($unknownSites);
            $io->warning(sprintf(
                'Colonne « %s » : sites absents du référentiel, ignorés : %s. Relancer app:sites-diffusion:sync (ou créer le site dans l\'admin) puis réimporter.',
                self::HEADER_VISIBILITE,
                implode(', ', array_map(
                    static fn (string $value, int $count): string => sprintf('« %s » (×%d)', $value, $count),
                    array_keys($unknownSites),
                    array_values($unknownSites),
                )),
            ));
        }
        if ([] !== $unknownPremiumValues) {
            arsort($unknownPremiumValues);
            $io->warning(sprintf(
                'Colonne « %s » : valeurs non reconnues, traitées comme non premium (seul « %s » active le flag) : %s. %d désactivation(s) de premium %s au total.',
                self::HEADER_PREMIUM,
                self::PREMIUM_VALUE,
                implode(', ', array_map(
                    static fn (string $value, int $count): string => sprintf('« %s » (×%d)', $value, $count),
                    array_keys($unknownPremiumValues),
                    array_values($unknownPremiumValues),
                )),
                $stats['premium désactivés'],
                $dryRun ? 'seraient opérées' : 'opérées',
            ));
        }
        $io->success($dryRun ? 'Analyse terminée (aucune écriture).' : 'Import terminé.');

        return Command::SUCCESS;
    }

    /**
     * Chaque cellule contient une entrée par ligne, les trois listes sont
     * alignées par index : les entrées vides sont CONSERVÉES pour ne pas
     * décaler les identités.
     *
     * @param list<string>       $cells
     * @param array<string, int> $columns
     *
     * @return list<array{email: string, nom: string, prenom: string}>
     */
    private function entries(array $cells, array $columns): array
    {
        $split = static fn (string $value): array => preg_split('/\r\n|\r|\n/', $value) ?: [];
        $emails = $split($cells[$columns[self::HEADER_EMAILS]] ?? '');
        $noms = $split($cells[$columns[self::HEADER_NOMS]] ?? '');
        $prenoms = $split($cells[$columns[self::HEADER_PRENOMS]] ?? '');
        $entries = [];
        $count = max(count($emails), count($noms), count($prenoms));
        for ($i = 0; $i < $count; ++$i) {
            $entries[] = [
                'email' => trim($emails[$i] ?? ''),
                'nom' => trim($noms[$i] ?? ''),
                'prenom' => trim($prenoms[$i] ?? ''),
            ];
        }

        return $entries;
    }

    /**
     * @param array{email: string, nom: string, prenom: string} $entry
     * @param array<string, int>                                $stats
     * @param array<string, FicheCollaborateur>                 $createdThisBatch
     * @param array<string, true>                               $seenPairs
     */
    private function importEntry(
        Fiche $fiche,
        array $entry,
        FicheAffiliationRole $role,
        User $createdBy,
        bool $dryRun,
        array &$stats,
        array &$createdThisBatch,
        array &$seenPairs,
    ): void {
        if ('' === $entry['email']) {
            // Nom/prénom sans adresse : inexploitable, l'email est la clé du
            // collaborateur (connexion espace client, invitation marketplace).
            if ('' !== $entry['nom'] || '' !== $entry['prenom']) {
                ++$stats['entrées sans email'];
            }

            return;
        }
        if (false === filter_var($entry['email'], FILTER_VALIDATE_EMAIL)) {
            ++$stats['emails invalides'];

            return;
        }
        $email = FicheCollaborateur::normalizeEmail($entry['email']);
        $pair = $email.'|'.$fiche->idString();
        if (isset($seenPairs[$pair])) {
            ++$stats['affiliations existantes'];

            return;
        }
        $seenPairs[$pair] = true;
        $collaborateur = $createdThisBatch[$email] ?? $this->collaborateurs->findOneByEmail($email);
        $isNew = null === $collaborateur;
        if ($isNew) {
            ++$stats['collaborateurs créés'];
            if ($dryRun) {
                $createdThisBatch[$email] = new FicheCollaborateur($email, $entry['prenom'], $entry['nom']);
                ++$stats['affiliations créées'];

                return;
            }
            $collaborateur = new FicheCollaborateur($email, $entry['prenom'], $entry['nom']);
            $this->entityManager->persist($collaborateur);
            $createdThisBatch[$email] = $collaborateur;
        } else {
            ++$stats['collaborateurs réutilisés'];
        }
        if (!$isNew && null !== $this->affiliations->findFor($collaborateur, $fiche)) {
            ++$stats['affiliations existantes'];

            return;
        }
        ++$stats['affiliations créées'];
        if (!$dryRun) {
            $this->entityManager->persist(new FicheAffiliation($collaborateur, $fiche, $role, $createdBy));
        }
    }

    /**
     * @param array<string, int> $stats
     * @param array<string, int> $unknownValues occurrences par valeur non reconnue
     */
    private function applyBusinessPremium(Fiche $fiche, string $value, bool $dryRun, array &$stats, array &$unknownValues): void
    {
        $value = trim($value);
        if ('' !== $value && self::PREMIUM_VALUE !== $value) {
            $unknownValues[$value] = ($unknownValues[$value] ?? 0) + 1;
        }
        $premium = self::PREMIUM_VALUE === $value;
        if ($premium === $fiche->businessPremium()) {
            return;
        }
        ++$stats[$premium ? 'premium activés' : 'premium désactivés'];
        if (!$dryRun) {
            // Sans transition de workflow : l'import ne doit pas dépublier.
            $fiche->preserveWorkflowDuring(static function () use ($fiche, $premium): void {
                $fiche->changeBusinessPremium($premium);
            });
        }
    }

    /**
     * Remplace la sélection de sites de diffusion par les libellés de la
     * colonne « Attribution visibilité » (un site par retour-ligne), sans
     * transition de workflow. Cellule vide → sélection intacte.
     *
     * @param array<string, int> $stats
     * @param array<string, int> $unknownSites occurrences par libellé inconnu
     */
    private function applyVisibilite(Fiche $fiche, string $value, bool $dryRun, array &$stats, array &$unknownSites): void
    {
        $labels = array_values(array_unique(array_filter(array_map(
            static fn (string $label): string => trim($label),
            preg_split('/\r\n|\r|\n/', $value) ?: [],
        ), static fn (string $label): bool => '' !== $label)));
        if ([] === $labels) {
            ++$stats['visibilités absentes'];

            return;
        }
        $ids = [];
        foreach ($labels as $label) {
            $id = $this->siteIdsParLabel()[$label] ?? null;
            if (null === $id) {
                $unknownSites[$label] = ($unknownSites[$label] ?? 0) + 1;
                continue;
            }
            $ids[$id] = true;
        }
        $actuels = $fiche->siteDiffusionIds();
        if (count($actuels) === count($ids) && [] === array_diff_key($ids, array_fill_keys($actuels, true))) {
            ++$stats['visibilités inchangées'];

            return;
        }
        ++$stats['visibilités appliquées'];
        if ($dryRun) {
            return;
        }
        // Rechargés à chaque fiche : les em->clear() des lots détachent les
        // entités, seuls les ids sont conservés entre les lots.
        $sites = array_values(array_filter(array_map(
            fn (int $id): ?SiteDiffusion => $this->sites->find($id),
            array_keys($ids),
        )));
        $fiche->preserveWorkflowDuring(static function () use ($fiche, $sites): void {
            $fiche->replaceSiteDiffusion($sites);
        });
    }

    /** @return array<string, int> */
    private function siteIdsParLabel(): array
    {
        if (null === $this->siteIdsParLabel) {
            $this->siteIdsParLabel = [];
            foreach ($this->sites->findAll() as $site) {
                if (null !== $site->id()) {
                    $this->siteIdsParLabel[$site->label()] = $site->id();
                }
            }
        }

        return $this->siteIdsParLabel;
    }

    /**
     * @param list<string> $headers
     *
     * @return array<string, int>|null
     */
    private function mapColumns(array $headers): ?array
    {
        $wanted = [self::HEADER_SYSPAD, self::HEADER_PREMIUM, self::HEADER_VISIBILITE, self::HEADER_EMAILS, self::HEADER_NOMS, self::HEADER_PRENOMS];
        $columns = [];
        foreach ($headers as $index => $header) {
            $header = trim($header);
            if (in_array($header, $wanted, true) && !isset($columns[$header])) {
                $columns[$header] = $index;
            }
        }

        return count($columns) === count($wanted) ? $columns : null;
    }

    private function resolveCreatedBy(?string $email): ?User
    {
        if (null !== $email && '' !== trim($email)) {
            return $this->users->findOneByEmail(trim($email));
        }
        foreach ($this->users->findActifs() as $user) {
            if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
                return $user;
            }
        }

        return null;
    }
}
