<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Entity\ShopInfo;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Service\PaymentRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class CheckoutController extends BaseController
{
    private ?ShopInfo $shopInfo = null;

    public function __construct(
        private EntityManagerInterface $em,
        private OrderRepository $orders,
        private CategoryRepository $categoriesRepo,
        private PaymentRecorder $recorder,
        Environment $twig,
        LoggerInterface $logger
    ) {
        parent::__construct($twig, $logger);

        $this->shopInfo = $this->em
            ->getRepository(ShopInfo::class)
            ->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Displays a mock payment gateway page for testing checkout flows.
     */
    #[Route(path: '/checkout/mock-gateway', name: 'checkout_mock_gateway', methods: ['GET'])]
    public function mockGateway(Request $request): Response
    {
        $orderId = (int) $request->query->get('order', 0);
        /** @var Order|null $order */
        $order = $orderId > 0 ? $this->orders->find($orderId) : null;

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        $categories = method_exists($this->categoriesRepo, 'findAllCategories')
            ? $this->categoriesRepo->findAllCategories()
            : $this->categoriesRepo->findAll();

        return $this->renderLocalized(
            'checkout/mock_gateway.html.twig',
            [
                'shopInfo' => $this->shopInfo,
                'order' => $order,
                'show_sidebar' => false,
                'categories' => $categories,
            ],
            $request
        );
    }

    /**
     * Receives the mock gateway callback and records a payment attempt.
     * This endpoint returns JSON because the frontend expects to call res.json().
     */
    #[Route(path: '/checkout/mock-gateway/callback', name: 'checkout_mock_callback', methods: ['POST'])]
    public function mockCallback(Request $request): JsonResponse
    {
        $orderId = (int) $request->request->get('order_id', 0);
        $result = strtoupper((string) $request->request->get('result', 'PENDING'));

        /** @var Order|null $order */
        $order = $orderId > 0 ? $this->orders->find($orderId) : null;

        if (!$order) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $payment = $this->recorder->recordPayment($order, [
            'status' => $result,
            'provider' => 'mock',
            'amount' => (string) $order->getTotalPrice(),
            'currencyCode' => (string) ($request->request->get('currencyCode') ?: 'CZK'),
            'payload' => [
                'via' => 'mock_gateway',
                'ip' => $request->getClientIp(),
                'ts' => time(),
            ],
        ]);

        return new JsonResponse([
            'success' => true,
            'orderId' => $order->getId(),
            'orderPaymentStatus' => $order->getPaymentStatus(),
            'paymentId' => $payment->getId(),
            'paymentStatus' => $payment->getStatus(),
        ]);
    }

    /**
     * Returns the current payment status for an order, including the latest payment if present.
     */
    #[Route(path: '/checkout/status/{id}', name: 'checkout_status', methods: ['GET'])]
    public function status(int $id): JsonResponse
    {
        /** @var Order|null $order */
        $order = $this->orders->find($id);

        if (!$order) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $latest = $order->getLatestPayment();

        return new JsonResponse([
            'success' => true,
            'orderId' => $order->getId(),
            'orderPaymentStatus' => $order->getPaymentStatus(),
            'latestPayment' => $latest ? [
                'id' => $latest->getId(),
                'status' => $latest->getStatus(),
                'amount' => $latest->getAmount(),
            ] : null,
        ]);
    }
}