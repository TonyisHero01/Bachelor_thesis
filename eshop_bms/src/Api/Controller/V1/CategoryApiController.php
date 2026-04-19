<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CATEGORIES_READ')]
#[Route('/api/v1/categories', name: 'api_v1_categories_')]
class CategoryApiController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * Returns a list of categories translated to the requested locale.
     *
     * Query parameters:
     * - locale: Language code (default: "en")
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $locale = (string) $request->query->get('locale', 'en');

        $categories = $this->categoryRepository->findAll();

        $data = array_map(
            static function (Category $category) use ($locale): array {
                return [
                    'id' => $category->getId(),
                    'name' => $category->getTranslatedName($locale),
                ];
            },
            $categories
        );

        return $this->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Returns a single category detail translated to the requested locale.
     *
     * Path parameters:
     * - id: Category identifier
     *
     * Query parameters:
     * - locale: Language code (default: "en")
     */
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(int $id, Request $request): JsonResponse
    {
        $locale = (string) $request->query->get('locale', 'en');

        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Category not found',
                ],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $category->getId(),
                'name' => $category->getTranslatedName($locale),
            ],
        ]);
    }
}