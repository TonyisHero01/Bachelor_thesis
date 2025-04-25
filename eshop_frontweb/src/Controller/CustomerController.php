<?php

// src/Controller/CustomerController.php
namespace App\Controller;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Security\CustomerLoginFormAuthenticator;
use App\Entity\ShopInfo;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ReturnRequest;
use App\Entity\Category;
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
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use Twig\Environment;

class CustomerController extends BaseController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        Environment $twig,
        LoggerInterface $logger,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack
    ) {
        parent::__construct($twig, $logger);
        $this->entityManager = $entityManager;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }

    #[Route('/customer/login', name: 'customer_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request, SessionInterface $session): Response
    {
        if ($this->getUser() instanceof Customer) {
            return $this->redirectToRoute('customer_home');
        }

        $targetPath = $request->headers->get('referer');
        if ($targetPath && !$session->get('_security.customer.target_path')) {
            $session->set('_security.customer.target_path', $targetPath);
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();

        return $this->renderLocalized('customer/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'categories' => $categories
        ], $request);
    }

    #[Route('/customer/home', name: 'customer_home')]
    #[IsGranted('ROLE_CUSTOMER')]
    public function home(Request $request): Response
    {
        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->renderLocalized('customer/home.html.twig', [
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'categories' => $categories
        ], $request);
    }

    #[Route('/customer/register', name: 'customer_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
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

            // ✅ 手动登录
            $token = new UsernamePasswordToken($customer, 'customer', $customer->getRoles());
            $this->tokenStorage->setToken($token);

            $session = $this->requestStack->getSession();
            $session->set('_security_customer', serialize($token));

            return $this->redirectToRoute('customer_home');
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->renderLocalized('customer/register.html.twig', [
            'registrationForm' => $form->createView(),
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'categories' => $categories
        ], $request);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/customer/wishlist', name: 'customer_wishlist')]
    public function showWishlist(Request $request, EntityManagerInterface $entityManager): Response
    {
        $customer = $this->getUser();

        if (!$customer instanceof Customer) {
            return $this->redirectToRoute('customer_login');
        }

        $wishlistProductIds = $customer->getWishlist();
        $products = [];
        if (!empty($wishlistProductIds)) {
            $products = $entityManager->getRepository(Product::class)->findBy(['id' => $wishlistProductIds]);
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->renderLocalized('customer/wishlist.html.twig', [
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'products' => $products,
            'categories' => $categories
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
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => '用户未登录'], 403);
        }

        $wishlist = $user->getWishlist();
        if (!in_array($id, $wishlist)) {
            return new JsonResponse(['success' => false, 'message' => '商品不在愿望单中'], 400);
        }

        $wishlist = array_diff($wishlist, [$id]);
        $user->setWishlist(array_values($wishlist));
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/cart', name: 'customer_cart')]
    public function showCart(EntityManagerInterface $entityManager, Request $request): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('customer_login');
        }

        $customer = $entityManager->getRepository(Customer::class)->find($this->getUser()->getId());
        $cartItems = $entityManager->getRepository(Cart::class)->findBy(
            ['customer' => $customer],
            ['addedAt' => 'ASC']
        );

        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([]);
        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();

        return $this->renderLocalized('eshop_cart/cart.html.twig', [
            'shopInfo' => $shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'cartItems' => $cartItems,
            'categories' => $categories
        ], $request);
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

    #[Route('/order-confirmation/{id}', name: 'order_confirmation', methods: ['GET'])]
    public function orderConfirmation(int $id, EntityManagerInterface $entityManager, Request $request): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('customer_login');
        }

        $order = $entityManager->getRepository(Order::class)->find($id);
        if (!$order || $order->getCustomer() !== $this->getUser()) {
            throw $this->createNotFoundException('Order not found.');
        }

        $orderItems = $entityManager->getRepository(OrderItem::class)->findBy(['order' => $order]);
        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([]);
        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();

        return $this->renderLocalized('eshop_order/order_confirmation.html.twig', [
            'shopInfo' => $shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'order' => $order,
            'orderItems' => $orderItems,
            'categories' => $categories
        ], $request);
    }

    #[Route('/order-confirmation2/{id}', name: 'order_confirmation2', methods: ['GET'])]
    public function orderConfirmation2(int $id, EntityManagerInterface $entityManager, Request $request): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('customer_login');
        }

        $order = $entityManager->getRepository(Order::class)->find($id);
        if (!$order || $order->getCustomer() !== $this->getUser()) {
            throw $this->createNotFoundException('Order not found.');
        }

        $orderItems = $entityManager->getRepository(OrderItem::class)->findBy(['order' => $order]);
        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([]);
        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();

        return $this->renderLocalized('eshop_order/order_confirmation2.html.twig', [
            'shopInfo' => $shopInfo,
            'locale' => $request->getLocale(),
            'show_sidebar' => false,
            'languages' => $this->getAvailableLanguages(),
            'order' => $order,
            'orderItems' => $orderItems,
            'categories' => $categories
        ]);
    }

    #[Route('/customer/orders', name: 'customer_orders')]
    public function customerOrders(EntityManagerInterface $entityManager, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $orders = $entityManager->getRepository(Order::class)->findBy(
            ['customer' => $user],
            ['orderCreatedAt' => 'DESC']
        );

        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([]);
        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();

        return $this->renderLocalized('eshop_order/customer_orders.html.twig', [
            'shopInfo' => $shopInfo,
            'locale' => $request->getLocale(),
            'show_sidebar' => false,
            'languages' => $this->getAvailableLanguages(),
            'orders' => $orders,
            'categories' => $categories
        ]);
    }

    #[Route('/customer/return-requests', name: 'customer_return_requests')]
    public function returnRequests(EntityManagerInterface $entityManager, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('customer_login');
        }

        $returnRequests = $entityManager->getRepository(ReturnRequest::class)
            ->findBy(['userEmail' => $user->getEmail()], ['requestDate' => 'DESC']);

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();

        return $this->renderLocalized('customer/return_requests.html.twig', [
            'returnRequests' => $returnRequests,
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'categories' => $categories
        ], $request);
    }
}
