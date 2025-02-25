<?php

// src/Controller/CustomerController.php
namespace App\Controller;

use Symfony\Component\Security\Core\Security;
use App\Entity\ShopInfo;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Form\CustomerRegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CustomerController extends AbstractController
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }
    #[Route('/customer/login', name: 'customer_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request, SessionInterface $session): Response
    {
        if ($this->getUser() instanceof App\Entity\Customer) {
            return $this->redirectToRoute('customer_home');
        }

        $targetPath = $request->headers->get('referer');
        if ($targetPath && !$session->get('_security.customer.target_path')) {
            $session->set('_security.customer.target_path', $targetPath);
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('customer/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false
        ]);
    }

    #[Route('/customer/home', name: 'customer_home')]
    #[IsGranted('ROLE_CUSTOMER')]
    public function home(): Response
    {
        return $this->render('customer/home.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false
        ]);
    }

    #[Route('/customer/register', name: 'customer_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerRegistrationFormType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customer->setPasswordHash(
                $passwordHasher->hashPassword(
                    $customer,
                    $form->get('password_hash')->getData()
                )
            );

            $entityManager->persist($customer);
            $entityManager->flush();

            return $this->redirectToRoute('customer_home');
        }

        return $this->render('customer/register.html.twig', [
            'registrationForm' => $form->createView(),
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false
        ]);
    }
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    
    #[Route('/customer/wishlist', name: 'customer_wishlist')]
    public function showWishlist(EntityManagerInterface $entityManager): Response
    {
        // 获取当前用户
        $customer = $this->getUser();

        // 确保用户已登录且为 Customer 类型
        if (!$customer instanceof Customer) {
            return $this->redirectToRoute('customer_login');
        }

        // 获取 wishlist 中的产品 ID
        $wishlistProductIds = $customer->getWishlist();

        // 使用产品 ID 查询产品
        $products = [];
        if (!empty($wishlistProductIds)) {
            $products = $entityManager->getRepository(Product::class)->findBy(['id' => $wishlistProductIds]);
        }

        return $this->render('customer/wishlist.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false,
            'products' => $products // 传递产品给模板
        ]);
    }

    #[Route('/wishlist/check/{productId}', name: 'check_wishlist', methods: ['GET'])]
    public function checkWishlist(int $productId, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user || !$user instanceof Customer) {
            return new JsonResponse(['inWishlist' => false], 403);
        }

        // 获取用户的 wishlist
        $wishlist = $user->getWishlist();

        return new JsonResponse(['inWishlist' => in_array($productId, $wishlist)]);
    }

    #[Route(path: '/add_to_wishlist', name: 'wishlist_adding', methods: ['POST'])]
    public function addToWishlist(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return new JsonResponse(['status' => 'error', 'message' => 'User not authenticated'], 403);
        }

        $input = json_decode($request->getContent(), true);
        $productId = $input['product_id'] ?? null;

        if (!$productId) {
            return new JsonResponse(['status' => 'error', 'message' => 'Product ID not provided'], 400);
        }

        // 获取用户 wishlist
        $wishlist = $user->getWishlist();

        // **如果已经在 wishlist 里，则移除**
        if (in_array($productId, $wishlist)) {
            $wishlist = array_filter($wishlist, fn($id) => $id != $productId); // 关键：移除 ID
        } else {
            $wishlist[] = (int) $productId; // 添加 ID
        }

        $user->setWishlist(array_values($wishlist)); // 重新设置
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success', 'wishlist' => $wishlist]); // 关键：返回最新的 wishlist
    }

    #[Route('/wishlist/remove/{id}', name: 'remove_from_wishlist', methods: ['POST'])]
    public function removeFromWishlist(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser(); // ✅ 直接获取用户
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => '用户未登录'], 403);
        }

        $wishlist = $user->getWishlist();
        if (!in_array($id, $wishlist)) {
            return new JsonResponse(['success' => false, 'message' => '商品不在愿望单中'], 400);
        }

        $wishlist = array_diff($wishlist, [$id]);
        $user->setWishlist(array_values($wishlist)); // 重新索引数组
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/cart', name: 'customer_cart')]
    public function showCart(EntityManagerInterface $entityManager): Response
    {
        // 确保用户已登录
        if (!$this->getUser()) {
            return $this->redirectToRoute('customer_login');
        }

        // 获取当前用户
        $customer = $entityManager->getRepository(Customer::class)->find($this->getUser()->getId());

        // 查询购物车内容，并按 `added_at` 时间升序排列
        $cartItems = $entityManager->getRepository(Cart::class)->findBy(
            ['customer' => $customer],
            ['addedAt' => 'ASC'] // 这里按照 `added_at` 排序
        );

        return $this->render('eshop_cart/cart.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false,
            'cartItems' => $cartItems,
        ]);
    }

    #[Route('/cart/update/{id}', name: 'update_cart', methods: ['POST'])]
    public function updateCart(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newQuantity = (int) ($data['quantity'] ?? 1);

        if ($newQuantity < 1) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid quantity'], 400);
        }

        if (!$this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 403);
        }

        $customer = $entityManager->getRepository(Customer::class)->find($this->getUser()->getId());
        $cartItem = $entityManager->getRepository(Cart::class)->find($id);

        if (!$cartItem || $cartItem->getCustomer() !== $customer) {
            return new JsonResponse(['success' => false, 'message' => 'Cart item not found'], 404);
        }

        $cartItem->setQuantity($newQuantity);
        $entityManager->flush();

        // 获取购物车总数
        $cartTotalQuantity = $entityManager->createQueryBuilder()
            ->select('SUM(c.quantity)')
            ->from(Cart::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'success' => true,
            'message' => 'Cart updated successfully',
            'cartCount' => $cartTotalQuantity ?? 0
        ]);
    }

    #[Route('/cart/remove/{id}', name: 'remove_from_cart', methods: ['POST'])]
    public function removeFromCart(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 403);
        }

        $customer = $entityManager->getRepository(Customer::class)->find($this->getUser()->getId());
        $cartItem = $entityManager->getRepository(Cart::class)->find($id);

        if (!$cartItem || $cartItem->getCustomer() !== $customer) {
            return new JsonResponse(['success' => false, 'message' => 'Cart item not found'], 404);
        }

        $entityManager->remove($cartItem);
        $entityManager->flush();

        // 获取购物车总数
        $cartTotalQuantity = $entityManager->createQueryBuilder()
            ->select('SUM(c.quantity)')
            ->from(Cart::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'success' => true,
            'message' => 'Item removed from cart',
            'cartCount' => $cartTotalQuantity ?? 0
        ]);
    }

    #[Route('/cart/checkout', name: 'cart_checkout', methods: ['POST'])]
    public function checkout(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(["success" => false, "message" => "User not logged in"], 403);
        }

        // 获取当前用户的购物车商品
        $cartItems = $entityManager->getRepository(Cart::class)->findBy(['customer' => $user]);

        if (empty($cartItems)) {
            return new JsonResponse(["success" => false, "message" => "Your cart is empty"], 400);
        }

        // 计算总价格
        $totalPrice = 0;
        foreach ($cartItems as $cartItem) {
            $totalPrice += $cartItem->getQuantity() * $cartItem->getProduct()->getPrice();
        }

        // 创建订单
        $order = new Order();
        $order->setCustomer($user);
        $order->setTotalPrice($totalPrice);
        $order->setOrderCreatedAt(new \DateTime());
        $order->setIsCompleted(false); // 订单未完成
        $order->setPaymentStatus("PENDING");
        $order->setDeliveryStatus("PENDING");

        $entityManager->persist($order);
        $entityManager->flush();

        // 订单项 (OrderItem) 关联购物车里的商品
        foreach ($cartItems as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setOrder($order);
            $orderItem->setProduct($cartItem->getProduct());
            $orderItem->setProductName($cartItem->getProduct()->getName());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setUnitPrice($cartItem->getProduct()->getPrice());
            $orderItem->setSubtotal(($cartItem->getProduct()->getPrice() / (1 + $cartItem->getProduct()->getTaxRate() / 100)) * $cartItem->getQuantity());

            $entityManager->persist($orderItem);
        }

        $entityManager->flush();

        return new JsonResponse([
            "success" => true,
            "order_id" => $order->getId()
        ]);
    }

    #[Route('/order-confirmation/{id}', name: 'order_confirmation', methods: ['GET'])]
    public function orderConfirmation(int $id, EntityManagerInterface $entityManager): Response
    {
        // 确保用户已登录
        if (!$this->getUser()) {
            return $this->redirectToRoute('customer_login');
        }

        // 查询订单
        $order = $entityManager->getRepository(Order::class)->find($id);

        // 确保订单存在，并且属于当前用户
        if (!$order || $order->getCustomer() !== $this->getUser()) {
            throw $this->createNotFoundException('Order not found.');
        }

        // 获取订单项
        $orderItems = $entityManager->getRepository(OrderItem::class)->findBy(['order' => $order]);

        return $this->render('eshop_order/order_confirmation.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false,
            'order' => $order,
            'orderItems' => $orderItems
        ]);
    }

    #[Route('/customer/orders', name: 'customer_orders')]
    public function customerOrders(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // 获取所有订单（不区分是否完成）
        $orders = $entityManager->getRepository(Order::class)->findBy(
            ['customer' => $user],
            ['orderCreatedAt' => 'DESC']
        );

        return $this->render('eshop_order/customer_orders.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false,
            'orders' => $orders
        ]);
    }

}