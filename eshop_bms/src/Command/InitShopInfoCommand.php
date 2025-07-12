<?php

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
    description: 'Initialize default ShopInfo entry if not exists'
)]
class InitShopInfoCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $repo = $this->em->getRepository(ShopInfo::class);
        $existing = $repo->findOneBy([]);

        if ($existing) {
            $io->warning('ShopInfo already exists. Aborting.');
            return Command::SUCCESS;
        }

        $io->title('Create default ShopInfo entry');

        $eshopName = $io->ask('Shop name (e.g. Moda Vogue)');
        $address = $io->ask('Address');
        $telephone = $io->ask('Phone');
        $email = $io->ask('Email');
        $companyName = $io->ask('Company name');
        $cin = $io->ask('CIN / IČ');

        $shopInfo = new ShopInfo();
        $shopInfo->setEshopName($eshopName)
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