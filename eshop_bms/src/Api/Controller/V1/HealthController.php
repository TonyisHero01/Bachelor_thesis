<?php
namespace App\Api\Controller\V1;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class HealthController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status'  => 'ok',
            'service' => 'eshop_bms',
            'version' => 'v1',
        ]);
    }
}