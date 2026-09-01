<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Repository\AeroportReferenceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Recharge le référentiel statique des aéroports (suggestions du bloc
 * Accès) depuis OurAirports (domaine public, mondial). Seuls les aéroports
 * large/medium à trafic commercial régulier sont retenus : les aérodromes
 * privés et héliports ne sont pas des accès. Remplacement en bloc dans une
 * transaction — la table reste servie en cas d'échec. À relancer de loin en
 * loin (le parc mondial d'aéroports bouge peu).
 */
#[AsCommand(name: 'app:acces:importer-aeroports', description: 'Recharge le référentiel des aéroports (OurAirports) des suggestions du bloc Accès.')]
final class ImporterAeroportsCommand extends Command
{
    private const URL = 'https://davidmegginson.github.io/ourairports-data/airports.csv';
    private const TYPES_RETENUS = ['large_airport', 'medium_airport'];

    public function __construct(
        private readonly AeroportReferenceRepository $aeroports,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::OPTIONAL, 'CSV OurAirports local (airports.csv) ; absent = téléchargement.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fichier = $input->getArgument('fichier');
        if (is_string($fichier) && '' !== $fichier) {
            $csv = @file_get_contents($fichier);
            if (false === $csv) {
                $output->writeln('<error>Fichier illisible.</error>');

                return Command::FAILURE;
            }
        } else {
            $output->writeln('Téléchargement de '.self::URL.' …');
            $csv = $this->httpClient->request('GET', self::URL)->getContent();
        }

        $flux = fopen('php://memory', 'r+b');
        if (false === $flux) {
            return Command::FAILURE;
        }
        fwrite($flux, $csv);
        rewind($flux);
        $entete = fgetcsv($flux, null, ',', '"', '');
        if (false === $entete || !in_array('iata_code', $entete, true)) {
            $output->writeln('<error>En-tête OurAirports introuvable (colonne iata_code attendue).</error>');

            return Command::FAILURE;
        }
        $colonne = array_flip(array_map(static fn (?string $nom): string => (string) $nom, $entete));
        $lignes = [];
        while (false !== ($donnees = fgetcsv($flux, null, ',', '"', ''))) {
            $champ = static fn (string $nom): string => trim((string) ($donnees[$colonne[$nom] ?? -1] ?? ''));
            if (!in_array($champ('type'), self::TYPES_RETENUS, true) || 'yes' !== $champ('scheduled_service')
                || !is_numeric($champ('latitude_deg')) || !is_numeric($champ('longitude_deg')) || '' === $champ('name')) {
                continue;
            }
            $iata = $champ('iata_code');
            $lignes[] = [
                'nom' => mb_substr($champ('name'), 0, 255),
                'code_iata' => '' === $iata ? null : mb_substr($iata, 0, 3),
                'code_pays' => mb_substr($champ('iso_country'), 0, 2),
                'latitude' => (float) $champ('latitude_deg'),
                'longitude' => (float) $champ('longitude_deg'),
            ];
        }
        fclose($flux);
        if ([] === $lignes) {
            $output->writeln('<error>Aucun aéroport retenu — source inattendue, table conservée.</error>');

            return Command::FAILURE;
        }

        $this->aeroports->remplacer($lignes);
        $output->writeln(sprintf('<info>%d aéroports importés (large/medium à trafic régulier).</info>', count($lignes)));

        return Command::SUCCESS;
    }
}
