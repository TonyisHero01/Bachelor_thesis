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
    /**
     * Confirms an existing order.
     * Currently a placeholder – assumes more business logic will be added later.
     *
     * @param int $id Order ID
     * @return JsonResponse Confirmation result
     */
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
    /**
     * Cancels and deletes the order with the given ID.
     *
     * @param int $id Order ID
     * @return JsonResponse Deletion result
     */
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
    /**
     * Displays the success page after an order is completed.
     *
     * @param int $id Order ID
     * @param Request $request HTTP request
     * @return Response Rendered success page
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if order not found
     */
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
    /**
     * Clears the customer's cart after a successful order.
     * Usually triggered via AJAX from the success page.
     *
     * @return JsonResponse Operation result
     */
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
    /**
     * Displays available delivery options for a given order.
     * Ensures the order belongs to the current logged-in user.
     *
     * @param int $id Order ID
     * @param Request $request HTTP request
     * @return Response Rendered delivery options page
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if order not found or access denied
     */
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
    /**
     * Displays delivery method selection screen.
     * Typically part of the checkout process.
     *
     * @param Request $request HTTP request
     * @return Response Rendered delivery selection page
     */
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
    /**
     * Submits the selected delivery method, address, and optional notes.
     *
     * @param int $id Order ID
     * @param Request $request JSON body: {"deliveryMethod": string, "address": string, "notes": string}
     * @return JsonResponse Success or validation error
     */
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
    /**
     * Creates a new order from the user's cart.
     * Validates stock, applies discounts, calculates tax,
     * saves order and associated order items.
     *
     * @param Request $request JSON body: {"deliveryMethod": string, "address": string, "notes": string}
     * @return JsonResponse Result with order ID or error message
     */
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
        $order->setDiscount(0.0);

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
