<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\ShopInfo;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class EshopProductController extends BaseController
{
    protected ?ShopInfo $shopInfo = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        Environment $twig,
        LoggerInterface $logger,
        ManagerRegistry $doctrine,
    ) {
        parent::__construct($twig, $logger, $doctrine);

        $this->shopInfo = $this->entityManager
            ->getRepository(ShopInfo::class)
            ->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Displays the product overview entry page.
     */
    #[Route('/eshop/product', name: 'app_eshop_product', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop_product/index.html.twig',
            [
                'shopInfo' => $this->shopInfo,
                'locale' => (string) $request->getLocale(),
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Displays product detail page for the given product id.
     */
    #[Route('/product/{id}', name: 'show_eshop_product', methods: ['GET'])]
    public function show(Request $request, int $id): Response
    {
        $productRepo = $this->entityManager->getRepository(Product::class);

        $product = method_exists($productRepo, 'findProductById')
            ? $productRepo->findProductById($id)
            : $productRepo->find($id);

        if (!$product instanceof Product) {
            throw $this->createNotFoundException(sprintf('No product found for id %d', $id));
        }

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        $shopInfo = $this->entityManager->getRepository(ShopInfo::class)->findOneBy([]);

        return $this->renderLocalized(
            'eshop_product/index.html.twig',
            [
                'shopInfo' => $shopInfo,
                'locale' => (string) $request->getLocale(),
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
                'product' => $product,
                'BMS_URL' => $this->getParameter('BMS_URL'),
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Adds a product to the authenticated customer's cart.
     * If the cart item already exists, its quantity is increased.
     */
    #[Route('/cart/add', name: 'add_to_cart', methods: ['POST'])]
    public function addToCart(Request $request): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body'], 400);
        }

        $productId = (int) ($data['productId'] ?? 0);
        $quantity = (int) ($data['quantity'] ?? 1);

        if ($productId <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid productId'], 400);
        }

        if ($quantity <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid quantity'], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof Customer) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 403);
        }

        $customer = $this->entityManager->getRepository(Customer::class)->find($user->getId());
        $product = $this->entityManager->getRepository(Product::class)->find($productId);

        if (!$customer instanceof Customer || !$product instanceof Product) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid product or user'], 400);
        }

        $cartRepo = $this->entityManager->getRepository(Cart::class);
        $cartItem = $cartRepo->findOneBy([
            'customer' => $customer,
            'product' => $product,
        ]);

        if ($cartItem instanceof Cart) {
            $cartItem->setQuantity($cartItem->getQuantity() + $quantity);
        } else {
            $cartItem = new Cart();
            $cartItem->setCustomer($customer);
            $cartItem->setProduct($product);
            $cartItem->setQuantity($quantity);
            $cartItem->setAddedAt(new \DateTime());
            $this->entityManager->persist($cartItem);
        }

        $this->entityManager->flush();

        $cartTotalQuantity = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(c.quantity), 0)')
            ->from(Cart::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'success' => true,
            'message' => 'Product added to cart',
            'cartCount' => (int) $cartTotalQuantity,
        ]);
    }

    /**
     * Returns the total quantity of items in the authenticated customer's cart.
     */
    #[Route('/cart/count', name: 'cart_count', methods: ['GET'])]
    public function getCartCount(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Customer) {
            return new JsonResponse(['cartCount' => 0]);
        }

        $customer = $this->entityManager->getRepository(Customer::class)->find($user->getId());
        if (!$customer instanceof Customer) {
            return new JsonResponse(['cartCount' => 0]);
        }

        $cartTotalQuantity = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(c.quantity), 0)')
            ->from(Cart::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse(['cartCount' => (int) $cartTotalQuantity]);
    }
}