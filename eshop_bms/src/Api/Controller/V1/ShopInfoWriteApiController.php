<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\ShopInfo;
use App\Entity\ShopInfoTranslation;
use App\Repository\ShopInfoRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SHOPINFOS_WRITE')]
#[Route('/api/v1/shop-info', name: 'api_v1_shop_info_write_')]
class ShopInfoWriteApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShopInfoRepository $shopInfoRepository,
    ) {
    }

    /**
     * Creates a shop info record (BMS only).
     *
     * The entity fields are mostly nullable, therefore no required fields are enforced here.
     * Translations can be provided via:
     * - translations: { "cz": { ... }, "en": { ... } }
     * - locale + translation: { "locale": "cz", "translation": { ... } }
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

        $shopInfo = new ShopInfo();

        try {
            $error = $this->applyBaseFields($shopInfo, $payload);
            if ($error !== null) {
                return $this->json(['status' => 'error', 'message' => $error], 400);
            }

            $error = $this->applyTranslations($shopInfo, $payload);
            if ($error !== null) {
                return $this->json(['status' => 'error', 'message' => $error], 400);
            }
        } catch (InvalidArgumentException $e) {
            return $this->json(
                ['status' => 'error', 'message' => $e->getMessage()],
                400
            );
        }

        $this->em->persist($shopInfo);
        $this->em->flush();

        return $this->json(
            [
                'status' => 'success',
                'data' => ['id' => $shopInfo->getId()],
            ],
            201
        );
    }

    /**
     * Updates a shop info record by identifier (BMS only).
     *
     * Supports PATCH and PUT.
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $payload = $this->getJson($request);
        if ($payload === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Invalid JSON body'],
                400
            );
        }

        $shopInfo = $this->shopInfoRepository->find($id);
        if ($shopInfo === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Shop info not found'],
                404
            );
        }

        try {
            $error = $this->applyBaseFields($shopInfo, $payload);
            if ($error !== null) {
                return $this->json(['status' => 'error', 'message' => $error], 400);
            }

            $error = $this->applyTranslations($shopInfo, $payload);
            if ($error !== null) {
                return $this->json(['status' => 'error', 'message' => $error], 400);
            }
        } catch (InvalidArgumentException $e) {
            return $this->json(
                ['status' => 'error', 'message' => $e->getMessage()],
                400
            );
        }

        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['id' => $shopInfo->getId()],
        ]);
    }

    /**
     * Deletes a shop info record by identifier (BMS only).
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $shopInfo = $this->shopInfoRepository->find($id);
        if ($shopInfo === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Shop info not found'],
                404
            );
        }

        $this->em->remove($shopInfo);
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['deletedId' => $id],
        ]);
    }

    /**
     * Updates the latest shop info record, or creates a new one if none exists (BMS only).
     *
     * Endpoint:
     * - PATCH /api/v1/shop-info/current
     */
    #[Route('/current', name: 'update_current', methods: ['PATCH'])]
    public function updateCurrent(Request $request): JsonResponse
    {
        $payload = $this->getJson($request);
        if ($payload === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Invalid JSON body'],
                400
            );
        }

        /** @var ShopInfo|null $shopInfo */
        $shopInfo = $this->shopInfoRepository->createQueryBuilder('si')
            ->orderBy('si.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $created = false;
        if ($shopInfo === null) {
            $shopInfo = new ShopInfo();
            $created = true;
        }

        try {
            $error = $this->applyBaseFields($shopInfo, $payload);
            if ($error !== null) {
                return $this->json(['status' => 'error', 'message' => $error], 400);
            }

            $error = $this->applyTranslations($shopInfo, $payload);
            if ($error !== null) {
                return $this->json(['status' => 'error', 'message' => $error], 400);
            }
        } catch (InvalidArgumentException $e) {
            return $this->json(
                ['status' => 'error', 'message' => $e->getMessage()],
                400
            );
        }

        if ($created) {
            $this->em->persist($shopInfo);
        }

        $this->em->flush();

        return $this->json(
            [
                'status' => 'success',
                'data' => [
                    'id' => $shopInfo->getId(),
                    'created' => $created,
                ],
            ],
            $created ? 201 : 200
        );
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
     * Applies non-translated fields (ShopInfo main table).
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyBaseFields(ShopInfo $shopInfo, array $payload): ?string
    {
        $stringFields = [
            'eshopName' => 'setEshopName',
            'address' => 'setAddress',
            'telephone' => 'setTelephone',
            'email' => 'setEmail',
            'logo' => 'setLogo',
            'companyName' => 'setCompanyName',
            'cin' => 'setCin',
            'aboutUs' => 'setAboutUs',
            'howToOrder' => 'setHowToOrder',
            'businessConditions' => 'setBusinessConditions',
            'privacyPolicy' => 'setPrivacyPolicy',
            'shippingInfo' => 'setShippingInfo',
            'payment' => 'setPayment',
            'refund' => 'setRefund',
        ];

        foreach ($stringFields as $key => $setter) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if ($value === null) {
                $shopInfo->{$setter}(null);
                continue;
            }

            $value = trim((string) $value);
            $shopInfo->{$setter}($value === '' ? null : $value);
        }

        if (array_key_exists('hidePrices', $payload)) {
            $shopInfo->setHidePrices((bool) $payload['hidePrices']);
        }

        if (array_key_exists('carouselPictures', $payload)) {
            $pics = $payload['carouselPictures'];

            if ($pics === null) {
                $shopInfo->setCarouselPictures(null);
            } elseif (!is_array($pics)) {
                return 'carouselPictures must be an array';
            } else {
                $out = [];

                foreach ($pics as $value) {
                    $value = trim((string) $value);
                    if ($value !== '') {
                        $out[] = $value;
                    }
                }

                $shopInfo->setCarouselPictures($out);
            }
        }

        return null;
    }

    /**
     * Applies translations to ShopInfoTranslation entities.
     *
     * Supported formats:
     * - translations: { "cz": { ... }, "en": { ... } }
     * - locale + translation: { "locale": "cz", "translation": { ... } }
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyTranslations(ShopInfo $shopInfo, array $payload): ?string
    {
        if (isset($payload['translations']) && is_array($payload['translations'])) {
            foreach ($payload['translations'] as $locale => $fields) {
                $loc = strtolower(trim((string) $locale));
                if ($loc === '') {
                    continue;
                }

                if (!is_array($fields)) {
                    return 'translations.<locale> must be an object';
                }

                $this->upsertTranslationFields($shopInfo, $loc, $fields);
            }

            return null;
        }

        if (array_key_exists('translation', $payload) || array_key_exists('locale', $payload)) {
            $loc = strtolower(trim((string) ($payload['locale'] ?? '')));
            if ($loc === '') {
                return 'locale is required when using "translation"';
            }

            if (!isset($payload['translation']) || !is_array($payload['translation'])) {
                return 'translation must be an object';
            }

            $this->upsertTranslationFields($shopInfo, $loc, $payload['translation']);

            return null;
        }

        return null;
    }

    /**
     * Inserts or updates translation fields for a specific locale.
     *
     * @param array<string, mixed> $fields
     */
    private function upsertTranslationFields(ShopInfo $shopInfo, string $locale, array $fields): void
    {
        $translation = $this->findOrCreateTranslation($shopInfo, $locale);

        $map = [
            'aboutUs' => 'setAboutUs',
            'howToOrder' => 'setHowToOrder',
            'businessConditions' => 'setBusinessConditions',
            'privacyPolicy' => 'setPrivacyPolicy',
            'shippingInfo' => 'setShippingInfo',
            'payment' => 'setPayment',
            'refund' => 'setRefund',
        ];

        foreach ($map as $key => $setter) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }

            $value = $fields[$key];

            if ($value === null) {
                $translation->{$setter}(null);
                continue;
            }

            $value = trim((string) $value);
            $translation->{$setter}($value === '' ? null : $value);
        }
    }

    /**
     * Finds an existing translation by locale or creates a new one.
     */
    private function findOrCreateTranslation(ShopInfo $shopInfo, string $locale): ShopInfoTranslation
    {
        foreach ($shopInfo->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        $translation = new ShopInfoTranslation();
        $translation->setLocale($locale);
        $translation->setShopInfo($shopInfo);
        $shopInfo->addTranslation($translation);

        return $translation;
    }
}