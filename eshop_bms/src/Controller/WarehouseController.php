<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Currency;
use App\Entity\ReturnRequest;
use App\Entity\Product;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Service\ShipmentService;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[IsGranted('ROLE_WAREHOUSEMAN')]
class WarehouseController extends BaseController
{
    /**
     * Displays the warehouse dashboard with pending orders and low-stock products.
     */
    #[Route('/warehouse', name: 'app_warehouse', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {

        $pendingOrders = $em->getRepository(Order::class)->createQueryBuilder('o')
            ->where('o.isCompleted = false')
            ->orderBy('o.orderCreatedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $lowStockProducts = $em->getRepository(\App\Entity\Product::class)->createQueryBuilder('p')
        ->where('p.number_in_stock < :threshold')
        ->setParameter('threshold', 5)
        ->orderBy('p.number_in_stock', 'ASC')
        ->setMaxResults(10)
        ->getQuery()
        ->getResult();

        return $this->renderLocalized('warehouse/index.html.twig', [
            'controller_name' => 'WarehouseController',
            'pendingOrders' => $pendingOrders,
            'lowStockProducts' => $lowStockProducts,
        ]);
    }

    /**
     * Displays all products with low stock (below configured threshold).
     */
    #[Route('/warehouse/low-stock', name: 'all_low_stock_products', methods: ['GET'])]
    public function showAllLowStockProducts(EntityManagerInterface $em): Response
    {
        $lowStockProducts = $em->getRepository(Product::class)->createQueryBuilder('p')
            ->where('p.number_in_stock < :threshold')
            ->setParameter('threshold', 5)
            ->orderBy('p.number_in_stock', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->renderLocalized('warehouse/low_stock_all.html.twig', [
            'lowStockProducts' => $lowStockProducts,
        ]);
    }

    /**
     * Batch updates stock for selected products.
     */
    #[Route('/warehouse/update-stocks', name: 'batch_update_product_stock', methods: ['POST'])]
    public function batchUpdateStock(Request $request, EntityManagerInterface $em): Response
    {
        $ids = $request->request->all('selected_products');
        $newStock = (int) $request->request->get('new_stock');

        if (!$ids || $newStock < 0) {
            $this->addFlash('error', 'Invalid selection or stock value.');
            return $this->redirectToRoute('all_low_stock_products');
        }

        $products = $em->getRepository(Product::class)->findBy(['id' => $ids]);

        foreach ($products as $product) {
            $product->setNumberInStock($newStock);
        }

        $em->flush();

        $this->addFlash('success', count($products) . ' products updated successfully.');
        return $this->redirectToRoute('all_low_stock_products');
    }

    /**
     * Updates stock for a single product.
     */
    #[Route('/warehouse/product/{id}/update-stock', name: 'update_product_stock', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateProductStock(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $product = $em->getRepository(Product::class)->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found.');
        }

        $newStock = (int) $request->request->get('new_stock');
        $product->setNumberInStock($newStock);
        $em->flush();

        return $this->redirectToRoute('app_warehouse');
    }

    /**
     * Displays the order management page with all orders and the default currency.
     */
    #[Route('/warehouse/order_management', name: 'app_order_management', methods: ['GET'])]
    public function orderManagement(EntityManagerInterface $em): Response
    {
        $orders = $em->getRepository(Order::class)->findAllOrders();

        $currency = $em->getRepository(Currency::class)->findDefaultCurrency();

        return $this->renderLocalized('warehouse/order_management.html.twig',[
            'orders' => $orders,
            'currency' => $currency
        ]);
    }

    /**
     * Displays details for a specific order.
     */
    #[Route('/warehouse/order/{id}', name: 'order_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function orderDetail(int $id, EntityManagerInterface $em): Response
    {
        $order = $em->getRepository(Order::class)->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->renderLocalized('warehouse/order_detail.html.twig', [
            'order' => $order
        ]);
    }

    /**
     * Marks an order as completed.
     */
    #[Route('/order/mark_completed/{id}', name: 'mark_order_completed', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markAsCompleted(int $id, EntityManagerInterface $em): JsonResponse
    {
        $order = $em->getRepository(Order::class)->find($id);

        if (!$order) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $order->setIsCompleted(true);
        $em->persist($order);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Displays all return requests.
     */
    #[Route('/warehouse/return-requests', name: 'app_return_requests', methods: ['GET'])]
    public function returnRequests(Request $request, EntityManagerInterface $em): Response
    {
        $returnRequests = $em->getRepository(ReturnRequest::class)->findAll();

        return $this->renderLocalized(
            'warehouse/return_requests.html.twig',
            [
                'returnRequests' => $returnRequests,
            ],
            $request
        );
    }

    /**
     * Displays details for a specific return request.
     */
    #[Route('/warehouse/return-request/{id}', name: 'return_request_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function returnRequestDetail(int $id, EntityManagerInterface $em): Response
    {
        $returnRequest = $em->getRepository(ReturnRequest::class)->find($id);

        if (!$returnRequest) {
            throw $this->createNotFoundException('Return request not found.');
        }

        return $this->renderLocalized('warehouse/return_request_detail.html.twig', [
            'request' => $returnRequest
        ]);
    }

    /**
     * Updates the status of a return request.
     */
    #[Route('/warehouse/return-request/{id}/process', name: 'process_return_request', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function processReturnRequest(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $returnRequest = $em->getRepository(ReturnRequest::class)->find($id);

        if (!$returnRequest) {
            return new JsonResponse(['success' => false, 'message' => 'Return request not found.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? '';

        if (!in_array($status, ['accepted', 'rejected'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid status.'], 400);
        }

        $returnRequest->setStatus($status);
        $em->persist($returnRequest);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Advances shipment status for an order.
     */
    #[Route('/warehouse/{id}/shipment/advance', name: 'order_shipment_advance', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function advance(int $id, Request $request, ShipmentService $svc): RedirectResponse
    {

        $token = (string) $req->request->get('_token');
        if (!$this->isCsrfTokenValid('ship-advance-'.$id, $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('order_detail', ['id' => $id]);
        }

        $to = strtoupper((string) $req->request->get('to')); 
        $allowed = ['PACKED','SHIPPED','IN_TRANSIT','OUT_FOR_DELIVERY','DELIVERED','RETURNED'];
        if (!in_array($to, $allowed, true)) {
            $this->addFlash('error', 'Invalid target status.');
            return $this->redirectToRoute('order_detail', ['id' => $id]);
        }

        try {
            $svc->advanceTo($id, $to, $this->getUser()->getUserIdentifier());
            $this->addFlash('success', "Shipment advanced to {$to}.");
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Failed to advance shipment: '.$e->getMessage());
        }

        $back = $req->headers->get('referer');
        return $back ? $this->redirect($back) : $this->redirectToRoute('order_detail', ['id' => $id]);
    }
}
