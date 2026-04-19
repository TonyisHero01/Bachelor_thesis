<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Currency;
use App\Entity\ShopInfo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-shopinfo',
    description: 'Initialize default ShopInfo and default Currency if not exists',
)]
class InitShopInfoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Creates a default ShopInfo record and default Currency record if they do not exist yet.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $shopInfoCreated = false;
        $currencyCreated = false;

        $existingShopInfo = $this->em->getRepository(ShopInfo::class)->findOneBy([]);

        if ($existingShopInfo === null) {
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
            $shopInfoCreated = true;
        } else {
            $io->note('ShopInfo already exists. Skipping ShopInfo creation.');
        }

        $currencyRepo = $this->em->getRepository(Currency::class);
        $existingCurrency = $currencyRepo->findOneBy(['isDefault' => true]);

        if ($existingCurrency === null) {
            $io->section('Create default currency');

            $currencyName = (string) $io->ask('Default currency (e.g. CZK, EUR)', 'CZK');
            $currencyValue = (float) $io->ask('Currency value (base = 1.0)', '1');

            $currency = new Currency();
            $currency
                ->setName($currencyName)
                ->setValue($currencyValue)
                ->setIsDefault(true);

            $this->em->persist($currency);
            $currencyCreated = true;
        } else {
            $io->note(sprintf('Default currency already exists: %s', $existingCurrency->getName()));
        }

        if ($shopInfoCreated || $currencyCreated) {
            $this->em->flush();
        }

        if ($shopInfoCreated) {
            $io->success('Default ShopInfo created successfully.');
        }

        if ($currencyCreated) {
            $io->success('Default Currency created successfully.');
        }

        if (!$shopInfoCreated && !$currencyCreated) {
            $io->warning('Nothing was created. ShopInfo and default Currency already exist.');
        }

        return Command::SUCCESS;
    }
}
