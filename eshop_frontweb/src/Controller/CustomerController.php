<?php

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
    /**
     * Displays the customer login form.
     * Redirects to the customer home page if the user is already authenticated.
     * Stores the referrer for post-login redirection.
     *
     * @param AuthenticationUtils $authenticationUtils
     * @param Request $request
     * @param SessionInterface $session
     * @return Response
     */
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
    /**
     * Displays the customer home page.
     * Requires the user to be authenticated.
     *
     * @param Request $request
     * @return Response
     */
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

    /**
     * Handles customer registration.
     * Automatically logs in the customer and redirects to the home page on success.
     *
     * @param Request $request
     * @param UserPasswordHasherInterface $passwordHasher
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
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
            $customer->setIsVerified(false);
            $entityManager->persist($customer);
            $entityManager->flush();

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
   /**
     * Customer logout route.
     * This method will never be executed directly; it is intercepted by Symfony firewall.
     *
     * @return void
     */
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/customer/wishlist', name: 'customer_wishlist')]
    /**
     * Displays the wishlist for the currently logged-in customer.
     * Redirects to login if unauthenticated.
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
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
    /**
     * Checks if the specified product is in the customer's wishlist.
     *
     * @param int $productId
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function checkWishlist(int $productId, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user || !$user instanceof Customer) {
            return new JsonResponse(['inWishlist' => false], 403);
        }

        $wishlist = $user->getWishlist();

        return new JsonResponse(['inWishlist' => in_array($productId, $wishlist)]);
    }

    #[Route(path: '/add_to_wishlist', name: 'wishlist_adding', methods: ['POST'])]
    /**
     * Adds or removes a product from the customer's wishlist.
     * Toggles wishlist state and returns updated wishlist as JSON.
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
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

        $wishlist = $user->getWishlist();

        if (in_array($productId, $wishlist)) {
            $wishlist = array_filter($wishlist, fn($id) => $id != $productId);
        } else {
            $wishlist[] = (int) $productId;
        }

        $user->setWishlist(array_values($wishlist));
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success', 'wishlist' => $wishlist]);
    }

    #[Route('/wishlist/remove/{id}', name: 'remove_from_wishlist', methods: ['POST'])]
    /**
     * Removes a product from the customer's wishlist.
     *
     * @param int $id Product ID
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function removeFromWishlist(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User not logged in'], 403);
        }

        $wishlist = $user->getWishlist();
        if (!in_array($id, $wishlist)) {
            return new JsonResponse(['success' => false, 'message' => 'The product is not in the wishlist.'], 400);
        }

        $wishlist = array_diff($wishlist, [$id]);
        $user->setWishlist(array_values($wishlist));
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/cart', name: 'customer_cart')]
    /**
     * Displays the customer's shopping cart.
     * Redirects to login if not authenticated.
     *
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
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
    /**
     * Updates the quantity of an item in the cart.
     *
     * @param int $id Cart item ID
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
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
    /**
     * Removes an item from the customer's cart.
     *
     * @param int $id Cart item ID
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
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
    /**
     * Displays the order confirmation page.
     * Ensures the order belongs to the logged-in customer.
     *
     * @param int $id Order ID
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
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
    /**
     * Displays an alternative order confirmation page.
     * Ensures the order belongs to the logged-in customer.
     *
     * @param int $id Order ID
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
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
    /**
     * Displays all orders made by the current customer.
     * Requires authentication.
     *
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
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
    /**
     * Displays all return requests made by the current customer.
     * Requires authentication and filters by user email.
     *
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
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
