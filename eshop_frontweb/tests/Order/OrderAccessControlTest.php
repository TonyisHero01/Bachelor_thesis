<?php

namespace App\Tests\Order;

use App\Entity\Customer;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Access control tests for frontweb order-related endpoints.
 *
 * Design goals:
 * - Avoid following redirects (do not render Twig pages) -> stable & fast.
 * - Assert only: status code + Location header (for redirects) + DB effects (for write ops).
 * - Intentionally detect security holes (IDOR / missing auth).
 */
class OrderAccessControlTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private $client;

    private int $customerAId;
    private int $customerBId;
    private int $orderAId;
    private int $orderBId;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->followRedirects(false);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        // ---- Deterministic test data (2 customers + 2 orders) ----

        $customerA = new Customer();
        $customerA->setEmail('test_customer_a_' . uniqid('', true) . '@example.com');
        $customerA->setPasswordHash('dummy');
        $customerA->setIsVerified(true);
        $this->em->persist($customerA);

        $customerB = new Customer();
        $customerB->setEmail('test_customer_b_' . uniqid('', true) . '@example.com');
        $customerB->setPasswordHash('dummy');
        $customerB->setIsVerified(true);
        $this->em->persist($customerB);

        $this->em->flush();

        $orderA = new Order();
        $orderA->setCustomer($customerA);
        $orderA->setTotalPrice('100.00');
        $orderA->setOrderCreatedAt(new \DateTime());
        $orderA->setIsCompleted(false);
        $orderA->setPaymentStatus('PENDING');
        $orderA->setDeliveryStatus('PENDING');
        $orderA->setDeliveryMethod('pickup');
        $orderA->setAddress('Test address A');
        $orderA->setNotes(null);
        $orderA->setDiscount('0.00');
        $this->em->persist($orderA);

        $orderB = new Order();
        $orderB->setCustomer($customerB);
        $orderB->setTotalPrice('200.00');
        $orderB->setOrderCreatedAt(new \DateTime());
        $orderB->setIsCompleted(false);
        $orderB->setPaymentStatus('PENDING');
        $orderB->setDeliveryStatus('PENDING');
        $orderB->setDeliveryMethod('pickup');
        $orderB->setAddress('Test address B');
        $orderB->setNotes(null);
        $orderB->setDiscount('0.00');
        $this->em->persist($orderB);

        $this->em->flush();

        $this->customerAId = $customerA->getId();
        $this->customerBId = $customerB->getId();
        $this->orderAId = $orderA->getId();
        $this->orderBId = $orderB->getId();

        $this->em->clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::ensureKernelShutdown();
    }

    private function loginCustomer(int $customerId): void
    {
        /** @var Customer $customer */
        $customer = $this->em->getRepository(Customer::class)->find($customerId);
        self::assertNotNull($customer, 'Test customer must exist in DB.');

        $this->client->loginUser($customer, 'customer');
    }

    private function assertRedirectsToLogin(): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $loc = $this->client->getResponse()->headers->get('Location') ?? '';
        $this->assertStringContainsString('/customer/login', $loc);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGuestCannotAccessCustomerOrders(): void
    {
        $this->client->request('GET', '/customer/orders?_locale=en');
        $this->assertRedirectsToLogin();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCustomerCanAccessOwnOrders(): void
    {
        $this->loginCustomer($this->customerAId);

        $this->client->request('GET', '/customer/orders?_locale=en');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGuestCannotAccessOrderConfirmation(): void
    {
        $this->client->request('GET', '/order-confirmation/' . $this->orderAId . '?_locale=en');
        $this->assertRedirectsToLogin();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCustomerCanAccessOwnOrderConfirmation(): void
    {
        $this->loginCustomer($this->customerAId);

        $this->client->request('GET', '/order-confirmation/' . $this->orderAId . '?_locale=en');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Customer MUST NOT access confirmation page of another customer's order.
     * In your controller it throws NotFound => 404.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCustomerCannotAccessOtherUsersOrderConfirmation_IDOR(): void
    {
        $this->loginCustomer($this->customerAId);

        $this->client->request('GET', '/order-confirmation/' . $this->orderBId . '?_locale=en');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGuestCannotCancelOrder(): void
    {
        $this->client->request('POST', '/order/cancel/' . $this->orderAId . '?_locale=en');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * Scheme B: customer can cancel own PENDING order.
     * Expect 200 JSON and order removed from DB.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCustomerCanCancelOwnPendingOrder(): void
    {
        $this->loginCustomer($this->customerAId);

        $this->client->request('POST', '/order/cancel/' . $this->orderAId . '?_locale=en');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $deleted = $this->em->getRepository(Order::class)->find($this->orderAId);
        $this->assertNull($deleted, 'Cancelled order should be removed from DB.');
    }

    /**
     * Customer MUST NOT cancel another user's order (IDOR protection).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCustomerCannotCancelOtherUsersOrder_IDOR(): void
    {
        $this->loginCustomer($this->customerAId);

        $this->client->request('POST', '/order/cancel/' . $this->orderBId . '?_locale=en');

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND], true),
            'Cancelling another user order must be 403 or 404 (IDOR protection). Got: ' . $status
        );

        $stillThere = $this->em->getRepository(Order::class)->find($this->orderBId);
        $this->assertNotNull($stillThere, 'Other user order must not be deleted by attacker.');
    }

    /**
     * Customer MUST NOT submit delivery for another user's order (IDOR).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCustomerCannotSubmitDeliveryForOtherUsersOrder_IDOR(): void
    {
        $this->loginCustomer($this->customerAId);

        $payload = json_encode([
            'deliveryMethod' => 'pickup',
            'address' => 'Hacked address',
            'notes' => 'Hacked',
        ], JSON_THROW_ON_ERROR);

        $this->client->request(
            'POST',
            '/order/submit_delivery/' . $this->orderBId . '?_locale=en',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload
        );

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND], true),
            'Submitting delivery for another user order must be 403 or 404 (IDOR protection). Got: ' . $status
        );
    }
}