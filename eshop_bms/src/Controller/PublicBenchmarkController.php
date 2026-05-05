<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PublicBenchmarkController
{
    #[Route('/search-like', name: 'public_search_like', methods: ['GET'])]
    public function searchLike(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if ($query === '') {
            return new JsonResponse(['results' => []]);
        }

        $results = $em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('LOWER(p.name) LIKE :q OR LOWER(p.description) LIKE :q')
            ->andWhere('p.hidden = false')
            ->setParameter('q', '%' . mb_strtolower($query) . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'results' => array_map(static fn (Product $p): array => [
                'id' => $p->getId(),
                'sku' => $p->getSku(),
                'name' => $p->getName(),
            ], $results),
        ]);
    }
}