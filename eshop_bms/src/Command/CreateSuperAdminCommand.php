<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-super-admin',
    description: 'Creates a new super admin employee'
)]
class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    /**
     * Configures the command metadata and help text.
     */
    protected function configure(): void
    {
        $this->setHelp('This command allows you to create a super admin employee.');
    }

    /**
     * Executes the command and creates a super admin if none exists yet.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->superAdminExists()) {
            $output->writeln('A super admin already exists.');

            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');

        $email = $this->askNonEmptyString($input, $output, $helper, 'Please enter the super admin email: ', false);
        if ($email === null) {
            $output->writeln('Email cannot be empty.');

            return Command::FAILURE;
        }

        $password = $this->askNonEmptyString(
            $input,
            $output,
            $helper,
            'Please enter the super admin password: ',
            true
        );

        if ($password === null) {
            $output->writeln('Password cannot be empty.');

            return Command::FAILURE;
        }

        $employee = new Employee();
        $employee->setSurname('Super');
        $employee->setName('Admin');
        $employee->setPhoneNumber('111111111111111');
        $employee->setEmail($email);
        $employee->setRoles(['ROLE_SUPER_ADMIN']);
        $employee->setPassword($this->passwordHasher->hashPassword($employee, $password));

        $this->entityManager->persist($employee);
        $this->entityManager->flush();

        $output->writeln('Super admin created successfully.');

        return Command::SUCCESS;
    }

    /**
     * Checks whether a super admin employee already exists.
     */
    private function superAdminExists(): bool
    {
        $employees = $this->entityManager->getRepository(Employee::class)->findAll();

        foreach ($employees as $employee) {
            if (in_array('ROLE_SUPER_ADMIN', $employee->getRoles(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Asks the user for a non-empty string value.
     *
     * @return string|null Returns null when the input is empty or invalid.
     */
    private function askNonEmptyString(
        InputInterface $input,
        OutputInterface $output,
        mixed $helper,
        string $questionText,
        bool $hidden
    ): ?string {
        $question = new Question($questionText);

        if ($hidden) {
            $question->setHidden(true);
            $question->setHiddenFallback(false);
        }

        $value = $helper->ask($input, $output, $question);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}