<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Currency;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CURRENCIES_WRITE')]
#[Route('/api/v1/currencies', name: 'api_v1_currencies_write_')]
class CurrencyWriteApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CurrencyRepository $currencyRepository,
    ) {
    }

    /**
     * Creates a new currency.
     *
     * Expected body:
     * - name: 3-letter currency code (e.g. "EUR")
     * - value: positive number
     * - isDefault: optional boolean
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->getJson($request);

        $name = strtoupper(trim((string) ($payload['name'] ?? '')));
        if ($name === '' || strlen($name) !== 3) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Invalid currency name. Expect 3-letter code, e.g. "EUR".',
                ],
                400
            );
        }

        if (!array_key_exists('value', $payload) || $payload['value'] === null || $payload['value'] === '') {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Missing currency value.',
                ],
                400
            );
        }

        $value = (float) $payload['value'];
        if ($value <= 0.0) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Currency value must be > 0.',
                ],
                400
            );
        }

        $isDefault = (bool) ($payload['isDefault'] ?? false);

        $currency = new Currency();
        $currency->setName($name);
        $currency->setValue($value);
        $currency->setIsDefault($isDefault);

        if ($isDefault) {
            $this->unsetOtherDefaults(null);
        }

        $this->em->persist($currency);
        $this->em->flush();

        return $this->json(
            [
                'status' => 'success',
                'data' => [
                    'id' => $currency->getId(),
                ],
            ],
            201
        );
    }

    /**
     * Updates an existing currency by identifier.
     *
     * Allowed partial body:
     * - name: 3-letter currency code
     * - value: positive number
     * - isDefault: boolean
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $payload = $this->getJson($request);

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

        if (array_key_exists('name', $payload)) {
            $name = strtoupper(trim((string) $payload['name']));
            if ($name === '' || strlen($name) !== 3) {
                return $this->json(
                    [
                        'status' => 'error',
                        'message' => 'Invalid currency name. Expect 3-letter code, e.g. "EUR".',
                    ],
                    400
                );
            }

            $currency->setName($name);
        }

        if (array_key_exists('value', $payload)) {
            if ($payload['value'] === null || $payload['value'] === '') {
                return $this->json(
                    [
                        'status' => 'error',
                        'message' => 'Currency value cannot be empty.',
                    ],
                    400
                );
            }

            $value = (float) $payload['value'];
            if ($value <= 0.0) {
                return $this->json(
                    [
                        'status' => 'error',
                        'message' => 'Currency value must be > 0.',
                    ],
                    400
                );
            }

            $currency->setValue($value);
        }

        if (array_key_exists('isDefault', $payload)) {
            $isDefault = (bool) $payload['isDefault'];
            $currency->setIsDefault($isDefault);

            if ($isDefault) {
                $this->unsetOtherDefaults($currency->getId());
            }
        }

        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $currency->getId(),
            ],
        ]);
    }

    /**
     * Deletes a currency by identifier.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
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

        $this->em->remove($currency);
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => [
                'deletedId' => $id,
            ],
        ]);
    }

    /**
     * Sets the given currency as the default currency.
     */
    #[Route('/{id}/default', name: 'set_default', methods: ['POST'])]
    public function setDefault(int $id): JsonResponse
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

        $this->unsetOtherDefaults($currency->getId());
        $currency->setIsDefault(true);

        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $currency->getId(),
                'is_default' => $currency->isIsDefault(),
            ],
        ]);
    }

    /**
     * Decodes JSON request body into an associative array.
     *
     * @return array<string, mixed>
     */
    private function getJson(Request $request): array
    {
        $raw = (string) $request->getContent();
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Unsets the default flag for all currencies except the one to keep.
     */
    private function unsetOtherDefaults(?int $keepId): void
    {
        $defaults = $this->currencyRepository->findBy(['isDefault' => true]);

        foreach ($defaults as $currency) {
            if ($keepId !== null && $currency->getId() === $keepId) {
                continue;
            }

            $currency->setIsDefault(false);
        }
    }
}