<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;

class ShipmentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orders,
    ) {
    }

    /**
     * Advances an order shipment to a new delivery status.
     *
     * @throws \RuntimeException         When the order does not exist
     * @throws \InvalidArgumentException When the target status is not allowed
     */
    public function advanceTo(int $orderId, string $toStatus, string $operator): void
    {
        $order = $this->orders->find($orderId);
        if ($order === null) {
            throw new \RuntimeException(sprintf('Order #%d not found.', $orderId));
        }

        $isPickup = $order->getDeliveryMethod() === 'pickup';

        $allowedForPickup = [
            'PACKED',
            'DELIVERED',
            'RETURNED',
        ];

        $allowedForDelivery = [
            'PACKED',
            'SHIPPED',
            'IN_TRANSIT',
            'OUT_FOR_DELIVERY',
            'DELIVERED',
            'RETURNED',
        ];

        $targetStatus = strtoupper($toStatus);
        $allowed = $isPickup ? $allowedForPickup : $allowedForDelivery;

        if (!in_array($targetStatus, $allowed, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Status "%s" is not allowed for delivery method "%s".',
                    $targetStatus,
                    (string) $order->getDeliveryMethod()
                )
            );
        }

        $shipment = $order->getShipment();

        if ($shipment === null) {
            $shipment = new Shipment();
            $shipment->setOrder($order);
            $shipment->setTrackingNumber($this->generateTrackingNumber());

            $order->setShipment($shipment);
            $this->em->persist($shipment);

            $customerEmail = $order->getCustomer()?->getEmail() ?? 'customer';
            $this->pushEvent(
                $shipment,
                'created',
                sprintf('Order placed by customer: %s', $customerEmail),
                'Online'
            );
        }

        $shipment->setStatus($targetStatus);

        if (method_exists($shipment, 'touch')) {
            $shipment->touch();
        }

        $order->setDeliveryStatus($targetStatus);

        $this->pushEvent(
            $shipment,
            strtolower($targetStatus),
            sprintf('Status → %s by %s', $targetStatus, $operator),
            null
        );

        $this->em->flush();
    }

    /**
     * Creates and persists a shipment event.
     */
    private function pushEvent(
        Shipment $shipment,
        string $code,
        ?string $description,
        ?string $location
    ): void {
        $event = new ShipmentEvent();
        $event->setShipment($shipment);
        $event->setEventCode($code);
        $event->setDescription($description);
        $event->setLocation($location);

        $this->em->persist($event);
    }

    /**
     * Generates a unique shipment tracking number.
     */
    private function generateTrackingNumber(): string
    {
        return 'MK'
            . date('YmdHis')
            . substr(bin2hex(random_bytes(3)), 0, 6);
    }
}