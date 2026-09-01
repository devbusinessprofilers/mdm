<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Repository\ClassementAtoutFranceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Recharge le référentiel du classement officiel des hébergements touristiques
 * depuis l'open data Atout France (data.gouv.fr, Licence Ouverte — hôtels de
 * tourisme, campings, villages de vacances, résidences de tourisme…). Seules
 * les lignes exploitables sont retenues : nom, code postal et classement en
 * étoiles lisibles. Remplacement en bloc dans une transaction — la table reste
 * servie en cas d'échec. À relancer de loin en loin (fichier republié
 * régulièrement par Atout France).
 */
#[AsCommand(name: 'app:pim:importer-classements-atout-france', description: 'Recharge le référentiel des classements Atout France (étoiles) des suggestions d\'enrichissement.')]
final class ImporterClassementsAtoutFranceCommand extends Command
{
    /** Ressource stable data.gouv.fr « Listes des hébergements collectifs classés en France ». */
    private const URL = 'https://www.data.gouv.fr/api/1/datasets/r/3ce290bf-07ec-4d63-b12b-d0496193a535';

    public function __construct(
        private readonly ClassementAtoutFranceRepository $classements,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::OPTIONAL, 'CSV Atout France local (séparateur « ; ») ; absent = téléchargement.');
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
        // BOM UTF-8 éventuel : il collerait au nom de la première colonne.
        fwrite($flux, (string) preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($flux);
        $entete = fgetcsv($flux, null, ';', '"', '');
        if (false === $entete || !in_array('NOM COMMERCIAL', $entete, true)) {
            $output->writeln('<error>En-tête Atout France introuvable (colonne NOM COMMERCIAL attendue).</error>');

            return Command::FAILURE;
        }
        $colonne = array_flip(array_map(static fn (?string $nom): string => (string) $nom, $entete));
        $lignes = [];
        while (false !== ($donnees = fgetcsv($flux, null, ';', '"', ''))) {
            $champ = static fn (string $nom): string => trim((string) ($donnees[$colonne[$nom] ?? -1] ?? ''));
            $etoiles = 1 === preg_match('/^([1-5])\s*étoile/u', $champ('CLASSEMENT'), $trouve) ? (int) $trouve[1] : null;
            $codePostal = $champ('CODE POSTAL');
            if (null === $etoiles || '' === $champ('NOM COMMERCIAL') || 1 !== preg_match('/^\d{5}$/', $codePostal)) {
                continue;
            }
            $chambres = $champ('NOMBRE DE CHAMBRES');
            $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $champ('DATE DE CLASSEMENT')) ?: null;
            $lignes[] = [
                'nom' => mb_substr($champ('NOM COMMERCIAL'), 0, 255),
                'code_postal' => $codePostal,
                'commune' => mb_substr($champ('COMMUNE'), 0, 255),
                'type_etablissement' => mb_substr($champ('TYPOLOGIE ÉTABLISSEMENT'), 0, 64),
                'etoiles' => $etoiles,
                'nombre_chambres' => 1 === preg_match('/^\d{1,5}$/', $chambres) ? (int) $chambres : null,
                'date_classement' => $date?->format('Y-m-d'),
            ];
        }
        fclose($flux);
        if ([] === $lignes) {
            $output->writeln('<error>Aucun classement retenu — source inattendue, table conservée.</error>');

            return Command::FAILURE;
        }

        $this->classements->remplacer($lignes);
        $output->writeln(sprintf('<info>%d classements importés.</info>', count($lignes)));

        return Command::SUCCESS;
    }
}
