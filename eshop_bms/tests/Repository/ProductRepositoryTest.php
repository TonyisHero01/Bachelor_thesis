<?php

namespace App\Tests\Repository;

use App\Entity\Product;
use App\Entity\Currency;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $em = null;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();
        $this->em = $em;

        $classes = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);

        try {
            $schemaTool->dropSchema($classes);
        } catch (\Throwable $e) {
        }
        $schemaTool->createSchema($classes);
    }

    protected function tearDown(): void
    {
        if ($this->em) {
            $this->em->close();
            $this->em = null;
        }

        self::ensureKernelShutdown();
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFindLatestVersionProductsReturnsOnlyMaxVersionPerSku(): void
    {
        $em = $this->em;
        $this->assertNotNull($em);

        $currency = $this->makeCurrency();
        $em->persist($currency);

        // SKU A: v1, v2 (latest = v2)
        $pA1 = $this->makeProduct('SKU-A', 1, 'A v1', $currency, new \DateTimeImmutable('2025-01-01 10:00:00'));
        $pA2 = $this->makeProduct('SKU-A', 2, 'A v2', $currency, new \DateTimeImmutable('2025-01-02 10:00:00'));

        // SKU B: v1, v3 (latest = v3)
        $pB1 = $this->makeProduct('SKU-B', 1, 'B v1', $currency, new \DateTimeImmutable('2025-01-01 11:00:00'));
        $pB3 = $this->makeProduct('SKU-B', 3, 'B v3', $currency, new \DateTimeImmutable('2025-01-03 09:00:00'));

        $em->persist($pA1);
        $em->persist($pA2);
        $em->persist($pB1);
        $em->persist($pB3);

        $em->flush();
        $em->clear();

        /** @var ProductRepository $repo */
        $repo = self::getContainer()->get(ProductRepository::class);
        $result = $repo->findLatestVersionProducts();

        $this->assertCount(2, $result);

        $map = [];
        foreach ($result as $p) {
            $map[$p->getSku()] = $p->getVersion();
        }

        $this->assertSame(2, $map['SKU-A'] ?? null);
        $this->assertSame(3, $map['SKU-B'] ?? null);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFindLatestVersionProductsOrderingByCreatedAtDescThenIdDesc(): void
    {
        $em = $this->em;
        $this->assertNotNull($em);

        $currency = $this->makeCurrency();
        $em->persist($currency);

        $latest1 = $this->makeProduct('SKU-X', 5, 'X v5', $currency, new \DateTimeImmutable('2025-01-01 10:00:00'));
        $latest2 = $this->makeProduct('SKU-Y', 2, 'Y v2', $currency, new \DateTimeImmutable('2025-01-02 10:00:00'));

        $em->persist($latest1);
        $em->persist($latest2);

        $em->flush();
        $em->clear();

        /** @var ProductRepository $repo */
        $repo = self::getContainer()->get(ProductRepository::class);
        $result = $repo->findLatestVersionProducts();

        $this->assertCount(2, $result);

        $this->assertSame('SKU-Y', $result[0]->getSku());
        $this->assertSame('SKU-X', $result[1]->getSku());
    }

    private function makeProduct(
        string $sku,
        int $version,
        string $name,
        Currency $currency,
        \DateTimeImmutable $createdAt
    ): Product {
        $p = new Product();
        $p->setSku($sku);
        $p->setVersion($version);
        $p->setName($name);

        $p->setCurrency($currency);

        $p->setNumberInStock(10);
        $p->setPrice(123.45);
        $p->setDiscount(100.0);
        $p->setHidden(false);
        $p->setTaxRate(21.0);

        $p->setCreatedAt($createdAt);
        $p->setUpdatedAt($createdAt);

        return $p;
    }

    private function makeCurrency(): Currency
    {
        $em = $this->em;
        $this->assertNotNull($em);

        $c = new Currency();
        $meta = $em->getClassMetadata(Currency::class);

        foreach ($meta->fieldMappings as $field => $mapping) {
            if (!empty($mapping['id'])) {
                continue;
            }

            $type = $mapping['type'] ?? null;
            $nullable = $mapping['nullable'] ?? false;

            if ($nullable) {
                continue;
            }

            $value = null;

            if ($type === 'string') {
                $len = (int)($mapping['length'] ?? 255);
                $value = ($len === 3) ? 'CZK' : str_repeat('A', max(1, min($len, 10)));
            } elseif ($type === 'integer' || $type === 'smallint' || $type === 'bigint') {
                $value = 1;
            } elseif ($type === 'float' || $type === 'decimal') {
                $value = 1.0;
            } elseif ($type === 'boolean') {
                $value = false;
            } elseif ($type === 'datetime_immutable' || $type === 'datetimetz_immutable') {
                $value = new \DateTimeImmutable('2025-01-01 00:00:00');
            } elseif ($type === 'datetime' || $type === 'datetimetz') {
                $value = new \DateTime('2025-01-01 00:00:00');
            } elseif ($type === 'json') {
                $value = [];
            } else {
                continue;
            }

            $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
            if (method_exists($c, $setter)) {
                $c->$setter($value);
            } else {
                $ref = new \ReflectionClass($c);
                if ($ref->hasProperty($field)) {
                    $prop = $ref->getProperty($field);
                    $prop->setAccessible(true);
                    $prop->setValue($c, $value);
                }
            }
        }

        return $c;
    }
}