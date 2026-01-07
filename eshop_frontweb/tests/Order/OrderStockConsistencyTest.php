<?php

namespace App\Tests\Frontweb;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Currency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

final class OrderStockConsistencyTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        // ✅ 只能在这里创建一次 client（它会 boot kernel）
        $this->client = static::createClient();

        // ✅ 从 client 拿 container（不会触发二次 boot）
        $this->em = $this->client->getContainer()->get(EntityManagerInterface::class);

        // 清理订单/购物车，避免互相污染
        $this->purgeCartsAndOrders();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAddToCartRequiresLogin(): void
    {
        $this->jsonRequest('POST', '/cart/add', [
            'productId' => 1,
            'quantity'  => 1,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCreateOrderDecreasesStockWhenSufficient(): void
    {
        $customer = $this->createCustomer('buyer1@example.com', 'Password123!');
        $product  = $this->createProduct('TEST-SKU-A', 10, 100.0);

        // ✅ 你的 firewall 叫 customer
        $this->client->loginUser($customer, 'customer');

        // add_to_cart (JSON)
        $this->jsonRequest('POST', '/cart/add', [
            'productId' => $product->getId(),
            'quantity'  => 3,
        ]);
        $this->assertResponseIsSuccessful();
        $add = json_decode($this->client->getResponse()->getContent(), true);
        self::assertTrue($add['success'] ?? false);

        // order_create (JSON)
        $this->jsonRequest('POST', '/order/create', [
            'deliveryMethod' => 'pickup',
            'address'        => 'Test Address',
            'notes'          => 'Test order',
        ]);
        $this->assertResponseIsSuccessful();
        $orderResp = json_decode($this->client->getResponse()->getContent(), true);
        self::assertTrue($orderResp['success'] ?? false);
        $orderId = (int) ($orderResp['orderId'] ?? 0);
        self::assertGreaterThan(0, $orderId);

        // 刷新后断言库存
        $this->em->clear();
        $freshProduct = $this->em->getRepository(Product::class)->find($product->getId());
        self::assertSame(7, $freshProduct->getNumberInStock());

        // 断言订单项
        $order = $this->em->getRepository(Order::class)->find($orderId);
        self::assertNotNull($order);

        $items = $this->em->getRepository(OrderItem::class)->findBy(['order' => $order]);
        self::assertCount(1, $items);
        self::assertSame(3, $items[0]->getQuantity());
        self::assertSame('TEST-SKU-A', $items[0]->getSku());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCreateOrderFailsWhenInsufficientStock(): void
    {
        $customer = $this->createCustomer('buyer2@example.com', 'Password123!');
        $product  = $this->createProduct('TEST-SKU-B', 1, 50.0);

        $this->client->loginUser($customer, 'customer');

        $this->jsonRequest('POST', '/cart/add', [
            'productId' => $product->getId(),
            'quantity'  => 2,
        ]);
        $this->assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/order/create', [
            'deliveryMethod' => 'pickup',
            'address'        => 'Test Address',
            'notes'          => 'Should fail',
        ]);

        // 你的 createOrder() 库存不足时返回 400
        $this->assertResponseStatusCodeSame(400);

        // 库存不变
        $this->em->clear();
        $freshProduct = $this->em->getRepository(Product::class)->find($product->getId());
        self::assertSame(1, $freshProduct->getNumberInStock());

        // 订单不应落库（你代码在 flush 之前 return）
        $orders = $this->em->getRepository(Order::class)->findBy(['customer' => $customer]);
        self::assertCount(0, $orders);
    }

    // ---------------- helpers ----------------

    private function jsonRequest(string $method, string $uri, array $data): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($data, JSON_THROW_ON_ERROR)
        );
    }

    private function createCustomer(string $email, string $plainPassword): Customer
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $this->client->getContainer()->get(UserPasswordHasherInterface::class);

        $c = new Customer();
        $c->setEmail($email);
        $c->setIsVerified(true);
        $c->setPasswordHash($hasher->hashPassword($c, $plainPassword));

        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }

    private function createProduct(string $sku, int $stock, float $price): Product
    {
        // currency 不可为 null：找一个现成的 currency
        $currency = $this->em->getRepository(Currency::class)->findOneBy([]);
        if (!$currency) {
            self::fail('No Currency found in DB. Seed at least one Currency row for tests, or send me Currency entity and I will create one here.');
        }

        $p = new Product();
        $p->setName('Test Product ' . $sku);
        $p->setSku($sku);
        $p->setNumberInStock($stock);
        $p->setPrice($price);
        $p->setDiscount(100.0);
        $p->setTaxRate(21.0);
        $p->setHidden(false);
        $p->setCurrency($currency);
        $p->setCreatedAt(new \DateTimeImmutable());
        $p->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    private function purgeCartsAndOrders(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('DELETE FROM order_items');
        $conn->executeStatement('DELETE FROM orders');
        $conn->executeStatement('DELETE FROM cart');
    }
    protected function tearDown(): void
    {
        parent::tearDown();
        self::ensureKernelShutdown();
    }
    
}