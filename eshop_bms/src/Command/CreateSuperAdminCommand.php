<?php

namespace App\Command;

use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateSuperAdminCommand extends Command
{
    protected static $defaultName = 'app:create-super-admin';
    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure()
    {
        $this
            ->setName('app:create-super-admin')
            ->setDescription('Creates a new super admin employee')
            ->setHelp('This command allows you to create a super admin employee...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $employees = $this->entityManager->getRepository(Employee::class)->findAll();

        foreach ($employees as $employee) {
            if (in_array('ROLE_SUPER_ADMIN', $employee->getRoles())) {
                $output->writeln('A super admin already exists.');
                return Command::FAILURE;
            }
        }

        $helper = $this->getHelper('question');

        $emailQuestion = new Question('Please enter the super admin email: ');
        $email = $helper->ask($input, $output, $emailQuestion);

        $passwordQuestion = new Question('Please enter the super admin password: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $password = $helper->ask($input, $output, $passwordQuestion);

        $employee = new Employee();
        $employee->setSurname('Super');
        $employee->setName('Admin');
        $employee->setUsername('super_admin');
        $employee->setPhoneNumber('111111111111111');
        $employee->setEmail($email);
        
        $hashedPassword = $this->passwordHasher->hashPassword($employee, $password);
        $employee->setPassword($hashedPassword);
        $employee->setRoles(['ROLE_SUPER_ADMIN']);

        $this->entityManager->persist($employee);
        $this->entityManager->flush();

        $output->writeln('Super admin created successfully.');

        return Command::SUCCESS;
    }
}