<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

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
    #[Route('/product_add_to_wishlist/{id}', name: 'add_eshop_product_to_wishlist')]
    public function addToWishlist(EntityManagerInterface $entityManager, int $id): Response
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
            'product' => $product
        ]);
    }
}