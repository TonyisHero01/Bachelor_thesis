<?php

// src/Controller/CustomerController.php
namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Customer;
use App\Entity\Product;
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
    
    #[Route(path: '/add_to_wishlist', name: 'wishlist_adding', methods: ['POST'])]
    public function addToWishlist(
        Request $request, 
        EntityManagerInterface $entityManager, 
        AuthorizationCheckerInterface $authorizationChecker
    ): JsonResponse {
        $user = $this->getUser();

        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY') || !$user instanceof Customer) {
            return new JsonResponse(['status' => 'error', 'message' => 'User not authenticated or not a customer'], 403);
        }

        $input = json_decode($request->getContent(), true);
        $productId = $input['product_id'] ?? null;

        if (!$productId) {
            return new JsonResponse(['status' => 'error', 'message' => 'Product ID not provided'], 400);
        }

        $product = $entityManager->getRepository(Product::class)->find($productId);
        if (!$product) {
            return new JsonResponse(['status' => 'error', 'message' => 'Product not found'], 404);
        }

        if (!in_array($productId, $user->getWishlist(), true)) {
            $user->setWishlist([...$user->getWishlist(), $productId]);
            $entityManager->persist($user);
            $entityManager->flush();
        }

        return new JsonResponse(['status' => 'success', 'wishlist' => $user->getWishlist()]);
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
}