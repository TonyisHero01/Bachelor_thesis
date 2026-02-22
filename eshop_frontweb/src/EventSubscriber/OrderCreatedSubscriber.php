<?php
namespace App\EventSubscriber;

use App\Entity\Order;
use App\Service\ShipmentService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;

#[AsDoctrineListener(event: Events::postPersist)]
class OrderCreatedSubscriber implements EventSubscriber
{
    public function __construct(private ShipmentService $shipmentService) {}

    public function getSubscribedEvents(): array
    {
        return [Events::postPersist];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Order) {
            return;
        }

        // 订单一创建 → 自动创建运单并写入 CREATED 事件
        $this->shipmentService->createOnOrderPlaced($entity);

        // 若你的下单流程最后没有统一 flush，则取消下一行注释：
        // $args->getObjectManager()->flush();
    }
}