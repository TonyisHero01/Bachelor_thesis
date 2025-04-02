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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class OrderController extends AbstractController
{
    public $shopInfo;
    private $entityManager;

    // 构造函数中注入 EntityManagerInterface，并加载 shopInfo
    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }

    #[Route('/order/confirm/{id}', name: 'confirm_order', methods: ['POST'])]
    public function confirmOrder(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $order = $entityManager->getRepository(Order::class)->find($id);
    
        if (!$order) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
        }
    
        $entityManager->persist($order);
        $entityManager->flush();
    
        return new JsonResponse(['success' => true]);
    }

    #[Route('/order/cancel/{id}', name: 'cancel_order', methods: ['POST'])]
    public function cancelOrder(EntityManagerInterface $entityManager, int $id): JsonResponse
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            return new JsonResponse(["success" => false, "message" => "Order not found"], 404);
        }

        $entityManager->remove($order);
        $entityManager->flush();

        return new JsonResponse(["success" => true]);
    }
    #[Route('/order/success/{id}', name: 'order_success')]
    public function orderSuccess(int $id, EntityManagerInterface $entityManager): Response
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        // 获取 shopInfo 并传递 hidePrices
        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([]);

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->render('eshop_order/order_success.html.twig', [
            'shopInfo' => $shopInfo,
            'show_sidebar' => false,
            'order' => $order,
            'categories' => $categories
        ]);
    }
    #[Route('/cart/clear_after_success', name: 'clear_cart_after_success', methods: ['POST'])]
    public function clearCartAfterSuccess(EntityManagerInterface $entityManager): JsonResponse
    {
        // 获取当前用户
        $customer = $this->getUser();
        
        if (!$customer) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 403);
        }

        // 查找用户的购物车商品
        $cartItems = $entityManager->getRepository(Cart::class)->findBy(['customer' => $customer]);

        if (!empty($cartItems)) {
            // 删除购物车中的所有商品
            foreach ($cartItems as $cartItem) {
                $entityManager->remove($cartItem);
            }
            $entityManager->flush(); // 提交删除操作
        }

        return new JsonResponse(['success' => true, 'message' => 'Cart has been cleared.']);
    }
    #[Route('/order/delivery-options/{id}', name: 'order_delivery_options', methods: ['GET'])]
    public function deliveryOptions(int $id, EntityManagerInterface $entityManager): Response
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order || $order->getCustomer() !== $this->getUser()) {
            throw $this->createNotFoundException('Order not found.');
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();

        return $this->render('eshop_order/order_delivery_options.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false,
            'order' => $order,
            'categories' => $categories
        ]);
    }

    #[Route('/order/select_delivery', name: 'order_select_delivery', methods: ['GET'])]
    public function selectDelivery(): Response
    {
        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->render('eshop_order/select_delivery.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false,
            'categories' => $categories
        ]);
    }

    #[Route('/order/submit_delivery/{id}', name: 'order_submit_delivery', methods: ['POST'])]
    public function submitDelivery(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $order = $entityManager->getRepository(Order::class)->find($id);
        if (!$order) {
            return new JsonResponse(["success" => false, "message" => "Order not found."], 404);
        }

        $data = json_decode($request->getContent(), true);
        $deliveryMethod = $data['deliveryMethod'] ?? null;
        $address = $data['address'] ?? "";
        $notes = $data['notes'] ?? "";  // 获取留言信息

        if (!$deliveryMethod) {
            return new JsonResponse(["success" => false, "message" => "You must select a delivery method."], 400);
        }

        // ✅ 保存配送方式、地址和留言
        $order->setDeliveryMethod($deliveryMethod);
        $order->setAddress($address);
        $order->setNotes($notes);

        $entityManager->persist($order);
        $entityManager->flush();

        return new JsonResponse(["success" => true]);
    }
    #[Route('/order/create', name: 'order_create', methods: ['POST'])]
    public function createOrder(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(["success" => false, "message" => "User not logged in"], 403);
        }

        $cartItems = $entityManager->getRepository(Cart::class)->findBy(['customer' => $user]);

        if (empty($cartItems)) {
            return new JsonResponse(["success" => false, "message" => "Your cart is empty"], 400);
        }

        $data = json_decode($request->getContent(), true);
        $deliveryMethod = $data['deliveryMethod'] ?? null;
        $address = $data['address'] ?? '';
        $orderNote = $data['notes'] ?? '';

        // 计算折扣后的总价格
        $totalPrice = 0;

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            $quantity = $cartItem->getQuantity();
            $price = $product->getPrice();
            $discount = $product->getDiscount(); // 100 = 无折扣，80 = 80%

            $finalUnitPrice = $price * ($discount / 100);
            $totalPrice += $finalUnitPrice * $quantity;
        }

        // 创建订单
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

        $entityManager->persist($order);

        // 创建订单项（OrderItem）
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

            $entityManager->persist($orderItem);
        }

        $entityManager->flush();

        return new JsonResponse([
            "success" => true,
            "orderId" => $order->getId()
        ]);
    }
}



    
