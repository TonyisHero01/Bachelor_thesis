<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Category;
use App\Entity\Color;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\Size;
use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PRODUCTS_WRITE')]
#[Route('/api/v1/products', name: 'api_v1_products_write_')]
class ProductWriteApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * Creates a new product (BMS only).
     *
     * Expected body may include:
     * - name: string (required on create)
     * - sku: string
     * - price: number (required on create)
     * - discount: number (0..100, 100 = no discount)
     * - tax_rate: number (0..100)
     * - number_in_stock: int (required on create)
     * - hidden: bool
     * - category_id: int|null
     * - color_id: int|null
     * - size_id: int|null
     * - currency_id: int (required on create)
     * - image_urls: string[]|null
     * - attributes: array|object|null
     * - dimensions: {width?:number|null,height?:number|null,length?:number|null,weight?:number|null}
     * - translations: { locale: {name?:string|null,description?:string|null,material?:string|null,attributes?:array|object|null} }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->getJson($request);

        $product = new Product();

        try {
            $error = $this->applyProductFields($product, $payload, true);
        } catch (InvalidArgumentException $e) {
            return $this->json(
                ['status' => 'error', 'message' => $e->getMessage()],
                400
            );
        }

        if ($error !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $error],
                400
            );
        }

        $this->em->persist($product);
        $this->em->flush();

        return $this->json(
            [
                'status' => 'success',
                'data' => ['id' => $product->getId()],
            ],
            201
        );
    }

    /**
     * Updates an existing product (BMS only).
     *
     * Supports PATCH and PUT. Body can include any fields from create.
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if ($product === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Product not found'],
                404
            );
        }

        $payload = $this->getJson($request);

        try {
            $error = $this->applyProductFields($product, $payload, false);
        } catch (InvalidArgumentException $e) {
            return $this->json(
                ['status' => 'error', 'message' => $e->getMessage()],
                400
            );
        }

        if ($error !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $error],
                400
            );
        }

        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['id' => $product->getId()],
        ]);
    }

    /**
     * Deletes a product by identifier (BMS only).
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if ($product === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Product not found'],
                404
            );
        }

        $this->em->remove($product);
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['deletedId' => $id],
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
     * Applies product fields from payload and validates input.
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyProductFields(Product $product, array $payload, bool $isCreate): ?string
    {
        if ($isCreate) {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '') {
                return 'name is required';
            }

            if (!array_key_exists('price', $payload)) {
                return 'price is required';
            }

            if (!array_key_exists('number_in_stock', $payload)) {
                return 'number_in_stock is required';
            }

            if (!array_key_exists('currency_id', $payload)) {
                return 'currency_id is required';
            }
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '') {
                return 'name cannot be empty';
            }

            $product->setName($name);
        }

        if (array_key_exists('description', $payload)) {
            $product->setDescription($payload['description'] === null ? null : (string) $payload['description']);
        }

        if (array_key_exists('material', $payload)) {
            $product->setMaterial($payload['material'] === null ? null : (string) $payload['material']);
        }

        if (array_key_exists('sku', $payload)) {
            $sku = trim((string) ($payload['sku'] ?? ''));
            if ($sku === '') {
                return 'sku cannot be empty';
            }

            $product->setSku($sku);
        }

        if (array_key_exists('price', $payload)) {
            if (!is_numeric($payload['price'])) {
                return 'price must be numeric';
            }

            $price = (float) $payload['price'];
            if ($price < 0.0) {
                return 'price must be >= 0';
            }

            $product->setPrice($price);
        }

        if (array_key_exists('discount', $payload)) {
            if (!is_numeric($payload['discount'])) {
                return 'discount must be numeric';
            }

            $discount = (float) $payload['discount'];
            if ($discount < 0.0 || $discount > 100.0) {
                return 'discount must be between 0 and 100 (100 = no discount)';
            }

            $product->setDiscount($discount);
        }

        if (array_key_exists('tax_rate', $payload)) {
            if (!is_numeric($payload['tax_rate'])) {
                return 'tax_rate must be numeric';
            }

            $taxRate = (float) $payload['tax_rate'];
            if ($taxRate < 0.0 || $taxRate > 100.0) {
                return 'tax_rate must be between 0 and 100';
            }

            $product->setTaxRate($taxRate);
        }

        if (array_key_exists('number_in_stock', $payload)) {
            if (!is_numeric($payload['number_in_stock'])) {
                return 'number_in_stock must be numeric';
            }

            $stock = (int) $payload['number_in_stock'];
            if ($stock < 0) {
                return 'number_in_stock must be >= 0';
            }

            $product->setNumberInStock($stock);
        }

        if (array_key_exists('hidden', $payload)) {
            $product->setHidden((bool) $payload['hidden']);
        }

        $imageUrlsError = $this->applyImageUrls($product, $payload);
        if ($imageUrlsError !== null) {
            return $imageUrlsError;
        }

        $attributesError = $this->applyAttributes($product, $payload);
        if ($attributesError !== null) {
            return $attributesError;
        }

        if (array_key_exists('version', $payload)) {
            if (!is_numeric($payload['version'])) {
                return 'version must be numeric';
            }

            $version = (int) $payload['version'];
            if ($version <= 0) {
                return 'version must be >= 1';
            }

            $product->setVersion($version);
        }

        $categoryError = $this->applyNullableRelation($product, Category::class, $payload, 'category_id', 'setCategory');
        if ($categoryError !== null) {
            return $categoryError;
        }

        $colorError = $this->applyNullableRelation($product, Color::class, $payload, 'color_id', 'setColor');
        if ($colorError !== null) {
            return $colorError;
        }

        $sizeError = $this->applyNullableRelation($product, Size::class, $payload, 'size_id', 'setSize');
        if ($sizeError !== null) {
            return $sizeError;
        }

        if (array_key_exists('currency_id', $payload)) {
            $currency = $this->findRequired(Currency::class, $payload['currency_id'], 'currency_id');
            if ($currency === null) {
                return 'Currency not found';
            }

            $product->setCurrency($currency);
        }

        $dimensionsError = $this->applyDimensions($product, $payload);
        if ($dimensionsError !== null) {
            return $dimensionsError;
        }

        $now = new DateTimeImmutable();
        if ($isCreate) {
            $product->setCreatedAt($now);
        }

        $product->setUpdatedAt($now);

        $translationsError = $this->applyTranslations($product, $payload);
        if ($translationsError !== null) {
            return $translationsError;
        }

        return null;
    }

    /**
     * Applies image_urls field from payload.
     *
     * @param array<string, mixed> $payload
     */
    private function applyImageUrls(Product $product, array $payload): ?string
    {
        if (!array_key_exists('image_urls', $payload)) {
            return null;
        }

        if ($payload['image_urls'] === null) {
            $product->setImageUrls([]);
            return null;
        }

        if (!is_array($payload['image_urls'])) {
            return 'image_urls must be an array of strings';
        }

        $urls = [];
        foreach ($payload['image_urls'] as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $urls[] = $value;
            }
        }

        $product->setImageUrls(array_values($urls));

        return null;
    }

    /**
     * Applies attributes field from payload.
     *
     * @param array<string, mixed> $payload
     */
    private function applyAttributes(Product $product, array $payload): ?string
    {
        if (!array_key_exists('attributes', $payload)) {
            return null;
        }

        if ($payload['attributes'] === null) {
            $product->setAttributes([]);
            return null;
        }

        if (!is_array($payload['attributes'])) {
            return 'attributes must be an object/array';
        }

        $product->setAttributes($payload['attributes']);

        return null;
    }

    /**
     * Applies a nullable relation by *_id from payload.
     *
     * @param class-string $class
     * @param array<string, mixed> $payload
     */
    private function applyNullableRelation(
        Product $product,
        string $class,
        array $payload,
        string $fieldName,
        string $setter
    ): ?string {
        if (!array_key_exists($fieldName, $payload)) {
            return null;
        }

        try {
            $entity = $this->findNullable($class, $payload[$fieldName], $fieldName);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        $product->{$setter}($entity);

        return null;
    }

    /**
     * Applies dimensions fields from payload.
     *
     * @param array<string, mixed> $payload
     */
    private function applyDimensions(Product $product, array $payload): ?string
    {
        if (!array_key_exists('dimensions', $payload)) {
            return null;
        }

        if ($payload['dimensions'] === null) {
            return null;
        }

        if (!is_array($payload['dimensions'])) {
            return 'dimensions must be an object';
        }

        $d = $payload['dimensions'];

        if (array_key_exists('width', $d)) {
            $product->setWidth($d['width'] === null ? null : (float) $d['width']);
        }

        if (array_key_exists('height', $d)) {
            $product->setHeight($d['height'] === null ? null : (float) $d['height']);
        }

        if (array_key_exists('length', $d)) {
            $product->setLength($d['length'] === null ? null : (float) $d['length']);
        }

        if (array_key_exists('weight', $d)) {
            $product->setWeight($d['weight'] === null ? null : (float) $d['weight']);
        }

        return null;
    }

    /**
     * Applies translations from payload.
     *
     * Supported formats:
     * - translations: { "en": {...}, "cz": {...} }
     * - translation: { "locale": "en", ... }
     *
     * @param array<string, mixed> $payload
     */
    private function applyTranslations(Product $product, array $payload): ?string
    {
        if (isset($payload['translations'])) {
            if (!is_array($payload['translations'])) {
                return 'translations must be an object';
            }

            foreach ($payload['translations'] as $locale => $tr) {
                $loc = strtolower(trim((string) $locale));
                if ($loc === '' || !is_array($tr)) {
                    continue;
                }

                $this->upsertTranslation($product, $loc, $tr);
            }

            return null;
        }

        if (isset($payload['translation'])) {
            if (!is_array($payload['translation'])) {
                return 'translation must be an object';
            }

            $loc = strtolower(trim((string) ($payload['translation']['locale'] ?? '')));
            if ($loc === '') {
                return 'translation.locale is required';
            }

            $this->upsertTranslation($product, $loc, $payload['translation']);

            return null;
        }

        return null;
    }

    /**
     * Inserts or updates a product translation for a given locale.
     *
     * @param array<string, mixed> $tr
     */
    private function upsertTranslation(Product $product, string $locale, array $tr): void
    {
        $translation = $product->getTranslation($locale);

        if ($translation === null) {
            $translation = new ProductTranslation();
            $translation->setLocale($locale);
            $product->addTranslation($translation);
            $this->em->persist($translation);
        }

        if (array_key_exists('name', $tr)) {
            $translation->setName($tr['name'] === null ? null : (string) $tr['name']);
        }

        if (array_key_exists('description', $tr)) {
            $translation->setDescription($tr['description'] === null ? null : (string) $tr['description']);
        }

        if (array_key_exists('material', $tr)) {
            $translation->setMaterial($tr['material'] === null ? null : (string) $tr['material']);
        }

        if (array_key_exists('attributes', $tr)) {
            $translation->setAttributes(is_array($tr['attributes']) ? $tr['attributes'] : []);
        }

        if ($locale === 'en') {
            $name = $translation->getName();
            if (is_string($name) && trim($name) !== '') {
                $product->setName($name);
            }

            if ($translation->getDescription() !== null) {
                $product->setDescription($translation->getDescription());
            }

            if ($translation->getMaterial() !== null) {
                $product->setMaterial($translation->getMaterial());
            }
        }
    }

    /**
     * Finds an entity by id or returns null when id is null/empty/"null".
     *
     * @param class-string $class
     */
    private function findNullable(string $class, mixed $id, string $fieldName): ?object
    {
        if ($id === null || $id === '' || (is_string($id) && strtolower($id) === 'null')) {
            return null;
        }

        if (!is_numeric($id)) {
            throw new InvalidArgumentException(sprintf('%s must be numeric or null', $fieldName));
        }

        return $this->em->getRepository($class)->find((int) $id);
    }

    /**
     * Finds an entity by id, id must be numeric.
     *
     * @param class-string $class
     */
    private function findRequired(string $class, mixed $id, string $fieldName): ?object
    {
        if (!is_numeric($id)) {
            throw new InvalidArgumentException(sprintf('%s must be numeric', $fieldName));
        }

        return $this->em->getRepository($class)->find((int) $id);
    }
}