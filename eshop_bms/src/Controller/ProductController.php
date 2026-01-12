<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Color;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\Size;
use App\Form\ProductType;
use App\Service\TfidfTrainer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Process\Process;
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

    #[Route('/bms/product_create', name: 'create_product', methods: ['POST'])]
    public function createProduct(
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
    ): Response {
        $input = json_decode((string) $request->getContent(), true);
        if (!is_array($input)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $sku = trim((string) ($input['sku'] ?? ''));
        if ($sku === '' || mb_strlen($sku) > 64) {
            return new JsonResponse(['error' => 'Invalid SKU'], 400);
        }

        $existing = $em->getRepository(Product::class)->findOneBy(['sku' => $sku]);
        if ($existing !== null) {
            return new JsonResponse(['error' => 'SKU already exists'], 400);
        }

        $name = trim((string) ($input['name'] ?? 'Unnamed Product'));
        $numberInStock = (int) ($input['number_in_stock'] ?? 0);
        $price = (float) ($input['price'] ?? 0.0);
        $currencyId = $input['currency_id'] ?? null;

        $currency = $currencyId !== null
            ? $em->getRepository(Currency::class)->find((int) $currencyId)
            : $em->getRepository(Currency::class)->findOneBy(['isDefault' => true]);

        if ($currency === null) {
            return new JsonResponse(['error' => 'Currency not found'], 400);
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
        ]);

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

    /**
     * Lists all latest-version products.
     */
    #[Route('/bms/product_list', name: 'show_All_products', methods: ['GET'])]
    public function showAllProducts(EntityManagerInterface $em, Request $request): Response
    {
        $products = $em->getRepository(Product::class)->findLatestVersionProducts();
        $form = $this->createForm(ProductType::class, new Product());

        $colors = $em->getRepository(Color::class)->findAll();
        $sizes = $em->getRepository(Size::class)->findAll();
        $categories = $em->getRepository(Category::class)->findAll();

        $locale = $request->getLocale();
        $productsForView = $this->buildProductsForView($products, $locale);

        return $this->renderLocalized('product/product_list.html.twig', [
            'products' => $productsForView,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'form' => $form,
            'colors' => $colors,
            'sizes' => $sizes,
            'categories' => $categories,
        ]);
    }

    /**
     * Modifies the category name and triggers TF-IDF retraining.
     */
    #[Route('/bms/modify_category', name: 'modify_category', methods: ['POST'])]
    public function modifyCategory(
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
    ): JsonResponse {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $id = $data['id'] ?? null;
        $newName = $data['new_name'] ?? null;

        if ($id === null || $newName === null || trim((string) $newName) === '') {
            return new JsonResponse(['success' => false, 'message' => 'Missing ID or new name'], 400);
        }

        $category = $em->getRepository(Category::class)->find((int) $id);
        if ($category === null) {
            return new JsonResponse(['success' => false, 'message' => 'Category not found'], 404);
        }

        $oldName = (string) $category->getName();
        $category->setName((string) $newName);
        $em->flush();

        // ✅ notify reindex
        $this->notifyReindex($httpClient, $logger, 'category_modify', [
            'categoryId' => (int) $id,
            'oldName' => $oldName,
            'newName' => (string) $newName,
        ]);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Displays the product edit page and product history by SKU.
     */
    #[Route('/bms/product_edit/{id}', name: 'edit_product', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(EntityManagerInterface $em, int $id): Response
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

        return $this->renderLocalized('product/product_edit.html.twig', [
            'product' => $product,
            'productsWithSameSku' => $productsWithSameSku,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'categories' => $categories,
            'colors' => $colors,
            'sizes' => $sizes,
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

                // ✅ notify reindex
                $this->notifyReindex($httpClient, $logger, 'product_update_in_place', [
                    'id' => $currentProduct->getId(),
                    'sku' => $currentProduct->getSku(),
                    'noVersionUpdate' => true,
                ]);

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

            // ✅ notify reindex
            $this->notifyReindex($httpClient, $logger, 'product_new_version', [
                'oldId' => $currentProduct->getId(),
                'newId' => $newProduct->getId(),
                'sku' => $currentProduct->getSku(),
                'noVersionUpdate' => false,
            ]);

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
        ]);

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
        $product = $em->getRepository(Product::class)->find($id);
        if ($product === null) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        $files = $request->files->all('images');
        if (empty($files)) {
            return new JsonResponse(['error' => 'No files received'], 400);
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $imagesDir = (string) $this->getParameter('images_directory');

        $slugger = new AsciiSlugger();
        $safeBase = strtolower((string) $slugger->slug((string) ($product->getSku() ?: ('product-' . $product->getId()))));

        $existing = $product->getImageUrls() ?? [];
        $new = [];

        foreach ($files as $file) {
            if ($file === null) {
                continue;
            }

            $ext = strtolower((string) $file->guessExtension());
            if (!in_array($ext, $allowedExt, true)) {
                return new JsonResponse(['error' => 'Unsupported file type'], 400);
            }

            $filename = sprintf('%s_%s.%s', $safeBase, bin2hex(random_bytes(8)), $ext);

            try {
                $file->move($imagesDir, $filename);
            } catch (\Throwable $e) {
                $logger->error('saveImage failed: ' . $e->getMessage());
                return new JsonResponse(['error' => 'Upload failed'], 500);
            }

            $new[] = $filename;
        }

        if ($new === []) {
            return new JsonResponse(['error' => 'No valid files uploaded'], 400);
        }

        $product->setImageUrls(array_values(array_merge($existing, $new)));
        $em->flush();

        return new JsonResponse(['filePaths' => $new]);
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

    /**
     * Executes TF-IDF search via python-api and stores ranked results in session.
     */
    #[Route('/bms/search', name: 'bms_search', methods: ['POST'])]
    public function search(
        EntityManagerInterface $em,
        SessionInterface $session,
        LoggerInterface $logger,
        Request $request,
        HttpClientInterface $httpClient,
    ): Response {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $query = trim((string) ($payload['query'] ?? ''));
        if ($query === '') {
            return new JsonResponse(['error' => 'Empty search query'], 400);
        }

        if (mb_strlen($query) > 200) {
            return new JsonResponse(['error' => 'Query too long'], 400);
        }

        $baseUrl = (string) $this->getParameter('python_api_base_url');
        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl === '') {
            $logger->error('PYTHON_API_BASE_URL is empty');
            return new JsonResponse(['error' => 'Search backend not available'], 500);
        }

        try {
            $response = $httpClient->request('POST', $baseUrl . '/search', [
                'json' => [
                    'query' => $query,
                    'limit' => 200,
                ],
                'timeout' => 5,
            ]);
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $logger->error('python-api request failed: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Search system error'], 500);
        }

        if (!is_array($data) || !isset($data['results']) || !is_array($data['results'])) {
            $logger->error('python-api invalid response: ' . json_encode($data));
            return new JsonResponse(['error' => 'Search system error'], 500);
        }

        $skuToSimilarity = [];
        foreach ($data['results'] as $result) {
            if (!is_array($result)) {
                continue;
            }
            if (!isset($result['product_sku'], $result['similarity'])) {
                continue;
            }
            $sku = (string) $result['product_sku'];
            $sim = (float) $result['similarity'];
            $skuToSimilarity[$sku] = $sim;
        }

        $productSkus = array_keys($skuToSimilarity);
        if ($productSkus === []) {
            $session->set('search_results', []);
            return new JsonResponse(['results' => []]);
        }
        $qb = $em->createQueryBuilder();
        $qb->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where($qb->expr()->in('p.sku', ':skus'))
            ->setParameter('skus', $productSkus)
            ->orderBy('p.id', 'DESC');

        $rows = $qb->getQuery()->getArrayResult();

        $skuToLatestId = [];
        foreach ($rows as $row) {
            $sku = (string) $row['sku'];
            if (!isset($skuToLatestId[$sku])) {
                $skuToLatestId[$sku] = (int) $row['id'];
            }
        }

        $sortedResults = [];
        foreach ($productSkus as $sku) {
            if (isset($skuToLatestId[$sku])) {
                $sortedResults[] = [
                    'id' => $skuToLatestId[$sku],
                    'similarity' => $skuToSimilarity[$sku],
                ];
            }
        }

        $session->set('search_results', $sortedResults);

        return new JsonResponse(['results' => $sortedResults]);
    }

    /**
     * Displays the product search results stored in session.
     */
    #[Route('/bms/results', name: 'results', methods: ['GET'])]
    public function results(SessionInterface $session, EntityManagerInterface $em, Request $request): Response
    {
        $searchResults = $session->get('search_results', []);
        if (empty($searchResults)) {
            return $this->renderLocalized('product/no_results.html.twig', []);
        }

        $ids = array_column($searchResults, 'id');

        /** @var Product[] $products */
        $products = $em->getRepository(Product::class)->findBy(['id' => $ids]);

        $productsById = [];
        foreach ($products as $product) {
            $productsById[$product->getId()] = $product;
        }

        $sortedProducts = [];
        foreach ($ids as $id) {
            if (isset($productsById[$id])) {
                $sortedProducts[] = $productsById[$id];
            }
        }

        $form = $this->createForm(ProductType::class, new Product());
        $colors = $em->getRepository(Color::class)->findAll();
        $sizes = $em->getRepository(Size::class)->findAll();
        $categories = $em->getRepository(Category::class)->findAll();

        $locale = $request->getLocale();
        $productsForView = $this->buildProductsForView($sortedProducts, $locale);

        return $this->renderLocalized('product/product_list.html.twig', [
            'products' => $productsForView,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'form' => $form,
            'colors' => $colors,
            'sizes' => $sizes,
            'categories' => $categories,
        ]);
    }

    /**
     * Creates a new category.
     */
    #[Route('/bms/save_category', name: 'save_category', methods: ['POST'])]
    public function createCategory(
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
    ): Response {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data) || empty($data['name'])) {
            return new JsonResponse(['error' => 'Invalid JSON or missing name'], 400);
        }

        $name = trim((string) $data['name']);
        if ($name === '') {
            return new JsonResponse(['error' => 'Category name cannot be empty'], 400);
        }

        $category = (new Category())->setName($name);

        $em->persist($category);
        $em->flush();

        $this->notifyReindex($httpClient, $logger, 'category_create', [
            'categoryId' => $category->getId(),
            'name' => $name,
        ]);

        return new JsonResponse([]);
    }

    /**
     * Creates a new color.
     */
    #[Route('/bms/save_color', name: 'save_color', methods: ['POST'])]
    public function createColor(Request $request, EntityManagerInterface $em): Response
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data) || empty($data['name']) || empty($data['hex'])) {
            return new JsonResponse(['error' => 'Invalid JSON or missing fields'], 400);
        }

        $color = (new Color())
            ->setName((string) $data['name'])
            ->setHex((string) $data['hex']);

        $em->persist($color);
        $em->flush();

        return new JsonResponse([]);
    }

    /**
     * Modifies an existing color.
     */
    #[Route('/bms/modify_color', name: 'modify_color', methods: ['POST'])]
    public function modifyColor(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid JSON'], 400);
        }

        $colorId = $data['id'] ?? null;
        $newName = $data['new_name'] ?? null;
        $newHex = $data['new_hex'] ?? null;

        if ($colorId === null || $newName === null || $newHex === null) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid data'], 400);
        }

        $color = $em->getRepository(Color::class)->find((int) $colorId);
        if ($color === null) {
            return new JsonResponse(['status' => 'error', 'message' => 'Color not found'], 404);
        }

        $color->setName((string) $newName);
        $color->setHex((string) $newHex);
        $em->flush();

        return new JsonResponse(['status' => 'success']);
    }

    /**
     * Creates a new size.
     */
    #[Route('/bms/create_size', name: 'size_create', methods: ['POST'])]
    public function createSize(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON.'], 400);
        }

        $sizeName = trim((string) ($data['name'] ?? ''));
        if ($sizeName === '') {
            return new JsonResponse(['success' => false, 'message' => 'Size name cannot be empty.'], 400);
        }

        $size = (new Size())->setName($sizeName);

        $em->persist($size);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Modifies an existing size name.
     */
    #[Route('/bms/modify_size/{id}', name: 'size_modify', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function modifySize(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON.'], 400);
        }

        $newSizeName = trim((string) ($data['name'] ?? ''));
        if ($newSizeName === '') {
            return new JsonResponse(['success' => false, 'message' => 'New size name cannot be empty.'], 400);
        }

        $size = $em->getRepository(Size::class)->find($id);
        if ($size === null) {
            return new JsonResponse(['success' => false, 'message' => 'Size not found.'], 404);
        }

        $size->setName($newSizeName);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Notify python-api to rebuild TF-IDF vectors.
     * Failure should NOT break main business flow.
     */
    private function notifyReindex(HttpClientInterface $httpClient, LoggerInterface $logger, string $reason, array $context = []): void
    {
        $baseUrl = (string) $this->getParameter('python_api_base_url');
        $baseUrl = rtrim($baseUrl, '/');

        if ($baseUrl === '') {
            $logger->warning('[TFIDF] python_api_base_url is empty, skip reindex', [
                'reason' => $reason,
                'context' => $context,
            ]);
            return;
        }

        try {
            $resp = $httpClient->request('POST', $baseUrl . '/reindex', [
                'json' => ['mode' => 'full'],
                'timeout' => 10,
            ]);

            $status = $resp->getStatusCode();
            $body = $resp->toArray(false);

            if ($status < 200 || $status >= 300) {
                $logger->error('[TFIDF] reindex non-2xx', [
                    'status' => $status,
                    'body' => $body,
                    'reason' => $reason,
                    'context' => $context,
                ]);
                return;
            }

            $logger->info('[TFIDF] reindex ok', [
                'status' => $status,
                'body' => $body,
                'reason' => $reason,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            $logger->error('[TFIDF] reindex request failed', [
                'msg' => $e->getMessage(),
                'reason' => $reason,
                'context' => $context,
            ]);
        }
    }
}