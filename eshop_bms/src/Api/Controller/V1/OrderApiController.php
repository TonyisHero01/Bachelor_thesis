<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ORDERS_READ')]
#[Route('/api/v1/orders', name: 'api_v1_orders_')]
class OrderApiController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Returns a paginated list of orders with optional filtering.
     *
     * Query parameters:
     * - customer_id: int
     * - payment_status: string
     * - delivery_status: string
     * - is_completed: bool|int (0/1)
     * - page: int (default 1)
     * - per_page: int (default 20, max 100)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', 20)));
        $offset = ($page - 1) * $perPage;

        $qb = $this->orderRepository->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')
            ->addSelect('c')
            ->orderBy('o.orderCreatedAt', 'DESC');

        $customerId = $request->query->get('customer_id');
        if ($customerId !== null && $customerId !== '') {
            $qb->andWhere('c.id = :cid')
                ->setParameter('cid', (int) $customerId);
        }

        $paymentStatus = $request->query->get('payment_status');
        if ($paymentStatus !== null && $paymentStatus !== '') {
            $qb->andWhere('o.paymentStatus = :ps')
                ->setParameter('ps', (string) $paymentStatus);
        }

        $deliveryStatus = $request->query->get('delivery_status');
        if ($deliveryStatus !== null && $deliveryStatus !== '') {
            $qb->andWhere('o.deliveryStatus = :ds')
                ->setParameter('ds', (string) $deliveryStatus);
        }

        $isCompletedRaw = $request->query->get('is_completed');
        if ($isCompletedRaw !== null && $isCompletedRaw !== '') {
            $qb->andWhere('o.isCompleted = :ic')
                ->setParameter('ic', $this->toBool($isCompletedRaw));
        }

        $qb->setFirstResult($offset)->setMaxResults($perPage);

        /** @var Order[] $orders */
        $orders = $qb->getQuery()->getResult();

        $total = $this->countOrders(
            $customerId !== null && $customerId !== '' ? (int) $customerId : null,
            $paymentStatus !== null && $paymentStatus !== '' ? (string) $paymentStatus : null,
            $deliveryStatus !== null && $deliveryStatus !== '' ? (string) $deliveryStatus : null,
            $isCompletedRaw !== null && $isCompletedRaw !== '' ? $this->toBool($isCompletedRaw) : null
        );

        $items = array_map(
            fn (Order $order): array => $this->serializeOrder($order, false),
            $orders
        );

        return $this->json([
            'status' => 'success',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                ],
            ],
        ]);
    }

    /**
     * Returns a single order detail by identifier, including order items.
     */
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        if ($order === null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Order not found',
                ],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => $this->serializeOrder($order, true),
        ]);
    }

    /**
     * Serializes an order entity to an API-friendly array structure.
     *
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order, bool $withItems = true): array
    {
        $data = [
            'id' => $order->getId(),
            'customer_id' => $order->getCustomer()->getId(),
            'total_price' => $order->getTotalPrice(),
            'discount' => $order->getDiscount(),
            'address' => $order->getAddress(),
            'order_created_at' => $order->getOrderCreatedAt()->format(DATE_ATOM),
            'pickup_or_delivery_at' => $order->getPickupOrDeliveryAt()?->format(DATE_ATOM),
            'delivery_method' => $order->getDeliveryMethod(),
            'payment_status' => $order->getPaymentStatus(),
            'payment_method' => $order->getPaymentMethod(),
            'delivery_status' => $order->getDeliveryStatus(),
            'is_completed' => $order->getIsCompleted(),
            'notes' => $order->getNotes(),
        ];

        if ($withItems) {
            $data['items'] = array_map(
                static function (OrderItem $item): array {
                    return [
                        'id' => $item->getId(),
                        'product_id' => $item->getProduct()->getId(),
                        'sku' => $item->getSku(),
                        'product_name' => $item->getProductName(),
                        'quantity' => $item->getQuantity(),
                        'unit_price' => $item->getUnitPrice(),
                        'subtotal' => $item->getSubtotal(),
                    ];
                },
                $order->getOrderItems()->toArray()
            );
        }

        return $data;
    }

    /**
     * Counts orders using the same optional filters as the list endpoint.
     */
    private function countOrders(
        ?int $customerId,
        ?string $paymentStatus,
        ?string $deliveryStatus,
        ?bool $isCompleted
    ): int {
        $qb = $this->orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->leftJoin('o.customer', 'c');

        if ($customerId !== null) {
            $qb->andWhere('c.id = :cid')
                ->setParameter('cid', $customerId);
        }

        if ($paymentStatus !== null) {
            $qb->andWhere('o.paymentStatus = :ps')
                ->setParameter('ps', $paymentStatus);
        }

        if ($deliveryStatus !== null) {
            $qb->andWhere('o.deliveryStatus = :ds')
                ->setParameter('ds', $deliveryStatus);
        }

        if ($isCompleted !== null) {
            $qb->andWhere('o.isCompleted = :ic')
                ->setParameter('ic', $isCompleted);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Converts common boolean representations ("1"/"0", "true"/"false") to bool.
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        if ($value === '1' || $value === 'true' || $value === 'yes' || $value === 'on') {
            return true;
        }

        if ($value === '0' || $value === 'false' || $value === 'no' || $value === 'off') {
            return false;
        }

        return (bool) $value;
    }
}