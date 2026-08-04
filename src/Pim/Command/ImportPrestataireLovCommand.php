<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Repository\ValeurAttributRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:lov:import-prestataires', description: 'Importe une LOV Prestataire CSV code;label sans supprimer les valeurs absentes.')]
final class ImportPrestataireLovCommand extends Command
{
    public function __construct(private readonly ValeurAttributRepository $values)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'CSV UTF-8 avec en-tête code;label')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Valide sans écrire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getArgument('file');
        $handle = @fopen($file, 'rb');
        if (false === $handle) {
            $output->writeln('<error>Fichier illisible.</error>');

            return Command::FAILURE;
        }
        $header = fgetcsv($handle, 0, ';');
        if (false === $header || ['code', 'label'] !== array_slice(array_map(static fn ($value): string => mb_strtolower(trim((string) $value)), $header), 0, 2)) {
            $output->writeln('<error>En-tête attendu : code;label</error>');
            fclose($handle);

            return Command::FAILURE;
        }
        $rows = [];
        $line = 1;
        while (false !== ($data = fgetcsv($handle, 0, ';'))) {
            ++$line;
            $code = strtoupper(trim((string) ($data[0] ?? '')));
            $label = trim((string) ($data[1] ?? ''));
            if ('' === $code || '' === $label || !preg_match('/^[A-Z0-9][A-Z0-9_-]{1,95}$/', $code) || isset($rows[$code])) {
                $output->writeln(sprintf('<error>Ligne %d invalide ou dupliquée.</error>', $line));
                fclose($handle);

                return Command::FAILURE;
            }
            $rows[$code] = $label;
        }
        fclose($handle);

        $attributeId = (int) sprintf('%u', crc32('attribute:PRESTATAIRE'));
        $position = 0;
        foreach ($rows as $code => $label) {
            $id = (int) sprintf('%u', crc32('value:PRESTATAIRE:'.$code));
            $collision = $this->values->findIdentityAt($id);
            if (null !== $collision && ('PRESTATAIRE' !== $collision['attribute_code'] || $code !== $collision['code'])) {
                $output->writeln('<error>Collision d’identifiant pour '.$code.'.</error>');

                return Command::FAILURE;
            }
            if (!$input->getOption('dry-run')) {
                $this->values->upsertPrestataire($id, $attributeId, $code, $label, $position);
            }
            ++$position;
        }
        $output->writeln(sprintf('<info>%d prestataire(s) %s.</info>', count($rows), $input->getOption('dry-run') ? 'validés' : 'importés'));

        return Command::SUCCESS;
    }
}
