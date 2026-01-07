<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Color;
use App\Repository\ColorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_COLORS_READ')]
#[Route('/api/v1/colors', name: 'api_v1_colors_')]
class ColorApiController extends AbstractController
{
    public function __construct(
        private readonly ColorRepository $colorRepository,
    ) {
    }

    /**
     * Returns a list of colors translated to the requested locale.
     *
     * Query parameters:
     * - locale: Language code (default: "en")
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $locale = (string) $request->query->get('locale', 'en');

        $colors = $this->colorRepository->findAll();

        $data = array_map(
            static function (Color $color) use ($locale): array {
                return [
                    'id' => $color->getId(),
                    'name' => $color->getTranslatedName($locale),
                    'hex' => $color->getHex(),
                ];
            },
            $colors
        );

        return $this->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Returns a color detail translated to the requested locale.
     *
     * Path parameters:
     * - id: Color identifier
     *
     * Query parameters:
     * - locale: Language code (default: "en")
     */
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(int $id, Request $request): JsonResponse
    {
        $locale = (string) $request->query->get('locale', 'en');

        $color = $this->colorRepository->find($id);
        if ($color === null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Color not found',
                ],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $color->getId(),
                'name' => $color->getTranslatedName($locale),
                'hex' => $color->getHex(),
            ],
        ]);
    }
}