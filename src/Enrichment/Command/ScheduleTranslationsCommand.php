<?php

declare(strict_types=1);

namespace App\Enrichment\Command;

use App\Enrichment\Service\FicheTranslationScheduler;
use App\Enrichment\Service\LovTranslationScheduler;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Enum\StatutFiche;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Ulid;

#[AsCommand(name: 'app:translations:schedule', description: 'Planifie les traductions manquantes des fiches publiées et des LOV.')]
final class ScheduleTranslationsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly FicheTranslationScheduler $fiches, private readonly LovTranslationScheduler $lovs) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('scope', null, InputOption::VALUE_REQUIRED, 'all, fiches ou lov', 'all')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de sujets', '100')
            ->addOption('after', null, InputOption::VALUE_REQUIRED, 'ULID de reprise des fiches')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte sans enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scope = (string) $input->getOption('scope');
        if (!in_array($scope, ['all', 'fiches', 'lov'], true)) { $output->writeln('<error>Scope attendu : all, fiches ou lov.</error>'); return Command::INVALID; }
        $limit = max(1, min(1000, (int) $input->getOption('limit')));
        $count = 0; $last = null;
        if (in_array($scope, ['all', 'fiches'], true)) {
            $qb = $this->entityManager->getRepository(Fiche::class)->createQueryBuilder('f')->andWhere('f.status = :status')->setParameter('status', StatutFiche::Publiee)->orderBy('f.id', 'ASC')->setMaxResults($limit);
            $after = trim((string) $input->getOption('after'));
            if ('' !== $after) { $qb->andWhere('f.id > :after')->setParameter('after', Ulid::fromString($after), 'ulid'); }
            foreach ($qb->getQuery()->getResult() as $fiche) { if ($fiche instanceof Fiche) { $this->fiches->schedule($fiche); ++$count; $last = $fiche->idString(); } }
        }
        if (in_array($scope, ['all', 'lov'], true)) {
            foreach ($this->entityManager->getRepository(AttributDefinition::class)->findBy([], ['code' => 'ASC'], $limit) as $attribute) { $this->lovs->scheduleDefinition($attribute); ++$count; }
            foreach ($this->entityManager->getRepository(ValeurAttribut::class)->findBy([], ['id' => 'ASC'], $limit) as $value) { $this->lovs->scheduleValue($value); ++$count; }
        }
        if (!$input->getOption('dry-run')) { $this->entityManager->flush(); }
        $output->writeln(sprintf('<info>%d sujet(s) %s.</info>', $count, $input->getOption('dry-run') ? 'analysés' : 'planifiés'));
        if (null !== $last) { $output->writeln('Prochain curseur : '.$last); }

        return Command::SUCCESS;
    }
}
