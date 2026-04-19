<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Size;
use App\Repository\SizeRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SIZES_WRITE')]
#[Route('/api/v1/sizes', name: 'api_v1_sizes_write_')]
class SizeWriteApiController extends AbstractController
{
    public function __construct(
        private readonly SizeRepository $sizeRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Creates a size (BMS only).
     *
     * Expected body:
     * - name: string (required)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->getJson($request);
        if ($payload === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Invalid JSON body'],
                400
            );
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'Field "name" is required'],
                422
            );
        }

        $size = new Size();
        $size->setName($name);

        try {
            $this->em->persist($size);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(
                ['status' => 'error', 'message' => 'Size name already exists'],
                409
            );
        }

        return $this->json(
            [
                'status' => 'success',
                'data' => [
                    'id' => $size->getId(),
                    'name' => $size->getName(),
                ],
            ],
            201
        );
    }

    /**
     * Updates a size by identifier (BMS only).
     *
     * Expected body:
     * - name: string (required)
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $size = $this->sizeRepository->find($id);
        if ($size === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Size not found'],
                404
            );
        }

        $payload = $this->getJson($request);
        if ($payload === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Invalid JSON body'],
                400
            );
        }

        if (!array_key_exists('name', $payload)) {
            return $this->json(
                ['status' => 'error', 'message' => 'Field "name" is required'],
                422
            );
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'name cannot be empty'],
                400
            );
        }

        $size->setName($name);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(
                ['status' => 'error', 'message' => 'Size name already exists'],
                409
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $size->getId(),
                'name' => $size->getName(),
            ],
        ]);
    }

    /**
     * Deletes a size by identifier (BMS only).
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $size = $this->sizeRepository->find($id);
        if ($size === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Size not found'],
                404
            );
        }

        $this->em->remove($size);
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
}