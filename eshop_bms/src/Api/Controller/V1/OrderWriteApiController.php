<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\OrderRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[IsGranted('ROLE_ORDERS_WRITE')]
#[Route('/api/v1/orders', name: 'api_v1_orders_write_')]
class OrderWriteApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    /**
     * Creates a new order (BMS only).
     *
     * Expected body:
     * - customer_id: int (required)
     * - address: string (required)
     * - delivery_method: string (optional; validated by entity)
     * - pickup_or_delivery_at: string|null (optional; datetime)
     * - payment_status: string (optional)
     * - payment_method: string|null (optional)
     * - delivery_status: string (optional)
     * - is_completed: bool (optional)
     * - discount: string (optional; decimal string)
     * - notes: string|null (optional)
     * - items: array (required; at least one item)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->getJson($request);

        $customerId = isset($payload['customer_id']) ? (int) $payload['customer_id'] : 0;
        if ($customerId <= 0) {
            return $this->json(
                ['status' => 'error', 'message' => 'customer_id is required'],
                422
            );
        }

        /** @var Customer|null $customer */
        $customer = $this->em->getRepository(Customer::class)->find($customerId);
        if ($customer === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Customer not found'],
                404
            );
        }

        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
        if ($items === []) {
            return $this->json(
                ['status' => 'error', 'message' => 'items is required'],
                422
            );
        }

        $order = new Order();
        $order->setCustomer($customer);

        $error = $this->applyOrderFields($order, $payload, true);
        if ($error !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $error],
                400
            );
        }

        $calc = $this->buildItemsAndCalcTotal($order, $items);
        if ($calc['error'] !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $calc['error']],
                400
            );
        }

        $order->setTotalPrice($calc['total']);

        $this->em->persist($order);
        $this->em->flush();

        return $this->json(
            [
                'status' => 'success',
                'data' => [
                    'id' => $order->getId(),
                    'total_price' => $order->getTotalPrice(),
                ],
            ],
            201
        );
    }

    /**
     * Updates an existing order (BMS only).
     *
     * Notes:
     * - If "items" is provided, it replaces all existing order items.
     * - Supports PATCH and PUT.
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        if ($order === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Order not found'],
                404
            );
        }

        $payload = $this->getJson($request);

        if (array_key_exists('customer_id', $payload)) {
            $customerId = (int) ($payload['customer_id'] ?? 0);
            if ($customerId <= 0) {
                return $this->json(
                    ['status' => 'error', 'message' => 'customer_id is invalid'],
                    400
                );
            }

            /** @var Customer|null $customer */
            $customer = $this->em->getRepository(Customer::class)->find($customerId);
            if ($customer === null) {
                return $this->json(
                    ['status' => 'error', 'message' => 'Customer not found'],
                    404
                );
            }

            $order->setCustomer($customer);
        }

        $error = $this->applyOrderFields($order, $payload, false);
        if ($error !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $error],
                400
            );
        }

        if (array_key_exists('items', $payload)) {
            if (!is_array($payload['items'])) {
                return $this->json(
                    ['status' => 'error', 'message' => 'items must be an array'],
                    400
                );
            }

            $this->deleteAllItems($order);

            $calc = $this->buildItemsAndCalcTotal($order, $payload['items']);
            if ($calc['error'] !== null) {
                return $this->json(
                    ['status' => 'error', 'message' => $calc['error']],
                    400
                );
            }

            $order->setTotalPrice($calc['total']);
        }

        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $order->getId(),
                'total_price' => $order->getTotalPrice(),
            ],
        ]);
    }

    /**
     * Deletes an order by identifier (BMS only).
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        if ($order === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Order not found'],
                404
            );
        }

        $this->em->remove($order);
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['deletedId' => $id],
        ]);
    }

    /**
     * Decodes JSON request body into an associative array.
     *
     * @return array<string, mixed>
     */
    private function getJson(Request $request): array
    {
        $raw = (string) $request->getContent();
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Applies order scalar fields from payload.
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyOrderFields(Order $order, array $payload, bool $isCreate): ?string
    {
        if (array_key_exists('address', $payload)) {
            $address = trim((string) ($payload['address'] ?? ''));
            if ($address === '') {
                return 'address cannot be empty';
            }

            $order->setAddress($address);
        } elseif ($isCreate) {
            return 'address is required';
        }

        if (array_key_exists('delivery_method', $payload)) {
            $method = trim((string) ($payload['delivery_method'] ?? ''));
            if ($method === '') {
                return 'delivery_method cannot be empty';
            }

            try {
                $order->setDeliveryMethod($method);
            } catch (Throwable) {
                return 'Invalid delivery_method (allowed: pickup, delivery)';
            }
        }

        if (array_key_exists('pickup_or_delivery_at', $payload)) {
            $raw = $payload['pickup_or_delivery_at'];

            if ($raw === null || trim((string) $raw) === '') {
                $order->setPickupOrDeliveryAt(null);
            } else {
                try {
                    $order->setPickupOrDeliveryAt(new DateTime((string) $raw));
                } catch (Throwable) {
                    return 'pickup_or_delivery_at must be a valid datetime string';
                }
            }
        }

        if (array_key_exists('payment_status', $payload)) {
            $paymentStatus = trim((string) ($payload['payment_status'] ?? ''));
            if ($paymentStatus === '') {
                return 'payment_status cannot be empty';
            }

            $order->setPaymentStatus($paymentStatus);
        }

        if (array_key_exists('payment_method', $payload)) {
            $paymentMethod = $payload['payment_method'];
            $order->setPaymentMethod($paymentMethod === null ? null : trim((string) $paymentMethod));
        }

        if (array_key_exists('delivery_status', $payload)) {
            $deliveryStatus = trim((string) ($payload['delivery_status'] ?? ''));
            if ($deliveryStatus === '') {
                return 'delivery_status cannot be empty';
            }

            $order->setDeliveryStatus($deliveryStatus);
        }

        if (array_key_exists('notes', $payload)) {
            $notes = $payload['notes'];
            $order->setNotes($notes === null ? null : (string) $notes);
        }

        if (array_key_exists('discount', $payload)) {
            $discount = (string) ($payload['discount'] ?? '0.00');
            if (!$this->isDecimal($discount)) {
                return 'discount must be a decimal string';
            }

            $order->setDiscount($discount);
        } elseif ($isCreate) {
            $order->setDiscount('0.00');
        }

        if (array_key_exists('is_completed', $payload)) {
            $order->setIsCompleted((bool) $payload['is_completed']);
        }

        return null;
    }

    /**
     * Builds order items and calculates total price minus discount.
     *
     * @param array<int, mixed> $items
     *
     * @return array{error:?string,total:string}
     */
    private function buildItemsAndCalcTotal(Order $order, array $items): array
    {
        $total = 0.0;

        foreach ($items as $index => $row) {
            if (!is_array($row)) {
                return [
                    'error' => sprintf('items[%d] must be an object', $index),
                    'total' => '0.00',
                ];
            }

            $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $quantity = isset($row['quantity']) ? (int) $row['quantity'] : 0;

            if ($productId <= 0) {
                return [
                    'error' => sprintf('items[%d].product_id is required', $index),
                    'total' => '0.00',
                ];
            }

            if ($quantity <= 0) {
                return [
                    'error' => sprintf('items[%d].quantity must be > 0', $index),
                    'total' => '0.00',
                ];
            }

            /** @var Product|null $product */
            $product = $this->em->getRepository(Product::class)->find($productId);
            if ($product === null) {
                return [
                    'error' => sprintf('Product not found: %d', $productId),
                    'total' => '0.00',
                ];
            }

            $unitPrice = isset($row['unit_price']) ? (string) $row['unit_price'] : null;
            $subtotal = isset($row['subtotal']) ? (string) $row['subtotal'] : null;

            if ($unitPrice === null || $subtotal === null) {
                $unitPrice = (string) $product->getPrice();
                $subtotal = number_format(((float) $unitPrice) * $quantity, 2, '.', '');
            } else {
                if (!$this->isDecimal($unitPrice)) {
                    return [
                        'error' => sprintf('items[%d].unit_price must be decimal string', $index),
                        'total' => '0.00',
                    ];
                }

                if (!$this->isDecimal($subtotal)) {
                    return [
                        'error' => sprintf('items[%d].subtotal must be decimal string', $index),
                        'total' => '0.00',
                    ];
                }
            }

            $sku = isset($row['sku']) ? trim((string) $row['sku']) : '';
            if ($sku === '') {
                $sku = (string) $product->getSku();
            }

            $productName = isset($row['product_name']) ? trim((string) $row['product_name']) : '';
            if ($productName === '') {
                $productName = (string) $product->getName();
            }

            $item = new OrderItem();
            $item->setOrder($order);
            $item->setProduct($product);
            $item->setSku($sku);
            $item->setProductName($productName);
            $item->setQuantity($quantity);
            $item->setUnitPrice($unitPrice);
            $item->setSubtotal($subtotal);

            $this->em->persist($item);

            $total += (float) $subtotal;
        }

        $discount = (float) ($order->getDiscount() ?? '0.00');
        $totalAfterDiscount = max(0.0, $total - $discount);

        return [
            'error' => null,
            'total' => number_format($totalAfterDiscount, 2, '.', ''),
        ];
    }

    /**
     * Removes all items from the given order.
     */
    private function deleteAllItems(Order $order): void
    {
        foreach ($order->getOrderItems() as $item) {
            $this->em->remove($item);
        }

        $this->em->flush();
    }

    /**
     * Validates a decimal string with up to 2 fraction digits.
     */
    private function isDecimal(string $value): bool
    {
        $value = trim($value);

        return preg_match('/^\d+(\.\d{1,2})?$/', $value) === 1;
    }
}