<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ShopInfo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-shopinfo',
    description: 'Initialize default ShopInfo entry if not exists',
)]
class InitShopInfoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    /**
     * Configures the command metadata and help text.
     */
    protected function configure(): void
    {
        $this->setHelp('Creates a default ShopInfo record if none exists yet.');
    }

    /**
     * Executes the command and creates a default ShopInfo record when missing.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $existing = $this->em->getRepository(ShopInfo::class)->findOneBy([]);
        if ($existing !== null) {
            $io->warning('ShopInfo already exists. Aborting.');

            return Command::SUCCESS;
        }

        $io->title('Create default ShopInfo entry');

        $eshopName = (string) $io->ask('Shop name (e.g. Moda Vogue)', '');
        $address = (string) $io->ask('Address', '');
        $telephone = (string) $io->ask('Phone', '');
        $email = (string) $io->ask('Email', '');
        $companyName = (string) $io->ask('Company name', '');
        $cin = (string) $io->ask('CIN / IČ', '');

        $shopInfo = new ShopInfo();
        $shopInfo
            ->setEshopName($eshopName)
            ->setAddress($address)
            ->setTelephone($telephone)
            ->setEmail($email)
            ->setCompanyName($companyName)
            ->setCin($cin);

        $this->em->persist($shopInfo);
        $this->em->flush();

        $io->success('Default ShopInfo created successfully.');

        return Command::SUCCESS;
    }
}