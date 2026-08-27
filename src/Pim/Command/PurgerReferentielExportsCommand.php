<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\MessageHandler\GenererReferentielExportHandler;
use App\Pim\Repository\ReferentielExportRepository;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Rétention des exports Excel du référentiel : 30 jours après génération, le
 * classeur quitte le bucket privé et l'export passe « expiré » (la page de
 * suivi et l'historique /outils le disent). Planifiée chaque nuit (Schedule).
 */
#[AsCommand(
    name: 'app:referentiel:purger-exports',
    description: 'Supprime du bucket privé les classeurs d\'export expirés (rétention 30 jours).',
)]
final class PurgerReferentielExportsCommand extends Command
{
    public function __construct(
        private readonly ReferentielExportRepository $exports,
        private readonly PrivateObjectStorageInterface $storage,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(S3_PREFIX)%')] private readonly string $storagePrefix,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $purges = 0;
        foreach ($this->exports->aPurger() as $export) {
            try {
                $this->storage->delete(GenererReferentielExportHandler::cle($this->storagePrefix, $export->idString()));
            } catch (\Throwable $exception) {
                // Objet déjà absent ou bucket indisponible : l'export reste
                // « terminé » (mais plus téléchargeable, expiresAt passé) et
                // sera retenté à la prochaine purge.
                $output->writeln(sprintf('<comment>Export %s : suppression impossible (%s).</comment>', $export->idString(), $exception->getMessage()));
                continue;
            }
            $export->expirer();
            ++$purges;
        }
        $this->entityManager->flush();
        $output->writeln(sprintf('%d export(s) purgé(s).', $purges));

        return Command::SUCCESS;
    }
}
