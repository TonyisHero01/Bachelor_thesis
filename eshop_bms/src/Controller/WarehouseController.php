<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Currency;
use App\Entity\ReturnRequest;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class WarehouseController extends BaseController
{
    #[Route('/warehouse', name: 'app_warehouse')]
    /**
     * Displays the warehouse dashboard if the user is authenticated.
     *
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @return Response
     */
    public function index(AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->Localized('employee/employee_not_logged.html.twig', []);
        }
        return $this->renderLocalized('warehouse/index.html.twig', [
            'controller_name' => 'WarehouseController',
        ]);
    }

    #[Route('/warehouse/order_tracking', name: 'app_order_tracking')]
    /**
     * Displays the order tracking page with a list of all orders and the default currency.
     *
     * @param EntityManagerInterface $entityManager
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @return Response
     */
    public function redirectToOrderTracking(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }
        $orders = $entityManager->getRepository(Order::class)->findAllOrders();

        $currency = $entityManager->getRepository(Currency::class)->findDefaultCurrency();

        return $this->renderLocalized('warehouse/order_tracking.html.twig',[
            'orders' => $orders,
            'currency' => $currency
        ]);
    }
    #[Route('/warehouse/order/{id}', name: 'order_detail')]
    /**
     * Shows detailed information about a specific order.
     *
     * @param int $id Order ID.
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function orderDetail(int $id, EntityManagerInterface $entityManager): Response
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->renderLocalized('warehouse/order_detail.html.twig', [
            'order' => $order
        ]);
    }
    #[Route('/order/mark_completed/{id}', name: 'mark_order_completed', methods: ['POST'])]
    /**
     * Marks a specific order as completed via POST request.
     *
     * @param int $id Order ID.
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse JSON response with success status.
     */
    public function markAsCompleted(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $order->setIsCompleted(true);
        $entityManager->persist($order);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/warehouse/return-requests', name: 'app_return_requests')]
    /**
     * Displays all return requests with support for localized templates.
     *
     * @param Request $request HTTP request containing locale info.
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger Logger to debug locale and path info.
     * @return Response
     */
    public function returnRequests(
        Request $request,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ): Response {
        $locale = $request->get('_locale') ?? $request->query->get('_locale') ?? $request->getLocale();

        $logger->info("🧭 Requested locale: " . $locale);
        $logger->info("📄 Will try localized template: templates/locale/{$locale}/warehouse/return_requests.html.twig");

        $returnRequests = $entityManager->getRepository(ReturnRequest::class)->findAll();

        return $this->renderLocalized('warehouse/return_requests.html.twig', [
            'returnRequests' => $returnRequests,
        ], $request);
    }

    #[Route('/warehouse/return-request/{id}', name: 'return_request_detail')]
    /**
     * Shows detailed information about a specific return request.
     *
     * @param int $id Return request ID.
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function returnRequestDetail(int $id, EntityManagerInterface $entityManager): Response
    {
        $returnRequest = $entityManager->getRepository(ReturnRequest::class)->find($id);

        if (!$returnRequest) {
            throw $this->createNotFoundException('Return request not found.');
        }

        return $this->renderLocalized('warehouse/return_request_detail.html.twig', [
            'request' => $returnRequest
        ]);
    }

    #[Route('/warehouse/return-request/{id}/process', name: 'process_return_request', methods: ['POST'])]
    /**
     * Updates the status of a return request to either 'accepted' or 'rejected'.
     *
     * @param int $id Return request ID.
     * @param EntityManagerInterface $entityManager
     * @param Request $request JSON POST body containing new status.
     * @return JsonResponse JSON response indicating success or error.
     */
    public function processReturnRequest(int $id, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $returnRequest = $entityManager->getRepository(ReturnRequest::class)->find($id);

        if (!$returnRequest) {
            return new JsonResponse(['success' => false, 'message' => 'Return request not found.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? '';

        if (!in_array($status, ['accepted', 'rejected'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid status.'], 400);
        }

        $returnRequest->setStatus($status);
        $entityManager->persist($returnRequest);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
