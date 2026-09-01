<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Repository\GrandeVilleReferenceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Recharge le référentiel statique des villes de 15 000 habitants et plus
 * (suggestions « Grande ville » du bloc Accès) depuis GeoNames cities15000
 * (CC-BY, mondial). Remplacement en bloc dans une transaction — la table
 * reste servie en cas d'échec. À relancer de loin en loin.
 */
#[AsCommand(name: 'app:acces:importer-grandes-villes', description: 'Recharge le référentiel des grandes villes (GeoNames) des suggestions du bloc Accès.')]
final class ImporterGrandesVillesCommand extends Command
{
    private const URL = 'https://download.geonames.org/export/dump/cities15000.zip';
    /** Colonnes GeoNames : nom(1), latitude(4), longitude(5), pays(8), population(14). */
    private const COL_NOM = 1;
    private const COL_LATITUDE = 4;
    private const COL_LONGITUDE = 5;
    private const COL_PAYS = 8;
    private const COL_POPULATION = 14;

    public function __construct(
        private readonly GrandeVilleReferenceRepository $grandesVilles,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::OPTIONAL, 'cities15000 local (.zip GeoNames ou .txt extrait) ; absent = téléchargement.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tsv = $this->contenu($input, $output);
        if (null === $tsv) {
            return Command::FAILURE;
        }

        $lignes = [];
        foreach (explode("\n", $tsv) as $brut) {
            $donnees = explode("\t", $brut);
            $nom = trim($donnees[self::COL_NOM] ?? '');
            $population = (int) ($donnees[self::COL_POPULATION] ?? 0);
            if ('' === $nom || $population < 15_000
                || !is_numeric($donnees[self::COL_LATITUDE] ?? null) || !is_numeric($donnees[self::COL_LONGITUDE] ?? null)) {
                continue;
            }
            $lignes[] = [
                'nom' => mb_substr($nom, 0, 255),
                'code_pays' => mb_substr(trim($donnees[self::COL_PAYS] ?? ''), 0, 2),
                'population' => $population,
                'latitude' => (float) $donnees[self::COL_LATITUDE],
                'longitude' => (float) $donnees[self::COL_LONGITUDE],
            ];
        }
        if ([] === $lignes) {
            $output->writeln('<error>Aucune ville retenue — source inattendue, table conservée.</error>');

            return Command::FAILURE;
        }

        $this->grandesVilles->remplacer($lignes);
        $output->writeln(sprintf('<info>%d villes importées (population ≥ 15 000).</info>', count($lignes)));

        return Command::SUCCESS;
    }

    /** Le TSV cities15000.txt, depuis un fichier local (.txt ou .zip) ou le téléchargement GeoNames. */
    private function contenu(InputInterface $input, OutputInterface $output): ?string
    {
        $fichier = $input->getArgument('fichier');
        if (is_string($fichier) && '' !== $fichier && str_ends_with($fichier, '.txt')) {
            $tsv = @file_get_contents($fichier);
            if (false === $tsv) {
                $output->writeln('<error>Fichier illisible.</error>');

                return null;
            }

            return $tsv;
        }
        if (is_string($fichier) && '' !== $fichier) {
            $zipLocal = $fichier;
        } else {
            $output->writeln('Téléchargement de '.self::URL.' …');
            $zipLocal = tempnam(sys_get_temp_dir(), 'cities15000');
            if (false === $zipLocal) {
                return null;
            }
            file_put_contents($zipLocal, $this->httpClient->request('GET', self::URL)->getContent());
        }
        $zip = new \ZipArchive();
        if (true !== $zip->open($zipLocal)) {
            $output->writeln('<error>Archive zip illisible.</error>');

            return null;
        }
        $tsv = $zip->getFromName('cities15000.txt');
        $zip->close();
        if (false === $tsv) {
            $output->writeln('<error>cities15000.txt absent de l\'archive.</error>');

            return null;
        }

        return $tsv;
    }
}
