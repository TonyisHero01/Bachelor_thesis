<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ReturnRequest;
use App\Entity\ShopInfo;
use App\Form\CustomerRegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

class CustomerController extends BaseController
{
    private ShopInfo $shopInfo;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        Environment $twig,
        LoggerInterface $logger,
        ManagerRegistry $doctrine,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
        parent::__construct($twig, $logger, $doctrine);

        $this->shopInfo = $entityManager
            ->getRepository(ShopInfo::class)
            ->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Displays the customer login page.
     * Redirects authenticated customers to the customer home page.
     * Stores the referrer as a target path when available.
     */
    #[Route('/customer/login', name: 'customer_login', methods: ['GET', 'POST'])]
    public function login(
        AuthenticationUtils $authenticationUtils,
        Request $request,
        SessionInterface $session,
    ): Response {
        if ($this->getUser() instanceof Customer) {
            return $this->redirectToRoute('customer_home');
        }

        $targetPath = (string) ($request->headers->get('referer') ?? '');
        if ($targetPath !== '' && !$session->has('_security.customer.target_path')) {
            $session->set('_security.customer.target_path', $targetPath);
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'customer/login.html.twig',
            [
                'last_username' => $lastUsername,
                'error' => $error,
                'shopInfo' => $this->shopInfo,
                'show_sidebar' => false,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Displays the customer home page.
     */
    #[Route('/customer/home', name: 'customer_home', methods: ['GET'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function home(Request $request): Response
    {
        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'customer/home.html.twig',
            [
                'shopInfo' => $this->shopInfo,
                'show_sidebar' => false,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Handles customer registration and logs the customer in on success.
     */
    #[Route('/customer/register', name: 'customer_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        SessionInterface $session,
    ): Response {
        $customer = new Customer();
        $form = $this->createForm(CustomerRegistrationFormType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();

            $customer->setPasswordHash($passwordHasher->hashPassword($customer, $plainPassword));
            $customer->setIsVerified(false);

            $this->entityManager->persist($customer);
            $this->entityManager->flush();

            $token = new UsernamePasswordToken($customer, 'customer', $customer->getRoles());
            $this->tokenStorage->setToken($token);
            $session->set('_security_customer', serialize($token));

            return $this->redirectToRoute('customer_home');
        }

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'customer/register.html.twig',
            [
                'registrationForm' => $form->createView(),
                'shopInfo' => $this->shopInfo,
                'show_sidebar' => false,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Logout route handled by Symfony security firewall.
     */
    #[Route(path: '/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method is intercepted by the logout firewall configuration.');
    }

    /**
     * Displays the wishlist for the currently logged-in customer.
     */
    #[Route('/customer/wishlist', name: 'customer_wishlist', methods: ['GET'])]
    public function showWishlist(Request $request): Response
    {
        $customer = $this->getUser();

        if (!$customer instanceof Customer) {
            return $this->redirectToRoute('customer_login');
        }

        $wishlistProductIds = $customer->getWishlist();
        $products = [];

        if ($wishlistProductIds !== []) {
            $products = $this->entityManager
                ->getRepository(Product::class)
                ->findBy(['id' => $wishlistProductIds]);
        }

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'customer/wishlist.html.twig',
            [
                'shopInfo' => $this->shopInfo,
                'show_sidebar' => false,
                'products' => $products,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Checks whether a product is in the authenticated customer's wishlist.
     */
    #[Route('/wishlist/check/{productId}', name: 'check_wishlist', methods: ['GET'])]
    public function checkWishlist(int $productId): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return new JsonResponse(['inWishlist' => false], 403);
        }

        return new JsonResponse(['inWishlist' => in_array($productId, $user->getWishlist(), true)]);
    }

    /**
     * Toggles a product in the authenticated customer's wishlist.
     */
    #[Route(path: '/add_to_wishlist', name: 'wishlist_adding', methods: ['POST'])]
    public function addToWishlist(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return new JsonResponse(['status' => 'error', 'message' => 'User not authenticated'], 403);
        }

        $input = json_decode((string) $request->getContent(), true);
        if (!is_array($input)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid JSON body'], 400);
        }

        $productId = $input['product_id'] ?? null;
        if (!is_numeric($productId)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Product ID not provided'], 400);
        }

        $pid = (int) $productId;

        $wishlist = $user->getWishlist();

        if (in_array($pid, $wishlist, true)) {
            $wishlist = array_values(array_filter(
                $wishlist,
                static fn (int $id): bool => $id !== $pid,
            ));
        } else {
            $wishlist[] = $pid;
            $wishlist = array_values(array_unique($wishlist));
        }

        $user->setWishlist($wishlist);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'success', 'wishlist' => $wishlist]);
    }

    /**
     * Removes a product from the authenticated customer's wishlist.
     */
    #[Route('/wishlist/remove/{id}', name: 'remove_from_wishlist', methods: ['POST'])]
    public function removeFromWishlist(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return new JsonResponse(['success' => false, 'message' => 'User not logged in'], 403);
        }

        $wishlist = $user->getWishlist();

        if (!in_array($id, $wishlist, true)) {
            return new JsonResponse(['success' => false, 'message' => 'The product is not in the wishlist.'], 400);
        }

        $user->setWishlist(array_values(array_diff($wishlist, [$id])));
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Displays the authenticated customer's cart.
     */
    #[Route('/cart', name: 'customer_cart', methods: ['GET'])]
    public function showCart(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return $this->redirectToRoute('customer_login');
        }

        $customer = $this->entityManager->getRepository(Customer::class)->find($user->getId());
        $cartItems = $this->entityManager->getRepository(Cart::class)->findBy(
            ['customer' => $customer],
            ['addedAt' => 'ASC'],
        );

        $shopInfo = $this->entityManager->getRepository(ShopInfo::class)->findOneBy([]);
        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop_cart/cart.html.twig',
            [
                'shopInfo' => $shopInfo,
                'show_sidebar' => false,
                'cartItems' => $cartItems,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Updates the quantity of a cart item owned by the authenticated customer.
     */
    #[Route('/cart/update/{id}', name: 'update_cart', methods: ['POST'])]
    public function updateCart(int $id, Request $request): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body'], 400);
        }

        $newQuantity = (int) ($data['quantity'] ?? 1);
        if ($newQuantity < 1) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid quantity'], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof Customer) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 403);
        }

        $customer = $this->entityManager->getRepository(Customer::class)->find($user->getId());
        $cartItem = $this->entityManager->getRepository(Cart::class)->find($id);

        if (!$cartItem || $cartItem->getCustomer() !== $customer) {
            return new JsonResponse(['success' => false, 'message' => 'Cart item not found'], 404);
        }

        $cartItem->setQuantity($newQuantity);
        $this->entityManager->flush();

        $cartTotalQuantity = $this->entityManager->createQueryBuilder()
            ->select('SUM(c.quantity)')
            ->from(Cart::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'success' => true,
            'message' => 'Cart updated successfully',
            'cartCount' => $cartTotalQuantity ? (int) $cartTotalQuantity : 0,
        ]);
    }

    /**
     * Removes a cart item owned by the authenticated customer.
     */
    #[Route('/cart/remove/{id}', name: 'remove_from_cart', methods: ['POST'])]
    public function removeFromCart(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Customer) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 403);
        }

        $customer = $this->entityManager->getRepository(Customer::class)->find($user->getId());
        $cartItem = $this->entityManager->getRepository(Cart::class)->find($id);

        if (!$cartItem || $cartItem->getCustomer() !== $customer) {
            return new JsonResponse(['success' => false, 'message' => 'Cart item not found'], 404);
        }

        $this->entityManager->remove($cartItem);
        $this->entityManager->flush();

        $cartTotalQuantity = $this->entityManager->createQueryBuilder()
            ->select('SUM(c.quantity)')
            ->from(Cart::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'success' => true,
            'message' => 'Item removed from cart',
            'cartCount' => $cartTotalQuantity ? (int) $cartTotalQuantity : 0,
        ]);
    }

    /**
     * Displays an order confirmation page for an order owned by the authenticated customer.
     */
    #[Route('/order-confirmation/{id}', name: 'order_confirmation', methods: ['GET'])]
    public function orderConfirmation(int $id, Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return $this->redirectToRoute('customer_login');
        }

        $order = $this->entityManager->getRepository(Order::class)->find($id);
        if (!$order || $order->getCustomer() !== $user) {
            throw $this->createNotFoundException('Order not found.');
        }

        $orderItems = $this->entityManager->getRepository(OrderItem::class)->findBy(['order' => $order]);
        $shopInfo = $this->entityManager->getRepository(ShopInfo::class)->findOneBy([]);

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop_order/order_confirmation.html.twig',
            [
                'shopInfo' => $shopInfo,
                'show_sidebar' => false,
                'order' => $order,
                'orderItems' => $orderItems,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Displays the alternative order confirmation page for an order owned by the authenticated customer.
     */
    #[Route('/order-confirmation2/{id}', name: 'order_confirmation2', methods: ['GET'])]
    public function orderConfirmation2(int $id, Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return $this->redirectToRoute('customer_login');
        }

        $order = $this->entityManager->getRepository(Order::class)->find($id);
        if (!$order || $order->getCustomer() !== $user) {
            throw $this->createNotFoundException('Order not found.');
        }

        $orderItems = $this->entityManager->getRepository(OrderItem::class)->findBy(['order' => $order]);
        $shopInfo = $this->entityManager->getRepository(ShopInfo::class)->findOneBy([]);

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop_order/order_confirmation2.html.twig',
            [
                'shopInfo' => $shopInfo,
                'show_sidebar' => false,
                'order' => $order,
                'orderItems' => $orderItems,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Displays all orders placed by the authenticated customer.
     */
    #[Route('/customer/orders', name: 'customer_orders', methods: ['GET'])]
    public function customerOrders(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return $this->redirectToRoute('customer_login');
        }

        $orders = $this->entityManager->getRepository(Order::class)->findBy(
            ['customer' => $user],
            ['orderCreatedAt' => 'DESC'],
        );

        $shopInfo = $this->entityManager->getRepository(ShopInfo::class)->findOneBy([]);

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop_order/customer_orders.html.twig',
            [
                'shopInfo' => $shopInfo,
                'show_sidebar' => false,
                'orders' => $orders,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Displays all return requests created by the authenticated customer (filtered by email).
     */
    #[Route('/customer/return-requests', name: 'customer_return_requests', methods: ['GET'])]
    public function returnRequests(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return $this->redirectToRoute('customer_login');
        }

        $returnRequests = $this->entityManager->getRepository(ReturnRequest::class)->findBy(
            ['userEmail' => $user->getEmail()],
            ['requestDate' => 'DESC'],
        );

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'customer/return_requests.html.twig',
            [
                'returnRequests' => $returnRequests,
                'shopInfo' => $this->shopInfo,
                'show_sidebar' => false,
                'categories' => $categories,
            ],
            $request,
        );
    }
}