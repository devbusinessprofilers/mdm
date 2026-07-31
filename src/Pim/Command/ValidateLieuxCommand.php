<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Validation\ValidationGroups;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:lieux:validate', description: 'Contrôle les fiches Lieu existantes sans modifier les données.')]
final class ValidateLieuxCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('submission', null, InputOption::VALUE_NONE, 'Ajoute les contraintes nécessaires à la soumission.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $groups = [ValidationGroups::DRAFT];
        if ($input->getOption('submission')) {
            $groups[] = ValidationGroups::SUBMISSION;
        }

        $invalid = $checked = 0;
        $query = $this->entityManager->createQuery('SELECT l FROM '.Lieu::class.' l ORDER BY l.id');
        foreach ($query->toIterable() as $lieu) {
            if (!$lieu instanceof Lieu) {
                continue;
            }
            ++$checked;
            $violations = $this->validator->validate($lieu, null, $groups);
            if (0 === count($violations)) {
                continue;
            }
            ++$invalid;
            foreach ($violations as $violation) {
                $io->writeln(json_encode([
                    'id' => $lieu->id(),
                    'code' => $lieu->code(),
                    'field' => (string) $violation->getPropertyPath(),
                    'error' => (string) $violation->getMessage(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }
            $this->entityManager->detach($lieu);
        }

        $io->comment(sprintf('%d lieu(x) contrôlé(s), %d invalide(s). Aucune donnée modifiée.', $checked, $invalid));

        return 0 === $invalid ? Command::SUCCESS : Command::FAILURE;
    }
}
