<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use Doctrine\ORM\EntityManagerInterface;

class ShipmentService
{
    public function __construct(private EntityManagerInterface $em){}

    public function createOnOrderPlaced(Order $order): Shipment
    {
        if ($order->getShipment()) {
            return $order->getShipment();
        }

        $shipment = new Shipment();
        $shipment->setOrder($order);
        $shipment->setTrackingNumber($this->generateTrackingNumber());
        $shipment->setStatus('CREATED');
        $order->setShipment($shipment);
        $order->setDeliveryStatus('PENDING');

        $this->em->persist($shipment);

        $customerEmail = $order->getCustomer()?->getEmail() ?? 'customer';

        $event = new ShipmentEvent();
        $event->setShipment($shipment);
        $event->setEventCode('created');
        $event->setDescription("Order placed by customer: {$customerEmail}");
        $event->setLocation('Online');
        $this->em->persist($event);
        
        return $shipment;
    }

    private function generateTrackingNumber(): string
    {
        return 'MK' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 6);
    }
}