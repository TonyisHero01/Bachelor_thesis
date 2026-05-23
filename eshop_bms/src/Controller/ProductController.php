<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Color;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\Size;
use App\Form\ProductType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Twig\Environment;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[IsGranted('ROLE_WAREHOUSE_MANAGER')]
final class ProductController extends BaseController
{
    public function __construct(
        Environment $twig,
        LoggerInterface $logger,
        ManagerRegistry $doctrine,
    ) {
        parent::__construct($twig, $logger, $doctrine);
    }

    /**
     * @param Product[] $products
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildProductsForView(array $products, string $locale): array
    {
        return array_map(
            static function (Product $product) use ($locale): array {
                return [
                    'id' => $product->getId(),
                    'name' => $product->getTranslatedName($locale),
                    'category' => $product->getCategory()?->getName() ?? '',
                    'colorName' => $product->getColor()?->getTranslatedName($locale) ?? '',
                    'sizeName' => $product->getSize()?->getName() ?? '',
                    'numberInStock' => $product->getNumberInStock(),
                    'createdAt' => $product->getCreatedAt(),
                    'price' => $product->getPrice(),
                    'hidden' => $product->getHidden(),
                ];
            },
            $products
        );
    }

    private function notifyReindex(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $event,
        array $payload = [],
        string $mode = 'partial',
        ?string $sku = null
    ): void {
        $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

        if ($baseUrl === '') {
            $logger->warning('[ProductController] SEARCH_SERVICE_BASE_URL is empty, skipping reindex.');
            return;
        }

        $apiKey = (string) $this->getParameter('search_api_key');

        $body = [
            'mode' => $mode,
            'reason' => $event,
            'context' => $payload,
        ];

        if ($mode === 'partial') {
            $sku = trim((string) ($sku ?? ($payload['sku'] ?? '')));

            if ($sku === '') {
                $logger->warning('[ProductController] Missing SKU for partial reindex.', [
                    'event' => $event,
                    'payload' => $payload,
                ]);
                return;
            }

            $body['sku'] = $sku;
        }

        try {
            $response = $httpClient->request('POST', $baseUrl . '/reindex', [
                'headers' => [
                    'X-API-KEY' => $apiKey,
                ],
                'json' => $body,
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $logger->error('[ProductController] Reindex request failed.', [
                    'statusCode' => $statusCode,
                    'event' => $event,
                    'mode' => $mode,
                    'sku' => $sku,
                    'payload' => $payload,
                    'response' => $response->getContent(false),
                ]);

                return;
            }

            $logger->info('[ProductController] Search index updated.', [
                'event' => $event,
                'mode' => $mode,
                'sku' => $sku,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            $logger->error('[ProductController] Failed to notify search-service: ' . $e->getMessage(), [
                'event' => $event,
                'mode' => $mode,
                'sku' => $sku,
                'payload' => $payload,
            ]);
        }
    }

    #[Route('/bms/product_create', name: 'create_product', methods: ['POST'])]
    public function createProduct(
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
    ): Response {
        $input = json_decode((string) $request->getContent(), true);
        if (!is_array($input)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON',
                'message' => 'Invalid request body.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $sku = trim((string) ($input['sku'] ?? ''));
        if ($sku === '' || mb_strlen($sku) > 64) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid SKU',
                'message' => 'SKU cannot be empty and must be at most 64 characters.',
                'field' => 'sku',
            ], Response::HTTP_BAD_REQUEST);
        }

        $existing = $em->getRepository(Product::class)->findOneBy(['sku' => $sku]);
        if ($existing !== null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'SKU already exists',
                'message' => sprintf('Product with SKU "%s" already exists. Please use another SKU.', $sku),
                'field' => 'sku',
            ], Response::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($input['name'] ?? 'Unnamed Product'));
        $numberInStock = (int) ($input['number_in_stock'] ?? 0);
        $price = (float) ($input['price'] ?? 0.0);
        $currencyId = $input['currency_id'] ?? null;

        $currency = $currencyId !== null
            ? $em->getRepository(Currency::class)->find((int) $currencyId)
            : $em->getRepository(Currency::class)->findOneBy(['isDefault' => true]);

        if ($currency === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Currency not found',
                'message' => 'Default currency was not found. Please configure a default currency first.',
                'field' => 'currency_id',
            ], Response::HTTP_BAD_REQUEST);
        }

        $now = new DateTimeImmutable();

        $product = (new Product())
            ->setName($name)
            ->setNumberInStock($numberInStock)
            ->setPrice($price)
            ->setSku($sku)
            ->setCurrency($currency)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setVersion(1)
            ->setHidden(false);

        $em->persist($product);
        $em->flush();

        $this->notifyReindex($httpClient, $logger, 'product_create', [
            'sku' => $sku,
            'productId' => $product->getId(),
        ], 'partial', $sku);

        return new JsonResponse(['success' => true, 'id' => $product->getId()]);
    }

    /**
     * Displays detailed information for a single product by ID.
     */
    #[Route('/bms/product/{id}', name: 'show_product', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(EntityManagerInterface $em, int $id): Response
    {
        $product = $em->getRepository(Product::class)->findProductById($id);
        if ($product === null) {
            throw $this->createNotFoundException('No product found for id ' . $id);
        }

        return $this->renderLocalized('product/product_show.html.twig', [
            'product' => $product,
        ]);
    }

    private function getSearchMethodInfo(EntityManagerInterface $em): array
    {
        $searchConfig = $em
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(['active' => true], ['id' => 'DESC']);

        $method = $searchConfig?->getSearchMethod() ?? 'tfidf';

        $label = match ($method) {
            'semantic_vector' => 'Semantic Vector',
            'elasticsearch_bm25' => 'Elasticsearch BM25',
            default => 'TF-IDF',
        };

        $endpoint = match ($method) {
            'semantic_vector' => '/semantic/search',
            'elasticsearch_bm25' => '/elastic/search',
            default => '/search',
        };

        return [
            'config' => $searchConfig,
            'method' => $method,
            'label' => $label,
            'endpoint' => $endpoint,
        ];
    }

    /**
     * Lists all latest-version products.
     */
    #[Route('/bms/product_list', name: 'show_All_products', methods: ['GET'])]
    public function showAllProducts(EntityManagerInterface $em, Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));

        $limit = (int) $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE');
        $limit = $limit > 0 ? $limit : 10;

        $offset = ($page - 1) * $limit;

        $productRepository = $em->getRepository(Product::class);

        $products = $productRepository->findLatestVersionProductsPaged(
            limit: $limit,
            offset: $offset
        );

        $totalProducts = $productRepository->countLatestVersionProducts();
        $totalPages = max(1, (int) ceil($totalProducts / $limit));
        $form = $this->createForm(ProductType::class, new Product());

        $colors = $em->getRepository(Color::class)->findAll();
        $sizes = $em->getRepository(Size::class)->findAll();
        $categories = $em->getRepository(Category::class)->findAll();

        $locale = strtolower((string) $request->query->get('_locale', $request->getLocale()));
        $productsForView = $this->buildProductsForView($products, $locale);
        $searchInfo = $this->getSearchMethodInfo($em);

        return $this->renderLocalized('product/product_list.html.twig', [
            'products' => $productsForView,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'form' => $form,
            'colors' => $colors,
            'sizes' => $sizes,
            'categories' => $categories,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $totalProducts,
            'searchMethodLabel' => $searchInfo['label'],
            'searchMethod' => $searchInfo['method'],
        ]);
    }

    /**
     * Displays the product edit page and product history by SKU.
     */
    #[Route('/bms/product_edit/{id}', name: 'edit_product', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(EntityManagerInterface $em, Request $request, int $id): Response
    {
        $productRepository = $em->getRepository(Product::class);
        $product = $productRepository->find($id);

        if ($product === null) {
            throw $this->createNotFoundException('Product not found');
        }

        $productsWithSameSku = $productRepository->findBy(
            ['sku' => $product->getSku()],
            ['version' => 'DESC']
        );

        $categories = $em->getRepository(Category::class)->findAllCategories();
        $colors = $em->getRepository(Color::class)->findAll();
        $sizes = $em->getRepository(Size::class)->findAll();

        $defaultCurrency = $em->getRepository(Currency::class)->findOneBy(['isDefault' => true]);
        $defaultCurrencyCode = $defaultCurrency !== null
            ? (string) $defaultCurrency->getName()
            : 'CZK';

        $locale = strtolower((string) $request->query->get('_locale', $request->getLocale()));

        return $this->renderLocalized('product/product_edit.html.twig', [
            'product' => $product,
            'productsWithSameSku' => $productsWithSameSku,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'categories' => $categories,
            'colors' => $colors,
            'sizes' => $sizes,
            'locale' => $locale,
            'defaultCurrencyCode' => $defaultCurrencyCode,
        ]);
    }

    #[Route('/bms/product_save/{id}', name: 'save_product', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveProduct(
        Request $request,
        EntityManagerInterface $em,
        int $id,
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
    ): Response {
        try {
            $productRepository = $em->getRepository(Product::class);
            $currentProduct = $productRepository->find($id);

            if ($currentProduct === null) {
                return new JsonResponse(['status' => 'Error', 'message' => 'Product not found'], 404);
            }

            $data = json_decode((string) $request->getContent(), true);
            if (!is_array($data)) {
                return new JsonResponse(['status' => 'Error', 'message' => 'Invalid JSON'], 400);
            }

            $noVersionUpdate = filter_var($data['no_version_update'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($noVersionUpdate) {
                $currentProduct->setName($data['name'] ?? $currentProduct->getName());
                $currentProduct->setDescription($data['description'] ?? $currentProduct->getDescription());
                $currentProduct->setNumberInStock($data['number_in_stock'] ?? $currentProduct->getNumberInStock());
                $currentProduct->setPrice($data['price'] ?? $currentProduct->getPrice());
                $currentProduct->setDiscount($data['discount'] ?? $currentProduct->getDiscount());
                $currentProduct->setTaxRate($data['tax_rate'] ?? $currentProduct->getTaxRate());
                $currentProduct->setMaterial($data['material'] ?? $currentProduct->getMaterial());
                $currentProduct->setAttributes($data['attributes'] ?? $currentProduct->getAttributes());

                $currentProduct->setWidth(isset($data['width']) ? (float) $data['width'] : $currentProduct->getWidth());
                $currentProduct->setHeight(isset($data['height']) ? (float) $data['height'] : $currentProduct->getHeight());
                $currentProduct->setLength(isset($data['length']) ? (float) $data['length'] : $currentProduct->getLength());
                $currentProduct->setWeight(isset($data['weight']) ? (float) $data['weight'] : $currentProduct->getWeight());

                if (!empty($data['size'])) {
                    $size = $em->getRepository(Size::class)->find((int) $data['size']);
                    if ($size !== null) {
                        $currentProduct->setSize($size);
                    }
                }

                if (!empty($data['color'])) {
                    $color = $em->getRepository(Color::class)->find((int) $data['color']);
                    if ($color !== null) {
                        $currentProduct->setColor($color);
                    }
                }

                if (!empty($data['category'])) {
                    $category = $em->getRepository(Category::class)->find((int) $data['category']);
                    if ($category !== null) {
                        $currentProduct->setCategory($category);
                    }
                }

                if (array_key_exists('image_urls', $data)) {
                    $validUrls = array_values(array_filter(
                        (array) $data['image_urls'],
                        static fn ($url): bool => !empty($url)
                    ));
                    $currentProduct->setImageUrls($validUrls);
                }

                if (array_key_exists('hidden', $data)) {
                    $currentProduct->setHidden((bool) $data['hidden']);
                }

                $currentProduct->setUpdatedAt(new DateTimeImmutable());
                $em->flush();

                $this->notifyReindex($httpClient, $logger, 'product_update_in_place', [
                    'id' => $currentProduct->getId(),
                    'sku' => $currentProduct->getSku(),
                    'noVersionUpdate' => true,
                ], 'partial', $currentProduct->getSku());

                return new JsonResponse(['status' => 'Success', 'message' => 'Updated current version']);
            }

            $maxVersion = (int) $em->createQueryBuilder()
                ->select('MAX(p.version)')
                ->from(Product::class, 'p')
                ->where('p.sku = :sku')
                ->setParameter('sku', $currentProduct->getSku())
                ->getQuery()
                ->getSingleScalarResult();

            $newProduct = new Product();
            $newProduct->setSku($currentProduct->getSku());
            $newProduct->setVersion($maxVersion + 1);
            $newProduct->setCreatedAt($currentProduct->getCreatedAt());

            $newProduct->setName($data['name'] ?? $currentProduct->getName());
            $newProduct->setDescription($data['description'] ?? $currentProduct->getDescription());
            $newProduct->setNumberInStock($data['number_in_stock'] ?? $currentProduct->getNumberInStock());
            $newProduct->setPrice($data['price'] ?? $currentProduct->getPrice());
            $newProduct->setDiscount($data['discount'] ?? $currentProduct->getDiscount());
            $newProduct->setTaxRate($data['tax_rate'] ?? $currentProduct->getTaxRate());
            $newProduct->setMaterial($data['material'] ?? $currentProduct->getMaterial());
            $newProduct->setAttributes($data['attributes'] ?? []);

            $newProduct->setWidth(isset($data['width']) ? (float) $data['width'] : $currentProduct->getWidth());
            $newProduct->setHeight(isset($data['height']) ? (float) $data['height'] : $currentProduct->getHeight());
            $newProduct->setLength(isset($data['length']) ? (float) $data['length'] : $currentProduct->getLength());
            $newProduct->setWeight(isset($data['weight']) ? (float) $data['weight'] : $currentProduct->getWeight());

            if (!empty($data['category'])) {
                $category = $em->getRepository(Category::class)->find((int) $data['category']);
                $newProduct->setCategory($category);
            } else {
                $newProduct->setCategory($currentProduct->getCategory());
            }

            if (!empty($data['size'])) {
                $size = $em->getRepository(Size::class)->find((int) $data['size']);
                $newProduct->setSize($size);
            } else {
                $newProduct->setSize($currentProduct->getSize());
            }

            if (!empty($data['color'])) {
                $color = $em->getRepository(Color::class)->find((int) $data['color']);
                $newProduct->setColor($color);
            } else {
                $newProduct->setColor($currentProduct->getColor());
            }

            $currency = $em->getRepository(Currency::class)->findOneBy(['isDefault' => true]);
            $newProduct->setCurrency($currency);

            $newProduct->setHidden((bool) ($data['hidden'] ?? false));

            $imageUrls = array_values(array_filter((array) ($data['image_urls'] ?? []), static fn ($u): bool => !empty($u)));
            $existingUrls = $currentProduct->getImageUrls() ?? [];
            $newProduct->setImageUrls(array_values(array_merge($existingUrls, $imageUrls)));

            $newProduct->setUpdatedAt(
                isset($data['edit_time']) ? new DateTimeImmutable((string) $data['edit_time']) : new DateTimeImmutable()
            );

            $em->persist($newProduct);
            $em->flush();

            $this->notifyReindex($httpClient, $logger, 'product_new_version', [
                'oldId' => $currentProduct->getId(),
                'newId' => $newProduct->getId(),
                'sku' => $currentProduct->getSku(),
                'noVersionUpdate' => false,
            ], 'partial', $currentProduct->getSku());

            return new JsonResponse(['status' => 'Success', 'new_product_id' => $newProduct->getId()]);
        } catch (\Throwable $e) {
            $logger->error('Error in saveProduct: ' . $e->getMessage());
            return new JsonResponse(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/bms/product_delete/{id}', name: 'delete_product', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteProduct(
        int $id,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
    ): Response {
        $productRepository = $em->getRepository(Product::class);
        $product = $productRepository->find($id);

        if ($product === null) {
            return new JsonResponse(['success' => false, 'message' => 'Product not found'], 404);
        }

        $sku = (string) $product->getSku();

        $productsWithSameSku = $productRepository->findBy(['sku' => $sku]);
        foreach ($productsWithSameSku as $productToDelete) {
            $em->remove($productToDelete);
        }

        $em->flush();

        $this->notifyReindex($httpClient, $logger, 'product_delete', [
            'sku' => $sku,
            'deletedVersions' => count($productsWithSameSku),
        ], 'partial', $sku);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Uploads product images and appends filenames to product imageUrls.
     */
    #[Route('/image_save/{id}', name: 'save_image', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveImage(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ): Response {
        $logger->info('saveImage started.', [
            'productId' => $id,
            'method' => $request->getMethod(),
            'contentType' => $request->headers->get('content-type'),
            'requestFields' => array_keys($request->request->all()),
            'fileFields' => array_keys($request->files->all()),
        ]);

        $product = $em->getRepository(Product::class)->find($id);
        if ($product === null) {
            $logger->warning('saveImage failed: product not found.', [
                'productId' => $id,
            ]);

            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        $uploadedFiles = [];

        foreach (['images', 'image', 'file'] as $fieldName) {
            $value = $request->files->get($fieldName);

            $logger->info('Checking upload field.', [
                'fieldName' => $fieldName,
                'exists' => $value !== null,
                'type' => is_object($value) ? get_class($value) : gettype($value),
                'isArray' => is_array($value),
            ]);

            if ($value instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $uploadedFiles[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $index => $item) {
                    $logger->info('Checking upload array item.', [
                        'fieldName' => $fieldName,
                        'index' => $index,
                        'type' => is_object($item) ? get_class($item) : gettype($item),
                    ]);

                    if ($item instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                        $uploadedFiles[] = $item;
                    }
                }
            }
        }

        $logger->info('Uploaded files collected.', [
            'count' => count($uploadedFiles),
            'allFileFields' => array_keys($request->files->all()),
        ]);

        if ($uploadedFiles === []) {
            $logger->warning('saveImage failed: no uploaded files collected.', [
                'allFiles' => $request->files->all(),
            ]);

            return new JsonResponse([
                'error' => 'No files received',
                'receivedFileFields' => array_keys($request->files->all()),
            ], 400);
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $imagesDir = (string) $this->getParameter('images_directory');

        $logger->info('Image directory status.', [
            'imagesDir' => $imagesDir,
            'exists' => is_dir($imagesDir),
            'isWritable' => is_writable($imagesDir),
            'owner' => is_dir($imagesDir) ? fileowner($imagesDir) : null,
            'permissions' => is_dir($imagesDir) ? substr(sprintf('%o', fileperms($imagesDir)), -4) : null,
        ]);

        if ($imagesDir === '') {
            $logger->error('saveImage failed: images_directory is empty.');
            return new JsonResponse(['error' => 'images_directory is not configured'], 500);
        }

        if (!is_dir($imagesDir) && !mkdir($imagesDir, 0775, true) && !is_dir($imagesDir)) {
            $logger->error('saveImage failed: failed to create images directory.', [
                'imagesDir' => $imagesDir,
            ]);

            return new JsonResponse(['error' => 'Failed to create images directory'], 500);
        }

        if (!is_writable($imagesDir)) {
            $logger->error('saveImage failed: images directory is not writable.', [
                'imagesDir' => $imagesDir,
                'permissions' => substr(sprintf('%o', fileperms($imagesDir)), -4),
            ]);

            return new JsonResponse(['error' => 'Images directory is not writable'], 500);
        }

        $slugger = new AsciiSlugger();
        $safeBase = strtolower((string) $slugger->slug((string) ($product->getSku() ?: ('product-' . $product->getId()))));

        $existing = $product->getImageUrls() ?? [];
        $new = [];

        foreach ($uploadedFiles as $index => $file) {
            $logger->info('Processing uploaded file.', [
                'index' => $index,
                'originalName' => $file->getClientOriginalName(),
                'clientMimeType' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'error' => $file->getError(),
                'errorMessage' => $file->getErrorMessage(),
                'isValid' => $file->isValid(),
                'tmpPath' => $file->getPathname(),
                'tmpReadable' => $file->getPathname() ? is_readable($file->getPathname()) : false,
            ]);

            if (!$file->isValid()) {
                $logger->warning('Skipping invalid uploaded image.', [
                    'index' => $index,
                    'originalName' => $file->getClientOriginalName(),
                    'error' => $file->getError(),
                    'errorMessage' => $file->getErrorMessage(),
                ]);

                continue;
            }

            $tmpPath = $file->getPathname();

            if (!$tmpPath || !is_readable($tmpPath)) {
                $logger->error('Uploaded file temporary path is empty or unreadable.', [
                    'index' => $index,
                    'originalName' => $file->getClientOriginalName(),
                    'clientMimeType' => $file->getClientMimeType(),
                    'error' => $file->getError(),
                    'errorMessage' => $file->getErrorMessage(),
                    'path' => $tmpPath,
                ]);

                return new JsonResponse(['error' => 'Uploaded file is not readable'], 400);
            }

            $originalName = $file->getClientOriginalName();
            $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

            if ($ext === '') {
                $mimeType = (string) $file->getClientMimeType();

                $ext = match ($mimeType) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => '',
                };
            }

            $logger->info('Detected uploaded file extension.', [
                'index' => $index,
                'originalName' => $originalName,
                'extension' => $ext,
            ]);

            if (!in_array($ext, $allowedExt, true)) {
                $logger->warning('Unsupported uploaded file type.', [
                    'index' => $index,
                    'filename' => $originalName,
                    'extension' => $ext,
                    'allowed' => $allowedExt,
                ]);

                return new JsonResponse([
                    'error' => 'Unsupported file type',
                    'filename' => $originalName,
                    'extension' => $ext,
                ], 400);
            }

            $filename = sprintf('%s_%s.%s', $safeBase, bin2hex(random_bytes(8)), $ext);

            try {
                $logger->info('Moving uploaded file.', [
                    'index' => $index,
                    'targetDir' => $imagesDir,
                    'filename' => $filename,
                ]);

                $file->move($imagesDir, $filename);

                $logger->info('Uploaded file moved successfully.', [
                    'index' => $index,
                    'savedAs' => $filename,
                    'fullPath' => $imagesDir . DIRECTORY_SEPARATOR . $filename,
                    'existsAfterMove' => file_exists($imagesDir . DIRECTORY_SEPARATOR . $filename),
                ]);
            } catch (\Throwable $e) {
                $logger->error('saveImage failed while moving file.', [
                    'message' => $e->getMessage(),
                    'targetDir' => $imagesDir,
                    'filename' => $filename,
                    'originalName' => $originalName,
                ]);

                return new JsonResponse(['error' => 'Upload failed'], 500);
            }

            $new[] = $filename;
        }

        if ($new === []) {
            $logger->warning('saveImage failed: no valid files uploaded after processing.', [
                'productId' => $id,
            ]);

            return new JsonResponse(['error' => 'No valid files uploaded'], 400);
        }

        $merged = array_values(array_merge($existing, $new));
        $product->setImageUrls($merged);
        $em->flush();

        $logger->info('saveImage completed successfully.', [
            'productId' => $id,
            'newFiles' => $new,
            'totalImageCount' => count($merged),
        ]);

        return new JsonResponse([
            'status' => 'success',
            'filePaths' => $new,
        ]);
    }

    /**
     * Removes an image filename from the product and deletes the file from disk.
     */
    #[Route('/delete_image/{id}', name: 'delete_image', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteImage(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $imageUrl = (string) ($data['imageUrl'] ?? '');
        if ($imageUrl === '') {
            return new JsonResponse(['error' => 'Missing imageUrl'], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9._-]+\.(jpg|jpeg|png|webp)$/', $imageUrl)) {
            return new JsonResponse(['error' => 'Invalid filename'], 400);
        }

        $product = $em->getRepository(Product::class)->find($id);
        if ($product === null) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        $imageUrls = $product->getImageUrls() ?? [];
        if (!in_array($imageUrl, $imageUrls, true)) {
            return new JsonResponse(['error' => 'Image not attached to product'], 404);
        }

        $product->setImageUrls(array_values(array_filter(
            $imageUrls,
            static fn (string $u): bool => $u !== $imageUrl
        )));
        $em->flush();

        $imagesDir = (string) $this->getParameter('images_directory');
        $fullPath = $imagesDir . DIRECTORY_SEPARATOR . $imageUrl;

        $filesystem = new Filesystem();
        if ($filesystem->exists($fullPath)) {
            $filesystem->remove($fullPath);
        }

        return new JsonResponse(['status' => 'success']);
    }

}