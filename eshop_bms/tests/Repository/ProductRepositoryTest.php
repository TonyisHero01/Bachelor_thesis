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
         * Remove data left by an interrupted previous test execution.
         * Only test products with our dedicated prefix are deleted.
         *
         * The database structure and existing application data remain intact.
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
        $em = $this->requireEntityManager();
        $currency = $this->getOrCreateCurrency();

        $testId = strtoupper(bin2hex(random_bytes(6)));

        $skuA = self::TEST_SKU_PREFIX . $testId . '-A';
        $skuB = self::TEST_SKU_PREFIX . $testId . '-B';

        // SKU A: v1 and v2; the latest version must be v2.
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

        // SKU B: v1 and v3; the latest version must be v3.
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

        $em->persist($productA1);
        $em->persist($productA2);
        $em->persist($productB1);
        $em->persist($productB3);
        $em->flush();
        $em->clear();

        /** @var ProductRepository $repository */
        $repository = self::getContainer()->get(ProductRepository::class);

        $allResults = $repository->findLatestVersionProducts();

        /*
         * app_test is copied from app and can already contain products.
         * Therefore, only products created by this test are evaluated.
         */
        $testResults = $this->filterProductsBySkus(
            $allResults,
            [$skuA, $skuB]
        );

        self::assertCount(
            2,
            $testResults,
            'The repository should return exactly one latest product per test SKU.'
        );

        $versionsBySku = [];

        foreach ($testResults as $product) {
            $sku = $product->getSku();

            if ($sku !== null) {
                $versionsBySku[$sku] = $product->getVersion();
            }
        }

        self::assertSame(
            2,
            $versionsBySku[$skuA] ?? null,
            'The highest version for SKU A should be returned.'
        );

        self::assertSame(
            3,
            $versionsBySku[$skuB] ?? null,
            'The highest version for SKU B should be returned.'
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFindLatestVersionProductsOrderingByCreatedAtDescThenIdDesc(): void
    {
        $em = $this->requireEntityManager();
        $currency = $this->getOrCreateCurrency();

        $testId = strtoupper(bin2hex(random_bytes(6)));

        $skuX = self::TEST_SKU_PREFIX . $testId . '-X';
        $skuY = self::TEST_SKU_PREFIX . $testId . '-Y';

        $productX = $this->makeProduct(
            $skuX,
            5,
            'Repository ordering test X',
            $currency,
            new \DateTimeImmutable('2025-01-01 10:00:00')
        );

        $productY = $this->makeProduct(
            $skuY,
            2,
            'Repository ordering test Y',
            $currency,
            new \DateTimeImmutable('2025-01-02 10:00:00')
        );

        $em->persist($productX);
        $em->persist($productY);
        $em->flush();
        $em->clear();

        /** @var ProductRepository $repository */
        $repository = self::getContainer()->get(ProductRepository::class);

        $allResults = $repository->findLatestVersionProducts();

        /*
         * Preserve the order returned by the repository while removing
         * unrelated products copied from the application database.
         */
        $testResults = $this->filterProductsBySkus(
            $allResults,
            [$skuX, $skuY]
        );

        self::assertCount(
            2,
            $testResults,
            'Both test products should be returned.'
        );

        $returnedSkus = array_map(
            static fn (Product $product): ?string => $product->getSku(),
            $testResults
        );

        self::assertSame(
            [$skuY, $skuX],
            $returnedSkus,
            'Products should be ordered by createdAt DESC and then id DESC.'
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
            ->setNumberInStock(10)
            ->setPrice(123.45)
            ->setDiscount(100.0)
            ->setHidden(false)
            ->setTaxRate(21.0)
            ->setDescription('Product created by ProductRepositoryTest.')
            ->setImageUrls([])
            ->setAttributes([])
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($createdAt);
    }

    /**
     * Reuse an existing currency from the copied database whenever possible.
     *
     * If the source database contains no currency, create one dynamically
     * using Doctrine metadata so the test does not depend on a particular
     * Currency entity implementation.
     */
    private function getOrCreateCurrency(): Currency
    {
        $em = $this->requireEntityManager();

        $existingCurrency = $em
            ->getRepository(Currency::class)
            ->findOneBy([]);

        if ($existingCurrency instanceof Currency) {
            return $existingCurrency;
        }

        $currency = new Currency();
        $metadata = $em->getClassMetadata(Currency::class);

        $randomCode = $this->generateCurrencyCode();

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
                $length,
                $randomCode
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

        $em->persist($currency);
        $em->flush();

        return $currency;
    }

    /**
     * @param array<int, Product> $products
     * @param array<int, string>  $skus
     *
     * @return array<int, Product>
     */
    private function filterProductsBySkus(
        array $products,
        array $skus
    ): array {
        return array_values(
            array_filter(
                $products,
                static fn (Product $product): bool => in_array(
                    $product->getSku(),
                    $skus,
                    true
                )
            )
        );
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
            ->setParameter('prefix', self::TEST_SKU_PREFIX . '%')
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

    private function generateCurrencyCode(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';

        for ($index = 0; $index < 3; ++$index) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    private function makeRequiredFieldValue(
        string $type,
        int $length,
        string $currencyCode
    ): mixed {
        return match ($type) {
            'string' => $length === 3
                ? $currencyCode
                : substr('RepositoryTest', 0, max(1, $length)),

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

            'date_immutable' => new \DateTimeImmutable('2025-01-01'),

            'date' => new \DateTime('2025-01-01'),

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
            ucwords(str_replace('_', ' ', $field))
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