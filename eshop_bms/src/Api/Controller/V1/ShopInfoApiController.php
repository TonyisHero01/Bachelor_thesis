<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\ShopInfo;
use App\Repository\ShopInfoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SHOPINFOS_READ')]
#[Route('/api/v1/shop-info', name: 'api_v1_shop_info_')]
class ShopInfoApiController extends AbstractController
{
    public function __construct(
        private readonly ShopInfoRepository $shopInfoRepository,
    ) {
    }

    /**
     * Returns the current shop info (latest record).
     *
     * Query parameters:
     * - locale: language code (default: "en")
     */
    #[Route('', name: 'current', methods: ['GET'])]
    public function current(Request $request): JsonResponse
    {
        $locale = (string) $request->query->get('locale', 'en');

        /** @var ShopInfo|null $shopInfo */
        $shopInfo = $this->shopInfoRepository->createQueryBuilder('s')
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($shopInfo === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Shop info not found'],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => $this->serializeShopInfo($shopInfo, $locale),
        ]);
    }

    /**
     * Returns shop info by identifier.
     *
     * Query parameters:
     * - locale: language code (default: "en")
     */
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(int $id, Request $request): JsonResponse
    {
        $locale = (string) $request->query->get('locale', 'en');

        $shopInfo = $this->shopInfoRepository->find($id);
        if ($shopInfo === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Shop info not found'],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => $this->serializeShopInfo($shopInfo, $locale),
        ]);
    }

    /**
     * Serializes ShopInfo using translated fields for the requested locale.
     *
     * @return array<string, mixed>
     */
    private function serializeShopInfo(ShopInfo $shopInfo, string $locale): array
    {
        $translatableFields = [
            'eshopName',
            'aboutUs',
            'howToOrder',
            'businessConditions',
            'privacyPolicy',
            'shippingInfo',
            'payment',
            'refund',
        ];

        $translated = [];
        foreach ($translatableFields as $field) {
            $translated[$field] = $shopInfo->getTranslatedField($field, $locale);
        }

        return [
            'id' => $shopInfo->getId(),
            'eshopName' => $translated['eshopName'],
            'aboutUs' => $translated['aboutUs'],
            'howToOrder' => $translated['howToOrder'],
            'businessConditions' => $translated['businessConditions'],
            'privacyPolicy' => $translated['privacyPolicy'],
            'shippingInfo' => $translated['shippingInfo'],
            'payment' => $translated['payment'],
            'refund' => $translated['refund'],
            'address' => $shopInfo->getAddress(),
            'telephone' => $shopInfo->getTelephone(),
            'email' => $shopInfo->getEmail(),
            'logo' => $shopInfo->getLogo(),
            'carouselPictures' => $shopInfo->getCarouselPictures() ?? [],
            'companyName' => $shopInfo->getCompanyName(),
            'cin' => $shopInfo->getCin(),
            'hidePrices' => $shopInfo->getHidePrices(),
            'meta' => [
                'locale_used' => $locale,
            ],
        ];
    }
}