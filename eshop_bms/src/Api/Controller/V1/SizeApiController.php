<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Size;
use App\Repository\SizeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SIZES_READ')]
#[Route('/api/v1/sizes', name: 'api_v1_sizes_')]
class SizeApiController extends AbstractController
{
    public function __construct(
        private readonly SizeRepository $sizeRepository,
    ) {
    }

    /**
     * Returns a list of sizes.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $sizes = $this->sizeRepository->findAll();

        $data = array_map(
            static fn (Size $size): array => [
                'id' => $size->getId(),
                'name' => $size->getName(),
            ],
            $sizes
        );

        return $this->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Returns size detail by identifier.
     */
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $size = $this->sizeRepository->find($id);

        if ($size === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Size not found'],
                404
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
}