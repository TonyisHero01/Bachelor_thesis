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
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_RETURNREQUESTS_WRITE')]
#[Route('/api/v1/returns', name: 'api_v1_returns_write_')]
class ReturnRequestWriteApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReturnRequestRepository $returnRepo,
        private readonly OrderRepository $orderRepo,
    ) {
    }

    /**
     * Creates a new return request.
     *
     * Expected body:
     * - order_id: int (required)
     * - userEmail: string (required)
     * - userPhone: string (required)
     * - userName: string (required)
     * - productSkus: string (required; comma-separated SKUs)
     * - returnReason: string|null (optional)
     * - userMessage: string|null (optional)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = $this->getJson($request);
        if ($data === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Invalid JSON body'],
                400
            );
        }

        $orderId = $data['order_id'] ?? null;
        if (!is_numeric($orderId)) {
            return $this->json(
                ['status' => 'error', 'message' => 'order_id is required'],
                400
            );
        }

        /** @var Order|null $order */
        $order = $this->orderRepo->find((int) $orderId);
        if ($order === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Order not found'],
                404
            );
        }

        $userEmail = trim((string) ($data['userEmail'] ?? ''));
        $userPhone = trim((string) ($data['userPhone'] ?? ''));
        $userName = trim((string) ($data['userName'] ?? ''));
        $productSkus = trim((string) ($data['productSkus'] ?? ''));

        if ($userEmail === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'userEmail is required'],
                400
            );
        }

        if ($userPhone === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'userPhone is required'],
                400
            );
        }

        if ($userName === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'userName is required'],
                400
            );
        }

        if ($productSkus === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'productSkus is required'],
                400
            );
        }

        $returnRequest = new ReturnRequest();
        $returnRequest
            ->setOrder($order)
            ->setUserEmail($userEmail)
            ->setUserPhone($userPhone)
            ->setUserName($userName)
            ->setProductSkus($productSkus)
            ->setReturnReason($this->toNullableString($data, 'returnReason'))
            ->setUserMessage($this->toNullableString($data, 'userMessage'));

        $errors = $validator->validate($returnRequest);
        if (count($errors) > 0) {
            $messages = [];

            foreach ($errors as $error) {
                $messages[] = sprintf('%s: %s', $error->getPropertyPath(), $error->getMessage());
            }

            return $this->json(
                ['status' => 'error', 'message' => $messages],
                400
            );
        }

        $this->em->persist($returnRequest);
        $this->em->flush();

        return $this->json(
            [
                'status' => 'success',
                'message' => 'Return request submitted successfully',
                'data' => ['id' => $returnRequest->getId()],
            ],
            201
        );
    }

    /**
     * Updates the status of a return request (BMS only).
     *
     * Expected body:
     * - status: "pending"|"accepted"|"rejected"
     */
    #[Route('/{id}/status', name: 'update_status', methods: ['PATCH'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $returnRequest = $this->returnRepo->find($id);
        if ($returnRequest === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Return request not found'],
                404
            );
        }

        $data = $this->getJson($request);
        if ($data === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Invalid JSON body'],
                400
            );
        }

        $status = $data['status'] ?? null;
        if (!is_string($status) || !in_array($status, ['pending', 'accepted', 'rejected'], true)) {
            return $this->json(
                ['status' => 'error', 'message' => 'Invalid status'],
                400
            );
        }

        $returnRequest->setStatus($status);
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'message' => sprintf('Return request status updated to %s', $status),
        ]);
    }

    /**
     * Deletes a return request by identifier (BMS only).
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $returnRequest = $this->returnRepo->find($id);
        if ($returnRequest === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Return request not found'],
                404
            );
        }

        $this->em->remove($returnRequest);
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['deletedId' => $id],
        ]);
    }

    /**
     * Decodes JSON request body into an associative array.
     *
     * @return array<string, mixed>|null Returns null when JSON is invalid.
     */
    private function getJson(Request $request): ?array
    {
        $raw = (string) $request->getContent();
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Returns a nullable string from payload, preserving explicit null values.
     *
     * @param array<string, mixed> $data
     */
    private function toNullableString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];

        return $value === null ? null : (string) $value;
    }
}