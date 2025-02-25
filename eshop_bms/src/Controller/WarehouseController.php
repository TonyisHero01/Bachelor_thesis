<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Currency;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;

class WarehouseController extends AbstractController
{
    #[Route('/warehouse', name: 'app_warehouse')]
    public function index(AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }
        return $this->render('warehouse/index.html.twig', [
            'controller_name' => 'WarehouseController',
        ]);
    }

    #[Route('/warehouse/order_tracking', name: 'app_order_tracking')]
    public function redirectToOrderTracking(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }
        $orders = $entityManager->getRepository(Order::class)->findAllOrders();

        $currency = $entityManager->getRepository(Currency::class)->findDefaultCurrency();

        return $this->render('warehouse/order_tracking.html.twig',[
            'orders' => $orders,
            'currency' => $currency
        ]);
    }
    #[Route('/warehouse/order/{id}', name: 'order_detail')]
    public function orderDetail(int $id, EntityManagerInterface $entityManager): Response
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->render('warehouse/order_detail.html.twig', [
            'order' => $order
        ]);
    }
    #[Route('/order/mark_completed/{id}', name: 'mark_order_completed', methods: ['POST'])]
    public function markAsCompleted(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
        }

        // 标记订单为已完成
        $order->setIsCompleted(true);
        $entityManager->persist($order);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
