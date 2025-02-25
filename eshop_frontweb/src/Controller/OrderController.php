<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Order;
use App\Entity\Cart;
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

        return $this->render('eshop_order/order_success.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false,
            'order' => $order
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

        return $this->render('eshop_order/order_delivery_options.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false,
            'order' => $order
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

        if (!$deliveryMethod) {
            return new JsonResponse(["success" => false, "message" => "You must select a delivery method."], 400);
        }

        if ($deliveryMethod === "pickup") {
            $order->setPaymentMethod("Pick Up");
            $order->setAddress("");  // 取货方式时，地址设为空字符串
        } elseif ($deliveryMethod === "delivery") {
            if (!$address) {
                return new JsonResponse(["success" => false, "message" => "Delivery address is required."], 400);
            }
            $order->setPaymentMethod("Delivery");
            $order->setAddress($address);
        } else {
            return new JsonResponse(["success" => false, "message" => "Invalid delivery method."], 400);
        }

        $entityManager->persist($order);
        $entityManager->flush();

        return new JsonResponse(["success" => true]);
    }
}



    
