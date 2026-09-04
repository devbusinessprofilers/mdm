<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Enum\TypeFiche;
use App\Pim\Validation\ValidationGroups;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Contrôle les fiches existantes contre leurs contraintes de validation sans
 * rien modifier : une ligne JSON par violation (id, code, champ, erreur),
 * toutes gammes ou une seule (`--gamme=lieux|restaurants|activites|services`).
 */
#[AsCommand(name: 'app:fiches:validate', description: 'Contrôle les fiches existantes sans modifier les données.')]
final class ValidateFichesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('gamme', null, InputOption::VALUE_REQUIRED, 'Segment de la gamme à contrôler (lieux, restaurants, activites, services) ; toutes par défaut.')
            ->addOption('submission', null, InputOption::VALUE_NONE, 'Ajoute les contraintes nécessaires à la soumission.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $groups = [ValidationGroups::DRAFT];
        if ($input->getOption('submission')) {
            $groups[] = ValidationGroups::SUBMISSION;
        }
        $gamme = $input->getOption('gamme');
        if (is_string($gamme)) {
            $type = TypeFiche::depuisSlug($gamme);
            if (null === $type || !$type->estOperationnel()) {
                $io->error(sprintf('Gamme inconnue : %s (attendu : %s).', $gamme, implode(', ', array_map(static fn (TypeFiche $t): string => $t->slug(), TypeFiche::operationnels()))));

                return Command::INVALID;
            }
            $types = [$type];
        } else {
            $types = TypeFiche::operationnels();
        }

        $invalid = 0;
        foreach ($types as $type) {
            $invalid += $this->controler($type, $groups, $io);
        }

        return 0 === $invalid ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param list<string> $groups
     *
     * @return int nombre de fiches invalides
     */
    private function controler(TypeFiche $type, array $groups, SymfonyStyle $io): int
    {
        $classe = $type->classeDetail() ?? throw new \LogicException('Gamme sans entité détail.');
        $invalid = $checked = 0;
        $query = $this->entityManager->createQuery(sprintf('SELECT e FROM %s e ORDER BY e.id', $classe));
        foreach ($query->toIterable() as $entite) {
            if (!$entite instanceof $classe) {
                continue;
            }
            ++$checked;
            $violations = $this->validator->validate($entite, null, $groups);
            if (count($violations) > 0) {
                ++$invalid;
                foreach ($violations as $violation) {
                    $io->writeln(json_encode([
                        'gamme' => $type->slug(),
                        'id' => $entite->id(),
                        'code' => $entite->code(),
                        'field' => (string) $violation->getPropertyPath(),
                        'error' => (string) $violation->getMessage(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                }
            }
            $this->entityManager->detach($entite);
        }
        $io->comment(sprintf('%s : %d fiche(s) contrôlée(s), %d invalide(s). Aucune donnée modifiée.', $type->libellePluriel(), $checked, $invalid));

        return $invalid;
    }
}
