<?php
namespace App\Controller;

use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EventManagerController extends BaseController
{
    #[Route('/event-manager/discounts', name: 'event_manager_discounts')]
    public function manageDiscounts(Request $request, ProductRepository $productRepository): Response
    {
        $skuFilter = $request->query->get('sku');
        $nameFilter = $request->query->get('name');

        $products = $productRepository->findLatestProductsGroupedBySku($skuFilter, $nameFilter);

        return $this->renderLocalized('event_manager/discounts.html.twig', [
            'products' => $products,
        ], $request);
    }

    #[Route('/event-manager/update-discounts', name: 'event_manager_update_discounts', methods: ['POST'])]
    public function updateDiscounts(Request $request, ProductRepository $productRepository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $discounts = $data['discounts'] ?? [];

        if (empty($discounts)) {
            return $this->json(['success' => false, 'message' => '无效请求']);
        }

        foreach ($discounts as $id => $discount) {
            $product = $productRepository->find($id);
            if ($product && $discount >= 0 && $discount <= 100) {
                $product->setDiscount((float) $discount);
            }
        }

        $em->flush();

        return $this->json(['success' => true, 'message' => 'Bulk discounts updated successfully']);
    }
}