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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\CustomerProductViewLog;
use App\Entity\CustomerSearchLog;
use App\Service\RecommendationEventLogger;
use App\Entity\SearchRelevanceConfig;

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
    public function showCart(
        Request $request,
        HttpClientInterface $httpClient,
        RecommendationEventLogger $recommendationEventLogger
    ): Response
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

        $searchConfig = $this->entityManager
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(['active' => true], ['id' => 'DESC']);

        $recommendationsEnabled = $searchConfig?->isRecommendationEnabled() ?? true;
        $recommendationLoggingEnabled = $searchConfig?->isRecommendationLoggingEnabled() ?? true;

        $recommendedProducts = [];

        if ($recommendationsEnabled) {
            $recommendedProducts = $this->buildCartRecommendations(
                $request,
                $cartItems,
                $httpClient,
                5
            );

            if ($recommendationLoggingEnabled) {
                $recommendationEventLogger->logManyImpressions(
                    pageType: 'cart',
                    sourceSku: null,
                    recommendations: $recommendedProducts,
                    algorithm: $cartItems !== [] ? 'session_based' : 'history_based'
                );
            }
        }

        return $this->renderLocalized(

            'eshop_cart/cart.html.twig',
            [
                'shopInfo' => $shopInfo,
                'show_sidebar' => false,
                'cartItems' => $cartItems,
                'categories' => $categories,
                'recommendedProducts' => $recommendedProducts,
                'recommendations_enabled' => $recommendationsEnabled,
                'recommendation_logging_enabled' => $recommendationLoggingEnabled,
                'BMS_URL' => $this->getParameter('BMS_URL'),
                'locale' => (string) $request->getLocale(),
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

    private function buildCartRecommendations(
        Request $request,
        array $cartItems,
        HttpClientInterface $httpClient,
        int $limit = 5
    ): array {
        $cartSkus = [];

        foreach ($cartItems as $cartItem) {
            if (!$cartItem instanceof Cart) {
                continue;
            }

            $product = $cartItem->getProduct();

            if (!$product instanceof Product) {
                continue;
            }

            $sku = trim((string) $product->getSku());

            if ($sku !== '' && !in_array($sku, $cartSkus, true)) {
                $cartSkus[] = $sku;
            }
        }

        if ($cartSkus !== []) {
            $recommendedSkus = $this->fetchSessionRecommendationSkus(
                currentSku: null,
                viewedSkus: [],
                cartSkus: $cartSkus,
                httpClient: $httpClient,
                limit: $limit * 3
            );

            return $this->findLatestVisibleProductsBySkus(
                $recommendedSkus,
                $limit,
                $cartSkus
            );
        }

        $fallbackSkus = $this->buildFallbackRecommendationSkus(
            $request,
            $httpClient,
            $limit * 3
        );

        $products = $this->findLatestVisibleProductsBySkus(
            $fallbackSkus,
            $limit,
            []
        );

        if ($products !== []) {
            return $products;
        }

        return $this->findFallbackProductsBySalesAndUpdate($limit);
    }

    private function fetchSessionRecommendationSkus(
        ?string $currentSku,
        array $viewedSkus,
        array $cartSkus,
        HttpClientInterface $httpClient,
        int $limit = 15
    ): array {
        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

            $response = $httpClient->request(
                'POST',
                $baseUrl . '/recommend/session',
                [
                    'json' => [
                        'current_sku' => $currentSku,
                        'viewed_skus' => array_values($viewedSkus),
                        'cart_skus' => array_values($cartSkus),
                        'limit' => $limit,
                    ],
                    'timeout' => 5,
                ]
            );

            if ($response->getStatusCode() >= 400) {
                return [];
            }

            $data = $response->toArray(false);
            $skus = [];

            foreach (($data['results'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $sku = trim((string) ($row['product_sku'] ?? ''));

                if ($sku !== '' && !in_array($sku, $skus, true)) {
                    $skus[] = $sku;
                }
            }

            return $skus;

        } catch (\Throwable $e) {
            $this->logger->warning(
                '[CartRecommendation] Failed to fetch session recommendations',
                [
                    'message' => $e->getMessage(),
                    'cartSkus' => $cartSkus,
                    'viewedSkus' => $viewedSkus,
                ]
            );

            return [];
        }
    }

    private function buildFallbackRecommendationSkus(
        Request $request,
        HttpClientInterface $httpClient,
        int $limit = 15
    ): array {
        $sourceSkus = [];

        foreach ($this->getRecentViewedSkus($request, 10) as $sku) {
            $sourceSkus[] = $sku;
        }

        foreach ($this->getWishlistSkus() as $sku) {
            $sourceSkus[] = $sku;
        }

        foreach ($this->getRecentOrderSkus(20) as $sku) {
            $sourceSkus[] = $sku;
        }

        foreach ($this->getRecentSearchBasedSkus($request, $httpClient, 10) as $sku) {
            $sourceSkus[] = $sku;
        }

        $sourceSkus = array_values(array_unique(array_filter($sourceSkus)));

        if ($sourceSkus === []) {
            return [];
        }

        $scores = [];

        foreach (array_slice($sourceSkus, 0, 15) as $sourceSku) {
            $this->addRecommendedSkuScoresFromSearchService(
                $scores,
                $sourceSku,
                1.0,
                $httpClient
            );
        }

        foreach ($sourceSkus as $sourceSku) {
            unset($scores[$sourceSku]);
        }

        arsort($scores);

        return array_slice(array_keys($scores), 0, $limit);
    }

    private function getRecentViewedSkus(
        Request $request,
        int $limit = 10
    ): array {
        $user = $this->getUser();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('v.sku')
            ->from(CustomerProductViewLog::class, 'v')
            ->orderBy('v.viewedAt', 'DESC')
            ->setMaxResults($limit);

        if ($user instanceof Customer) {
            $qb->where('v.customer = :customer')
                ->setParameter('customer', $user);
        } else {
            $qb->where('v.sessionId = :sessionId')
                ->setParameter('sessionId', $request->getSession()->getId());
        }

        $rows = $qb->getQuery()->getArrayResult();
        $skus = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku !== '' && !in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }

        return $skus;
    }

    private function getWishlistSkus(): array
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return [];
        }

        $wishlistIds = $user->getWishlist();

        if ($wishlistIds === []) {
            return [];
        }

        $products = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['id' => $wishlistIds]);

        $skus = [];

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $sku = trim((string) $product->getSku());

            if ($sku !== '' && !in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }

        return $skus;
    }

    private function getRecentOrderSkus(int $limit = 20): array
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('oi.sku AS sku')
            ->from(OrderItem::class, 'oi')
            ->join('oi.order', 'o')
            ->where('o.customer = :customer')
            ->setParameter('customer', $user)
            ->orderBy('o.orderCreatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $skus = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku !== '' && !in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }

        return $skus;
    }

    private function getRecentSearchBasedSkus(
        Request $request,
        HttpClientInterface $httpClient,
        int $limit = 10
    ): array {
        $user = $this->getUser();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('l.query')
            ->from(CustomerSearchLog::class, 'l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(5);

        if ($user instanceof Customer) {
            $qb->where('l.customer = :customer')
                ->setParameter('customer', $user);
        } else {
            $qb->where('l.sessionId = :sessionId')
                ->setParameter('sessionId', $request->getSession()->getId());
        }

        $logs = $qb->getQuery()->getArrayResult();

        $skus = [];

        foreach ($logs as $log) {
            $query = trim((string) ($log['query'] ?? ''));

            if ($query === '') {
                continue;
            }

            try {
                $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');
                $searchMethod = $this->getActiveSearchMethod();
                $endpoint = $this->getSearchEndpoint($searchMethod);

                $response = $httpClient->request(
                    'POST',
                    $baseUrl . $endpoint,
                    [
                        'json' => [
                            'query' => $query,
                            'limit' => 5,
                        ],
                        'timeout' => 5,
                    ]
                );

                if ($response->getStatusCode() >= 400) {
                    continue;
                }

                $data = $response->toArray(false);

                foreach (($data['results'] ?? []) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $sku = $this->getSkuFromSearchRow($row, $searchMethod);

                    if ($sku !== '' && !in_array($sku, $skus, true)) {
                        $skus[] = $sku;
                    }

                    if (count($skus) >= $limit) {
                        return $skus;
                    }
                }

            } catch (\Throwable) {
                continue;
            }
        }

        return $skus;
    }

    private function addRecommendedSkuScoresFromSearchService(
        array &$scores,
        string $sku,
        float $weight,
        HttpClientInterface $httpClient
    ): void {
        $sku = trim($sku);

        if ($sku === '') {
            return;
        }

        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

            $response = $httpClient->request(
                'GET',
                $baseUrl . '/recommend/' . rawurlencode($sku),
                [
                    'query' => ['limit' => 10],
                    'timeout' => 5,
                ]
            );

            if ($response->getStatusCode() >= 400) {
                return;
            }

            $data = $response->toArray(false);

            foreach (($data['results'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $recommendedSku = trim((string) ($row['product_sku'] ?? ''));
                $similarity = (float) ($row['similarity'] ?? 0);

                if ($recommendedSku !== '' && $similarity > 0) {
                    $scores[$recommendedSku]
                        = ($scores[$recommendedSku] ?? 0)
                        + ($similarity * $weight);
                }
            }

        } catch (\Throwable) {
            return;
        }
    }

    private function findLatestVisibleProductsBySkus(
        array $skus,
        int $limit = 5,
        array $excludedSkus = []
    ): array {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku): string => trim((string) $sku),
            $skus
        ))));

        $excludedSkus = array_values(array_unique(array_filter(array_map(
            static fn ($sku): string => trim((string) $sku),
            $excludedSkus
        ))));

        if ($excludedSkus !== []) {
            $skus = array_values(array_filter(
                $skus,
                static fn (string $sku): bool => !in_array($sku, $excludedSkus, true)
            ));
        }

        if ($skus === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where('p.sku IN (:skus)')
            ->andWhere('p.hidden = false')
            ->setParameter('skus', $skus)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $skuToLatestId = [];

        foreach ($rows as $row) {
            $sku = (string) ($row['sku'] ?? '');
            $id = (int) ($row['id'] ?? 0);

            if ($sku !== '' && $id > 0 && !isset($skuToLatestId[$sku])) {
                $skuToLatestId[$sku] = $id;
            }
        }

        $ids = [];

        foreach ($skus as $sku) {
            if (isset($skuToLatestId[$sku])) {
                $ids[] = $skuToLatestId[$sku];
            }
        }

        $ids = array_slice($ids, 0, $limit);

        if ($ids === []) {
            return [];
        }

        $productsRaw = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['id' => $ids]);

        $productMap = [];

        foreach ($productsRaw as $product) {
            if ($product instanceof Product) {
                $productMap[$product->getId()] = $product;
            }
        }

        $products = [];

        foreach ($ids as $id) {
            if (isset($productMap[$id])) {
                $products[] = $productMap[$id];
            }
        }

        return $products;
    }

    private function findFallbackProductsBySalesAndUpdate(int $limit = 5): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id AS id')
            ->addSelect('COALESCE(SUM(oi.quantity), 0) AS HIDDEN salesCount')
            ->from(Product::class, 'p')
            ->leftJoin(OrderItem::class, 'oi', 'WITH', 'oi.sku = p.sku')
            ->where('p.hidden = false')
            ->andWhere('p.sku IS NOT NULL')
            ->andWhere('p.sku <> :emptySku')
            ->setParameter('emptySku', '')
            ->groupBy('p.id')
            ->orderBy('salesCount', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $ids = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $productsRaw = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['id' => $ids]);

        $productMap = [];

        foreach ($productsRaw as $product) {
            if ($product instanceof Product) {
                $productMap[$product->getId()] = $product;
            }
        }

        $products = [];

        foreach ($ids as $id) {
            if (isset($productMap[$id])) {
                $products[] = $productMap[$id];
            }
        }

        return $products;
    }

    #[Route('/recommendation/event', name: 'recommendation_event_log', methods: ['POST'])]
    public function logRecommendationEvent(
        Request $request,
        RecommendationEventLogger $recommendationEventLogger
    ): JsonResponse {
        $data = json_decode((string) $request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON body',
            ], 400);
        }

        $searchConfig = $this->entityManager
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(['active' => true], ['id' => 'DESC']);

        if (!($searchConfig?->isRecommendationLoggingEnabled() ?? true)) {
            return new JsonResponse(['success' => true, 'loggingDisabled' => true]);
        }

        $recommendedSku = trim((string) ($data['recommendedSku'] ?? ''));

        if ($recommendedSku === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Recommended SKU is required',
            ], 400);
        }

        $recommendationEventLogger->log(
            pageType: (string) ($data['pageType'] ?? 'unknown'),
            sourceSku: isset($data['sourceSku']) && $data['sourceSku'] !== ''
                ? (string) $data['sourceSku']
                : null,
            recommendedSku: $recommendedSku,
            algorithm: (string) ($data['algorithm'] ?? 'unknown'),
            rankPosition: (int) ($data['rankPosition'] ?? 0),
            score: isset($data['score']) && $data['score'] !== ''
                ? (float) $data['score']
                : null,
            eventType: (string) ($data['eventType'] ?? 'click')
        );

        return new JsonResponse(['success' => true]);
    }

    private function getActiveSearchMethod(): string
    {
        $config = $this->entityManager
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(['active' => true], ['id' => 'DESC']);

        return $config?->getSearchMethod() ?? 'lexical';
    }

    private function getSearchEndpoint(string $searchMethod): string
    {
        return match ($searchMethod) {
            'semantic_vector' => '/semantic/search',
            'elasticsearch_bm25' => '/elastic/search',
            default => '/search',
        };
    }

    private function getSkuFromSearchRow(array $row, string $searchMethod): string
    {
        if ($searchMethod === 'semantic_vector' || $searchMethod === 'elasticsearch_bm25') {
            return trim((string) ($row['sku'] ?? ''));
        }

        return trim((string) ($row['product_sku'] ?? ''));
    }
}