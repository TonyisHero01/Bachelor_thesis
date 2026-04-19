<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Currency;
use App\Repository\CurrencyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CURRENCIES_READ')]
#[Route('/api/v1/currencies', name: 'api_v1_currencies_')]
class CurrencyApiController extends AbstractController
{
    public function __construct(
        private readonly CurrencyRepository $currencyRepository,
    ) {
    }

    /**
     * Returns a list of all currencies.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $currencies = $this->currencyRepository->findAll();

        $data = array_map(
            static function (Currency $currency): array {
                return [
                    'id' => $currency->getId(),
                    'name' => $currency->getName(),
                    'value' => $currency->getValue(),
                    'is_default' => $currency->isIsDefault(),
                ];
            },
            $currencies
        );

        return $this->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Returns a single currency detail by identifier.
     */
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $currency = $this->currencyRepository->find($id);

        if ($currency === null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Currency not found',
                ],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $currency->getId(),
                'name' => $currency->getName(),
                'value' => $currency->getValue(),
                'is_default' => $currency->isIsDefault(),
            ],
        ]);
    }

    /**
     * Returns the default currency.
     */
    #[Route('/default', name: 'default', methods: ['GET'])]
    public function defaultCurrency(): JsonResponse
    {
        $defaultCurrency = $this->currencyRepository->findOneBy(['isDefault' => true]);

        if ($defaultCurrency === null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Default currency not set',
                ],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $defaultCurrency->getId(),
                'name' => $defaultCurrency->getName(),
                'value' => $defaultCurrency->getValue(),
                'is_default' => $defaultCurrency->isIsDefault(),
            ],
        ]);
    }
}