<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Product;
use App\Entity\Customer;
use App\Entity\Cart;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class EshopProductController extends AbstractController
{
    private $shopInfo;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }

    #[Route('/eshop/product', name: 'app_eshop_product')]
    public function index(): Response
    {
        return $this->render('eshop_product/index.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false
        ]);
    }

    #[Route('/product/{id}', name: 'show_eshop_product')]
    public function show(EntityManagerInterface $entityManager, int $id): Response
    {
        $product = $entityManager->getRepository(Product::class)->findProductById($id);
        if(!$product)
        {
            throw $this->createNotFoundException(
                'No product found for id '.$id
            );
        }

        return $this->render('eshop_product/index.html.twig', [
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false,
            'product' => $product,
            'BMS_URL' => $this->getParameter('BMS_URL'),
        ]);
    }
    
    #[Route('/cart/add', name: 'add_to_cart', methods: ['POST'])]
    public function addToCart(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $productId = (int) ($data['productId'] ?? 0);
        $quantity = (int) ($data['quantity'] ?? 1);

        if ($quantity <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid quantity'], 400);
        }

        if (!$this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 403);
        }

        $customer = $entityManager->getRepository(Customer::class)->find($this->getUser()->getId());
        $product = $entityManager->getRepository(Product::class)->find($productId);

        if (!$product || !$customer) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid product or user'], 400);
        }

        // 检查购物车是否已有该商品
        $cartItem = $entityManager->getRepository(Cart::class)->findOneBy([
            'customer' => $customer,
            'product' => $product
        ]);

        if ($cartItem) {
            $cartItem->setQuantity($cartItem->getQuantity() + $quantity);
        } else {
            $cartItem = new Cart();
            $cartItem->setCustomer($customer);
            $cartItem->setProduct($product);
            $cartItem->setQuantity($quantity);
            $cartItem->setAddedAt(new \DateTime());
            $entityManager->persist($cartItem);
        }

        $entityManager->flush();

        // 计算购物车总数
        $cartTotalQuantity = $entityManager->createQueryBuilder()
            ->select('SUM(c.quantity)')
            ->from(Cart::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'success' => true,
            'message' => 'Product added to cart',
            'cartCount' => $cartTotalQuantity ?? 0
        ]);
    }

    #[Route('/cart/count', name: 'cart_count', methods: ['GET'])]
    public function getCartCount(EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['cartCount' => 0]);
        }

        $customer = $entityManager->getRepository(Customer::class)->find($this->getUser()->getId());

        $cartTotalQuantity = $entityManager->createQueryBuilder()
            ->select('SUM(c.quantity)')
            ->from(Cart::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse(['cartCount' => $cartTotalQuantity ?? 0]);
    }
}