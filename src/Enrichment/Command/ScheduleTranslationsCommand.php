<?php

declare(strict_types=1);

namespace App\Enrichment\Command;

use App\Enrichment\Service\FicheTranslationScheduler;
use App\Enrichment\Service\LovTranslationScheduler;
use App\Pim\Repository\AttributDefinitionRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\ValeurAttributRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:translations:schedule', description: 'Planifie les traductions manquantes des fiches publiées et des LOV.')]
final class ScheduleTranslationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FicheRepository $ficheRepository,
        private readonly AttributDefinitionRepository $attributes,
        private readonly ValeurAttributRepository $values,
        private readonly FicheTranslationScheduler $fiches,
        private readonly LovTranslationScheduler $lovs,
    ) { parent::__construct(); }

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
            $after = trim((string) $input->getOption('after'));
            foreach ($this->ficheRepository->findPublishedAfter('' === $after ? null : $after, $limit) as $fiche) { $this->fiches->schedule($fiche); ++$count; $last = $fiche->idString(); }
        }
        if (in_array($scope, ['all', 'lov'], true)) {
            foreach ($this->attributes->findOrdered($limit) as $attribute) { $this->lovs->scheduleDefinition($attribute); ++$count; }
            foreach ($this->values->findOrdered($limit) as $value) { $this->lovs->scheduleValue($value); ++$count; }
        }
        if (!$input->getOption('dry-run')) { $this->entityManager->flush(); }
        $output->writeln(sprintf('<info>%d sujet(s) %s.</info>', $count, $input->getOption('dry-run') ? 'analysés' : 'planifiés'));
        if (null !== $last) { $output->writeln('Prochain curseur : '.$last); }

        return Command::SUCCESS;
    }
}
