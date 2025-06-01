<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Order;
use App\Entity\Cart;
use App\Entity\OrderItem;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class OrderController extends BaseController
{
    private $shopInfo;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, Security $security, Environment $twig, LoggerInterface $logger)
    {
        parent::__construct($twig, $logger);
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }

    #[Route('/order/confirm/{id}', name: 'confirm_order', methods: ['POST'])]
    public function confirmOrder(int $id): JsonResponse
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/order/cancel/{id}', name: 'cancel_order', methods: ['POST'])]
    public function cancelOrder(int $id): JsonResponse
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            return new JsonResponse(["success" => false, "message" => "Order not found"], 404);
        }

        $this->entityManager->remove($order);
        $this->entityManager->flush();

        return new JsonResponse(["success" => true]);
    }

    #[Route('/order/success/{id}', name: 'order_success')]
    public function orderSuccess(int $id, Request $request): Response
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->renderLocalized('eshop_order/order_success.html.twig', [
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'order' => $order,
            'categories' => $categories
        ], $request);
    }

    #[Route('/cart/clear_after_success', name: 'clear_cart_after_success', methods: ['POST'])]
    public function clearCartAfterSuccess(): JsonResponse
    {
        $customer = $this->getUser();

        if (!$customer) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 403);
        }

        $cartItems = $this->entityManager->getRepository(Cart::class)->findBy(['customer' => $customer]);

        foreach ($cartItems as $cartItem) {
            $this->entityManager->remove($cartItem);
        }
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Cart has been cleared.']);
    }

    #[Route('/order/delivery-options/{id}', name: 'order_delivery_options', methods: ['GET'])]
    public function deliveryOptions(int $id, Request $request): Response
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (!$order || $order->getCustomer() !== $this->getUser()) {
            throw $this->createNotFoundException('Order not found.');
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();

        return $this->renderLocalized('eshop_order/order_delivery_options.html.twig', [
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'order' => $order,
            'categories' => $categories
        ], $request);
    }

    #[Route('/order/select_delivery', name: 'order_select_delivery', methods: ['GET'])]
    public function selectDelivery(Request $request): Response
    {
        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->renderLocalized('eshop_order/select_delivery.html.twig', [
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'categories' => $categories
        ], $request);
    }

    #[Route('/order/submit_delivery/{id}', name: 'order_submit_delivery', methods: ['POST'])]
    public function submitDelivery(int $id, Request $request): JsonResponse
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);
        if (!$order) {
            return new JsonResponse(["success" => false, "message" => "Order not found."], 404);
        }

        $data = json_decode($request->getContent(), true);
        $deliveryMethod = $data['deliveryMethod'] ?? null;
        $address = $data['address'] ?? "";
        $notes = $data['notes'] ?? "";

        if (!$deliveryMethod) {
            return new JsonResponse(["success" => false, "message" => "You must select a delivery method."], 400);
        }

        $order->setDeliveryMethod($deliveryMethod);
        $order->setAddress($address);
        $order->setNotes($notes);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return new JsonResponse(["success" => true]);
    }

    #[Route('/order/create', name: 'order_create', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(["success" => false, "message" => "User not logged in"], 403);
        }

        $cartItems = $this->entityManager->getRepository(Cart::class)->findBy(['customer' => $user]);

        if (empty($cartItems)) {
            return new JsonResponse(["success" => false, "message" => "Your cart is empty"], 400);
        }

        $data = json_decode($request->getContent(), true);
        $deliveryMethod = $data['deliveryMethod'] ?? null;
        $address = $data['address'] ?? '';
        $orderNote = $data['notes'] ?? '';

        $totalPrice = 0;

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            $quantity = $cartItem->getQuantity();
            $price = $product->getPrice();
            $discount = $product->getDiscount();
            $finalUnitPrice = $price * ($discount / 100);
            $totalPrice += $finalUnitPrice * $quantity;
        }

        $order = new Order();
        $order->setCustomer($user);
        $order->setTotalPrice(round($totalPrice, 2));
        $order->setOrderCreatedAt(new \DateTime());
        $order->setIsCompleted(false);
        $order->setPaymentStatus("PENDING");
        $order->setDeliveryStatus("PENDING");
        $order->setDeliveryMethod($deliveryMethod);
        $order->setAddress($address);
        $order->setNotes($orderNote);

        $this->entityManager->persist($order);

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            $quantity = $cartItem->getQuantity();
            $price = $product->getPrice();
            $discount = $product->getDiscount();
            $taxRate = $product->getTaxRate();
        
            $finalUnitPrice = $price * ($discount / 100);
            $subtotal = $finalUnitPrice * $quantity;
            $subtotalExclTax = $subtotal / (1 + $taxRate / 100);
        
            $orderItem = new OrderItem();
            $orderItem->setOrder($order);
            $orderItem->setProduct($product);
            $orderItem->setProductName($product->getName());
            $orderItem->setSku($product->getSku());
            $orderItem->setQuantity($quantity);
            $orderItem->setUnitPrice(round($finalUnitPrice, 2));
            $orderItem->setSubtotal(round($subtotalExclTax, 2));
        
            $this->entityManager->persist($orderItem);

            $currentStock = $product->getNumberInStock();
            $newStock = $currentStock - $quantity;
        
            if ($newStock < 0) {
                return new JsonResponse([
                    "success" => false,
                    "message" => "Insufficient stock for product: " . $product->getName()
                ], 400);
            }
        
            $product->setNumberInStock($newStock);
            $this->entityManager->persist($product);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            "success" => true,
            "orderId" => $order->getId()
        ]);
    }
}
