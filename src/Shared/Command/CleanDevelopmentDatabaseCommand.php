<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Shared\Service\PrivateObjectStorageInterface;
use App\Shared\Service\PublicObjectStorageInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'dev')]
#[
    AsCommand(
        name: 'app:dev:database:clean',
        description: 'Vide les données et les médias de développement.',
    ),
]
final class CleanDevelopmentDatabaseCommand extends Command
{
    /** @var list<string> */
    private const PRESERVED_TABLES = [
        'doctrine_migration_versions',
        'pim_attribute_definition',
        'pim_attribute_value',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly PrivateObjectStorageInterface $privateStorage,
        #[Autowire(service: 'app.storage.public')]
        private readonly PublicObjectStorageInterface $publicStorage,
        #[Autowire(env: 'S3_PREFIX')]
        private readonly string $storagePrefix,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Confirme la suppression sans question interactive.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $prefix = trim($this->storagePrefix, '/');

        if ('' === $prefix || '.' === $prefix) {
            $io->error(
                'S3_PREFIX doit identifier un répertoire de développement non vide.',
            );

            return Command::FAILURE;
        }

        $io->warning([
            'Cette commande supprime toutes les données applicatives de la base courante.',
            sprintf(
                'Elle supprime aussi les objets privés et publics sous le préfixe S3 « %s/ ».',
                $prefix,
            ),
            'Les versions de migration et les listes de valeurs sont conservées.',
        ]);

        if (!$input->getOption('force')) {
            if (!$input->isInteractive()) {
                $io->error('Utilisez --force en mode non interactif.');

                return Command::FAILURE;
            }

            if (!$io->confirm('Confirmer le nettoyage irréversible ?', false)) {
                $io->note('Nettoyage annulé.');

                return Command::SUCCESS;
            }
        }

        $tables = $this->tablesToClean();

        foreach ($this->legacyPublicDocumentKeys($tables) as $key) {
            $this->publicStorage->delete($key);
        }

        // Le stockage est vidé avant la base : si S3 est indisponible, les
        // références SQL restent présentes et la commande peut être relancée.
        $this->privateStorage->deleteDirectory($prefix);
        $this->publicStorage->deleteDirectory($prefix);
        $this->truncate($tables);

        $io->success(sprintf(
            '%d table(s) nettoyée(s) et médias « %s/ » supprimés des stockages privé et public.',
            count($tables),
            $prefix,
        ));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $tables
     *
     * @return list<string>
     */
    private function legacyPublicDocumentKeys(array $tables): array
    {
        if (!in_array('pim_ressource_lieu', $tables, true)) {
            return [];
        }

        $keys = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT public_storage_key
                FROM pim_ressource_lieu
                WHERE public_storage_key IS NOT NULL
                  AND public_storage_key <> ''
                SQL,
        );

        return array_values(array_unique(array_map(
            static fn (mixed $key): string => (string) $key,
            $keys,
        )));
    }

    /** @return list<string> */
    private function tablesToClean(): array
    {
        $tables = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT TABLE_NAME
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY TABLE_NAME
                SQL,
        );

        return array_values(array_filter(
            array_map(static fn (mixed $table): string => (string) $table, $tables),
            static fn (string $table): bool => !in_array(
                $table,
                self::PRESERVED_TABLES,
                true,
            ),
        ));
    }

    /** @param list<string> $tables */
    private function truncate(array $tables): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                $quotedTable = $this->connection
                    ->getDatabasePlatform()
                    ->quoteIdentifier($table);
                $this->connection->executeStatement('TRUNCATE TABLE '.$quotedTable);
            }
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
