<?php

declare(strict_types=1);

namespace App\Account\Command;

use App\Account\Entity\User;
use App\Account\Repository\UserRepository;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:user:create-super-admin',
    description: 'Crée le premier compte super-administrateur local.',
)]
final class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly ParametreProviderInterface $parametres,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Adresse email du super-administrateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = User::normalizeEmail((string) $input->getArgument('email'));
        $emailViolations = $this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);

        if (count($emailViolations) > 0) {
            $io->error('L’adresse email fournie n’est pas valide.');

            return Command::INVALID;
        }

        $existingUser = $this->users->findOneByEmail($email);
        if (null !== $existingUser) {
            if ($existingUser->isActive() && in_array('ROLE_SUPER_ADMIN', $existingUser->getRoles(), true)) {
                $io->success(sprintf('Le super-administrateur %s existe déjà.', $email));

                return Command::SUCCESS;
            }

            $io->error('Un compte non super-administrateur utilise déjà cette adresse. Aucune modification effectuée.');

            return Command::FAILURE;
        }

        $longueurMin = max(1, $this->parametres->int('compte.mot_de_passe_longueur_min'));
        $password = (string) $io->askHidden(sprintf('Mot de passe (%d caractères minimum)', $longueurMin));
        $confirmation = (string) $io->askHidden('Confirmez le mot de passe');

        if (!hash_equals($password, $confirmation)) {
            $io->error('Les mots de passe ne correspondent pas.');

            return Command::INVALID;
        }

        $passwordViolations = $this->validator->validate($password, [
            new Assert\Length(min: $longueurMin, max: 4096),
            new Assert\NotCompromisedPassword(),
        ]);
        if (count($passwordViolations) > 0) {
            $io->error(sprintf('Le mot de passe doit contenir au moins %d caractères et ne pas être compromis.', $longueurMin));

            return Command::INVALID;
        }

        $user = new User($email, ['ROLE_SUPER_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Le super-administrateur %s a été créé.', $email));

        return Command::SUCCESS;
    }
}
