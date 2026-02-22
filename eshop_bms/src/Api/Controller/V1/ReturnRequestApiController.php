<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Order;
use App\Entity\ReturnRequest;
use App\Repository\OrderRepository;
use App\Repository\ReturnRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_RETURNREQUESTS_READ')]
#[Route('/api/v1/returns', name: 'api_v1_returns_')]
class ReturnRequestApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReturnRequestRepository $returnRepo,
        private readonly OrderRepository $orderRepo,
    ) {
    }

    /**
     * Returns a list of return requests (BMS only).
     *
     * Query parameters:
     * - status: string (e.g. pending|accepted|rejected)
     * - email: string (partial match)
     * - order_id: int
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->returnRepo->createQueryBuilder('r')
            ->leftJoin('r.order', 'o')
            ->addSelect('o')
            ->orderBy('r.requestDate', 'DESC');

        $status = $request->query->get('status');
        if ($status !== null && $status !== '') {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', (string) $status);
        }

        $email = $request->query->get('email');
        if ($email !== null && $email !== '') {
            $qb->andWhere('r.userEmail LIKE :email')
                ->setParameter('email', sprintf('%%%s%%', (string) $email));
        }

        $orderId = $request->query->get('order_id');
        if ($orderId !== null && $orderId !== '') {
            $qb->andWhere('o.id = :oid')
                ->setParameter('oid', (int) $orderId);
        }

        /** @var ReturnRequest[] $items */
        $items = $qb->getQuery()->getResult();

        $data = array_map(
            fn (ReturnRequest $returnRequest): array => $this->serializeReturn($returnRequest),
            $items
        );

        return $this->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Returns a single return request detail by identifier.
     */
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $returnRequest = $this->returnRepo->find($id);
        if ($returnRequest === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Return request not found'],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => $this->serializeReturn($returnRequest, true),
        ]);
    }

    /**
     * Serializes a return request entity to an API-friendly array structure.
     *
     * @return array<string, mixed>
     */
    private function serializeReturn(ReturnRequest $returnRequest, bool $withOrder = false): array
    {
        $data = [
            'id' => $returnRequest->getId(),
            'status' => $returnRequest->getStatus(),
            'userEmail' => $returnRequest->getUserEmail(),
            'userPhone' => $returnRequest->getUserPhone(),
            'userName' => $returnRequest->getUserName(),
            'returnReason' => $returnRequest->getReturnReason(),
            'userMessage' => $returnRequest->getUserMessage(),
            'productSkus' => array_values(
                array_filter(
                    array_map('trim', explode(',', (string) $returnRequest->getProductSkus())),
                    static fn (string $sku): bool => $sku !== ''
                )
            ),
            'requestDate' => $returnRequest->getRequestDate()->format(DATE_ATOM),
        ];

        if ($withOrder) {
            $order = $returnRequest->getOrder();

            $data['order'] = $order instanceof Order
                ? [
                    'id' => $order->getId(),
                    'totalItems' => count($order->getOrderItems()),
                    'totalPrice' => $order->getTotalAmount() ?? null,
                ]
                : null;
        }

        return $data;
    }
}