<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Currency;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductRepositoryTest extends KernelTestCase
{
    private const TEST_SKU_PREFIX = 'TEST-REPOSITORY-';

    private ?EntityManagerInterface $em = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()
            ->get('doctrine')
            ->getManager();

        $this->em = $entityManager;

        /*
         * Remove products left by an interrupted earlier test run.
         * Existing application products and database structure remain intact.
         */
        $this->removeTestProducts();
    }

    protected function tearDown(): void
    {
        if ($this->em !== null && $this->em->isOpen()) {
            try {
                $this->removeTestProducts();
            } finally {
                $this->em->clear();
                $this->em->close();
                $this->em = null;
            }
        }

        self::ensureKernelShutdown();

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFindLatestVersionProductsReturnsOnlyMaxVersionPerSku(): void
    {
        $entityManager = $this->requireEntityManager();
        $currency = $this->getOrCreateCurrency();

        $testPrefix = self::TEST_SKU_PREFIX
            . strtoupper(bin2hex(random_bytes(6)));

        $skuA = $testPrefix . '-A';
        $skuB = $testPrefix . '-B';

        // SKU A: versions 1 and 2; version 2 must be returned.
        $productA1 = $this->makeProduct(
            $skuA,
            1,
            'Repository test A v1',
            $currency,
            new \DateTimeImmutable('2025-01-01 10:00:00')
        );

        $productA2 = $this->makeProduct(
            $skuA,
            2,
            'Repository test A v2',
            $currency,
            new \DateTimeImmutable('2025-01-02 10:00:00')
        );

        // SKU B: versions 1 and 3; version 3 must be returned.
        $productB1 = $this->makeProduct(
            $skuB,
            1,
            'Repository test B v1',
            $currency,
            new \DateTimeImmutable('2025-01-01 11:00:00')
        );

        $productB3 = $this->makeProduct(
            $skuB,
            3,
            'Repository test B v3',
            $currency,
            new \DateTimeImmutable('2025-01-03 09:00:00')
        );

        $entityManager->persist($productA1);
        $entityManager->persist($productA2);
        $entityManager->persist($productB1);
        $entityManager->persist($productB3);

        $entityManager->flush();
        $entityManager->clear();

        /*
         * Verify that all four product rows were actually saved.
         */
        $savedProducts = $entityManager
            ->getRepository(Product::class)
            ->findBy([
                'sku' => [$skuA, $skuB],
            ]);

        self::assertCount(
            4,
            $savedProducts,
            'All four product versions should exist in the test database.'
        );

        /** @var ProductRepository $repository */
        $repository = self::getContainer()->get(
            ProductRepository::class
        );

        /*
         * Use the repository SKU filter so products copied from app do not
         * affect pagination or the result count.
         */
        $result = $repository->findLatestVersionProducts(
            limit: 20,
            offset: 0,
            skuFilter: $testPrefix
        );

        self::assertCount(
            2,
            $result,
            'The repository should return one latest row for each test SKU.'
        );

        $versionsBySku = [];

        foreach ($result as $product) {
            $sku = $product->getSku();

            if ($sku !== null) {
                $versionsBySku[$sku] = $product->getVersion();
            }
        }

        self::assertSame(
            2,
            $versionsBySku[$skuA] ?? null,
            'The repository should return version 2 for SKU A.'
        );

        self::assertSame(
            3,
            $versionsBySku[$skuB] ?? null,
            'The repository should return version 3 for SKU B.'
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFindLatestVersionProductsOrdersBySkuAscending(): void
    {
        $entityManager = $this->requireEntityManager();
        $currency = $this->getOrCreateCurrency();

        $testPrefix = self::TEST_SKU_PREFIX
            . strtoupper(bin2hex(random_bytes(6)));

        $skuA = $testPrefix . '-A';
        $skuB = $testPrefix . '-B';

        /*
         * Creation dates intentionally use the opposite order.
         * The repository should still order by SKU ASC.
         */
        $productA = $this->makeProduct(
            $skuA,
            5,
            'Repository ordering test A',
            $currency,
            new \DateTimeImmutable('2025-01-02 10:00:00')
        );

        $productB = $this->makeProduct(
            $skuB,
            2,
            'Repository ordering test B',
            $currency,
            new \DateTimeImmutable('2025-01-01 10:00:00')
        );

        /*
         * Persist B first so database ID order also differs from SKU order.
         */
        $entityManager->persist($productB);
        $entityManager->persist($productA);

        $entityManager->flush();
        $entityManager->clear();

        /** @var ProductRepository $repository */
        $repository = self::getContainer()->get(
            ProductRepository::class
        );

        $result = $repository->findLatestVersionProducts(
            limit: 20,
            offset: 0,
            skuFilter: $testPrefix
        );

        self::assertCount(
            2,
            $result,
            'Both test products should be returned.'
        );

        $returnedSkus = array_map(
            static fn (Product $product): ?string => $product->getSku(),
            $result
        );

        self::assertSame(
            [$skuA, $skuB],
            $returnedSkus,
            'Products should be ordered by SKU in ascending order.'
        );
    }

    private function makeProduct(
        string $sku,
        int $version,
        string $name,
        Currency $currency,
        \DateTimeImmutable $createdAt
    ): Product {
        return (new Product())
            ->setSku($sku)
            ->setVersion($version)
            ->setName($name)
            ->setCurrency($currency)
            ->setDescription('Product created by ProductRepositoryTest.')
            ->setNumberInStock(10)
            ->setPrice(123.45)
            ->setDiscount(100.0)
            ->setHidden(false)
            ->setTaxRate(21.0)
            ->setImageUrls([])
            ->setAttributes([])
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($createdAt);
    }

    /**
     * Reuse an existing currency copied from app.
     *
     * If no currency exists, dynamically create one based on Doctrine
     * metadata so the test does not depend on the exact Currency fields.
     */
    private function getOrCreateCurrency(): Currency
    {
        $entityManager = $this->requireEntityManager();

        $existingCurrency = $entityManager
            ->getRepository(Currency::class)
            ->findOneBy([]);

        if ($existingCurrency instanceof Currency) {
            return $existingCurrency;
        }

        $currency = new Currency();
        $metadata = $entityManager->getClassMetadata(Currency::class);

        foreach ($metadata->fieldMappings as $field => $mapping) {
            if ($this->isIdentifierMapping($mapping)) {
                continue;
            }

            $nullable = (bool) $this->mappingValue(
                $mapping,
                'nullable',
                false
            );

            if ($nullable) {
                continue;
            }

            $type = (string) $this->mappingValue(
                $mapping,
                'type',
                ''
            );

            $length = (int) $this->mappingValue(
                $mapping,
                'length',
                255
            );

            $value = $this->makeRequiredFieldValue(
                $type,
                $length
            );

            if ($value === null) {
                continue;
            }

            $this->writeEntityField(
                $currency,
                (string) $field,
                $value
            );
        }

        $entityManager->persist($currency);
        $entityManager->flush();

        return $currency;
    }

    private function removeTestProducts(): void
    {
        if ($this->em === null || !$this->em->isOpen()) {
            return;
        }

        $this->em
            ->createQuery(
                <<<'DQL'
DELETE FROM App\Entity\Product product
WHERE product.sku LIKE :prefix
DQL
            )
            ->setParameter(
                'prefix',
                self::TEST_SKU_PREFIX . '%'
            )
            ->execute();

        $this->em->clear();
    }

    private function requireEntityManager(): EntityManagerInterface
    {
        self::assertNotNull(
            $this->em,
            'The Doctrine EntityManager has not been initialized.'
        );

        return $this->em;
    }

    private function makeRequiredFieldValue(
        string $type,
        int $length
    ): mixed {
        return match ($type) {
            'string' => $length === 3
                ? 'TST'
                : substr(
                    'RepositoryTest',
                    0,
                    max(1, $length)
                ),

            'integer',
            'smallint',
            'bigint' => 1,

            'float' => 1.0,

            'decimal' => '1.00',

            'boolean' => false,

            'datetime_immutable',
            'datetimetz_immutable' => new \DateTimeImmutable(
                '2025-01-01 00:00:00'
            ),

            'datetime',
            'datetimetz' => new \DateTime(
                '2025-01-01 00:00:00'
            ),

            'date_immutable' => new \DateTimeImmutable(
                '2025-01-01'
            ),

            'date' => new \DateTime(
                '2025-01-01'
            ),

            'json' => [],

            default => null,
        };
    }

    private function writeEntityField(
        object $entity,
        string $field,
        mixed $value
    ): void {
        $setter = 'set' . str_replace(
            ' ',
            '',
            ucwords(
                str_replace('_', ' ', $field)
            )
        );

        if (method_exists($entity, $setter)) {
            $entity->{$setter}($value);

            return;
        }

        $reflection = new \ReflectionClass($entity);

        if (!$reflection->hasProperty($field)) {
            return;
        }

        $property = $reflection->getProperty($field);
        $property->setValue($entity, $value);
    }

    private function isIdentifierMapping(mixed $mapping): bool
    {
        if (is_array($mapping)) {
            return !empty($mapping['id']);
        }

        return isset($mapping->id) && (bool) $mapping->id;
    }

    private function mappingValue(
        mixed $mapping,
        string $property,
        mixed $default = null
    ): mixed {
        if (is_array($mapping)) {
            return $mapping[$property] ?? $default;
        }

        return $mapping->{$property} ?? $default;
    }
}