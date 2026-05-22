<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:bootstrap-admin',
    description: 'Create or repair the default admin account (for empty production databases)',
)]
class BootstrapAdminCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $_ENV['BOOTSTRAP_ADMIN_USERNAME'] ?? $_SERVER['BOOTSTRAP_ADMIN_USERNAME'] ?? 'admin';
        $email = $_ENV['BOOTSTRAP_ADMIN_EMAIL'] ?? $_SERVER['BOOTSTRAP_ADMIN_EMAIL'] ?? 'admin@gmail.com';
        $password = $_ENV['BOOTSTRAP_ADMIN_PASSWORD'] ?? $_SERVER['BOOTSTRAP_ADMIN_PASSWORD'] ?? 'admin123';
        $resetPassword = filter_var(
            $_ENV['BOOTSTRAP_ADMIN_RESET_PASSWORD'] ?? $_SERVER['BOOTSTRAP_ADMIN_RESET_PASSWORD'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        $user = $this->userRepository->findOneBy(['username' => $username]);

        if ($user instanceof User) {
            $changed = false;

            if ($user->isVerified() !== true) {
                $user->setIsVerified(true);
                $user->setVerificationToken(null);
                $changed = true;
            }

            if ($user->getStatus() !== 'active') {
                $user->setStatus('active');
                $changed = true;
            }

            if ($resetPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
                $changed = true;
                $io->warning('Admin password was reset from BOOTSTRAP_ADMIN_RESET_PASSWORD.');
            }

            if ($changed) {
                $this->entityManager->flush();
            }

            $io->success(sprintf('Admin user "%s" already exists.', $username));

            return Command::SUCCESS;
        }

        $admin = new User();
        $admin->setUsername($username);
        $admin->setEmail($email);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setStatus('active');
        $admin->setIsVerified(true);
        $admin->setVerificationToken(null);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Created admin user "%s". Change the password after first login.', $username));

        return Command::SUCCESS;
    }
}
