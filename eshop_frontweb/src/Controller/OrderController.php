<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ShopInfo;
use App\Entity\Product;
use App\Service\ShipmentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class OrderController extends BaseController
{
    private ?ShopInfo $shopInfo = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly ShipmentService $shipmentService,
        Environment $twig,
        LoggerInterface $logger,
    ) {
        parent::__construct($twig, $logger);

        $this->shopInfo = $this->entityManager
            ->getRepository(ShopInfo::class)
            ->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Confirms an order for the currently authenticated customer.
     */
    #[Route('/order/confirm/{id}', name: 'confirm_order', methods: ['POST'])]
    public function confirmOrder(int $id): JsonResponse
    {
        $customer = $this->getUser();
        if (!$customer instanceof Customer) {
            return new JsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $order = $this->entityManager->getRepository(Order::class)->find($id);
        if (!$order instanceof Order || $order->getCustomer() !== $customer) {
            return new JsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * Cancels an order and restores product stock when cancellation is allowed.
     */
    #[Route('/order/cancel/{id}', name: 'cancel_order', methods: ['POST'])]
    public function cancelOrder(int $id): JsonResponse
    {
        $customer = $this->getUser();
        if (!$customer instanceof Customer) {
            return new JsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $order = $this->entityManager->getRepository(Order::class)->find($id);
        if (!$order instanceof Order || $order->getCustomer() !== $customer) {
            return new JsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ($order->getIsCompleted() || $order->getPaymentStatus() === 'COMPLETED') {
            return new JsonResponse(['success' => false, 'message' => 'Order cannot be cancelled.'], 400);
        }

        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();
            if ($product) {
                $product->setNumberInStock($product->getNumberInStock() + $item->getQuantity());
                $this->entityManager->persist($product);
            }
        }

        $this->entityManager->remove($order);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Renders the order success page for the authenticated customer.
     */
    #[Route('/order/success/{id}', name: 'order_success', methods: ['GET'])]
    public function orderSuccess(int $id, Request $request): Response
    {
        $customer = $this->getUser();
        if (!$customer instanceof Customer) {
            return $this->redirectToRoute('customer_login');
        }

        $order = $this->entityManager->getRepository(Order::class)->find($id);
        if (!$order instanceof Order || $order->getCustomer() !== $customer) {
            throw $this->createNotFoundException('Order not found.');
        }

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop_order/order_success.html.twig',
            [
                'shopInfo' => $this->shopInfo,
                'locale' => (string) $request->getLocale(),
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
                'order' => $order,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Clears all cart items for the authenticated customer after a successful order.
     */
    #[Route('/cart/clear_after_success', name: 'clear_cart_after_success', methods: ['POST'])]
    public function clearCartAfterSuccess(): JsonResponse
    {
        $customer = $this->getUser();
        if (!$customer instanceof Customer) {
            return new JsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $cartItems = $this->entityManager->getRepository(Cart::class)->findBy(['customer' => $customer]);

        foreach ($cartItems as $cartItem) {
            $this->entityManager->remove($cartItem);
        }

        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Cart has been cleared.']);
    }

    /**
     * Renders the delivery options page for an order owned by the authenticated customer.
     */
    #[Route('/order/delivery-options/{id}', name: 'order_delivery_options', methods: ['GET'])]
    public function deliveryOptions(int $id, Request $request): Response
    {
        $customer = $this->getUser();
        if (!$customer instanceof Customer) {
            return $this->redirectToRoute('customer_login');
        }

        $order = $this->entityManager->getRepository(Order::class)->find($id);
        if (!$order instanceof Order || $order->getCustomer() !== $customer) {
            throw $this->createNotFoundException('Order not found.');
        }

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop_order/order_delivery_options.html.twig',
            [
                'shopInfo' => $this->shopInfo,
                'locale' => (string) $request->getLocale(),
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
                'order' => $order,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Renders the delivery method selection page.
     */
    #[Route('/order/select_delivery', name: 'order_select_delivery', methods: ['GET'])]
    public function selectDelivery(Request $request): Response
    {
        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop_order/select_delivery.html.twig',
            [
                'shopInfo' => $this->shopInfo,
                'locale' => (string) $request->getLocale(),
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Updates delivery method, address and notes for a pending order owned by the authenticated customer.
     */
    #[Route('/order/submit_delivery/{id}', name: 'order_submit_delivery', methods: ['POST'])]
    public function submitDelivery(int $id, Request $request): JsonResponse
    {
        $customer = $this->getUser();
        if (!$customer instanceof Customer) {
            return new JsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $order = $this->entityManager->getRepository(Order::class)->find($id);
        if (!$order instanceof Order || $order->getCustomer() !== $customer) {
            return new JsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ($order->getIsCompleted() || $order->getPaymentStatus() === 'COMPLETED') {
            return new JsonResponse(['success' => false, 'message' => 'Order cannot be modified.'], 400);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body'], 400);
        }

        $deliveryMethod = $data['deliveryMethod'] ?? null;
        $address = $data['address'] ?? '';
        $notes = $data['notes'] ?? '';

        if (!is_string($deliveryMethod) || !in_array($deliveryMethod, ['pickup', 'delivery'], true)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid delivery method.'], 400);
        }

        $order->setDeliveryMethod($deliveryMethod);
        $order->setAddress((string) $address);
        $order->setNotes($notes !== null && (string) $notes !== '' ? (string) $notes : null);

        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Creates an order from the authenticated customer's cart and decrements stock.
     */
    #[Route('/order/create', name: 'order_create', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {

        $this->logger->info('[OrderCreate] called', [
            'user' => $this->getUser() ? get_class($this->getUser()) : null,
            'content' => $request->getContent(),
        ]);
        $customer = $this->getUser();
        if (!$customer instanceof Customer) {
            return new JsonResponse(['success' => false, 'message' => 'User not logged in'], 403);
        }

        $cartItems = $this->entityManager->getRepository(Cart::class)->findBy(['customer' => $customer]);
        if ($cartItems === []) {
            return new JsonResponse(['success' => false, 'message' => 'Your cart is empty'], 400);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body'], 400);
        }

        $deliveryMethod = $data['deliveryMethod'] ?? null;
        $address = (string) ($data['address'] ?? '');
        $orderNote = (string) ($data['notes'] ?? '');

        if (!is_string($deliveryMethod) || !in_array($deliveryMethod, ['pickup', 'delivery'], true)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid delivery method.'], 400);
        }

        $totalPrice = 0.0;
        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            if (!$product instanceof Product) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid cart item product'], 400);
            }

            $quantity = (int) $cartItem->getQuantity();
            $price = (float) $product->getPrice();
            $discount = (float) $product->getDiscount();
            $finalUnitPrice = $price * ($discount / 100.0);

            $totalPrice += $finalUnitPrice * $quantity;
        }

        $order = new Order();
        $order->setCustomer($customer);
        $order->setTotalPrice((string) round($totalPrice, 2));
        $order->setOrderCreatedAt(new \DateTime());
        $order->setIsCompleted(false);
        $order->setPaymentStatus('PENDING');
        $order->setDeliveryStatus('PENDING');
        $order->setDeliveryMethod($deliveryMethod);
        $order->setAddress($address);
        $order->setNotes($orderNote !== '' ? $orderNote : null);
        $order->setDiscount('0.00');

        $this->entityManager->persist($order);

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            if (!$product instanceof Product) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid cart item product'], 400);
            }

            $quantity = (int) $cartItem->getQuantity();

            $currentStock = (int) $product->getNumberInStock();
            $newStock = $currentStock - $quantity;
            if ($newStock < 0) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Insufficient stock for product: ' . $product->getName(),
                ], 400);
            }

            $price = (float) $product->getPrice();
            $discount = (float) $product->getDiscount();
            $taxRate = (float) $product->getTaxRate();

            $finalUnitPrice = $price * ($discount / 100.0);
            $subtotal = $finalUnitPrice * $quantity;
            $subtotalExclTax = $taxRate > 0 ? ($subtotal / (1 + $taxRate / 100.0)) : $subtotal;

            $orderItem = new OrderItem();
            $orderItem->setOrder($order);
            $orderItem->setProduct($product);
            $orderItem->setProductName((string) $product->getName());
            $orderItem->setSku((string) $product->getSku());
            $orderItem->setQuantity($quantity);
            $orderItem->setUnitPrice(number_format($finalUnitPrice, 2, '.', ''));
            $orderItem->setSubtotal(number_format($subtotalExclTax, 2, '.', ''));

            $this->entityManager->persist($orderItem);

            $product->setNumberInStock($newStock);
            $this->entityManager->persist($product);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'orderId' => $order->getId(),
        ]);
    }
}