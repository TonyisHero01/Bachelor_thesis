<?php

namespace App\Controller;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use App\Entity\Product;
use App\Entity\Category;
use App\Entity\Color;
use App\Entity\Currency;
use App\Entity\Size;
use App\Form\ProductType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Controller\ProductRepository;
use Exception;
use Twig\Environment;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Filesystem\Filesystem;
function compareProductIds($a, $b) {
    return $a['similarity'] <=> $b['similarity'];
}
class ProductController extends BaseController
{
    private $params;
    public function __construct(
        ParameterBagInterface $params,
        EntityManagerInterface $em,
        AuthorizationCheckerInterface $auth,
        LoggerInterface $logger,
        Environment $twig
    ) {
        parent::__construct($twig, $logger);
        $this->params = $params;
        $this->em = $em;
        $this->auth = $auth;
    }
    private $image_count = 1;
    #[Route('/bms/product_create', name: 'create_product', methods: ['POST'])]
    public function createProduct(
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }

        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);

        $name = $input["name"] ?? "Unnamed Product";
        $number_in_stock = $input["number_in_stock"] ?? 0;
        $price = $input["price"] ?? 0.00;
        $sku = $input["sku"] ?? "UNKNOWN";
        $currencyId = $input["currency_id"] ?? null;

        $existing = $entityManager->getRepository(Product::class)->findOneBy(['sku' => $sku]);
        if ($existing) {
            return new JsonResponse([
                'success' => false,
                'message' => 'SKU already exists. Please use a unique SKU.'
            ], 400);
        }

        if ($currencyId === null) {
            $currency = $entityManager->getRepository(Currency::class)->findOneBy(['isDefault' => true]);
        } else {
            $currency = $entityManager->getRepository(Currency::class)->find($currencyId);
        }

        if (!$currency) {
            return new JsonResponse(['success' => false, 'message' => 'Currency not found!'], 400);
        }

        $product = new Product();
        $product->setName($name);
        $product->setNumberInStock($number_in_stock);
        $product->setPrice($price);
        $product->setSku($sku);
        $product->setCurrency($currency);
        $product->setCreatedAt(new \DateTimeImmutable());
        $product->setUpdatedAt(new \DateTimeImmutable());
        $product->setVersion(1);
        $product->setHidden(false);

        $entityManager->persist($product);
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'id' => $product->getId()]);
    }

    #[Route('/bms/product/{id}', name: 'show_product')]
    public function show(EntityManagerInterface $entityManager, int $id, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }
        $product = $entityManager->getRepository(Product::class)->findProductById($id);
        if(!$product)
        {
            throw $this->createNotFoundException(
                'No product found for id '.$id
            );
        }

        return $this->renderLocalized('product/product_show.html.twig', [
            'product' => $product
        ]);
    }

    #[Route('/bms/product_list', name: 'show_All_products')]
    public function showAllProducts(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker, LoggerInterface $logger): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }
        $user = $this->getUser();
        if ($authorizationChecker->isGranted('ROLE_CUSTOMER', $user)) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }
        if ($user) {
            $roles = $user->getRoles();
            
            $logger->info(json_encode($roles));
        } else {
            dump('User not logged in');
        }
        $products = $entityManager->getRepository(Product::class)->findLatestVersionProducts();
        $form = $this->createForm(ProductType::class, new Product());

        $product_list = '';

        foreach ($products as $product) 
        {
            $product_list .= '<div>' . $product->getName() . ' ' . $product->getNumberInStock() . ' ' . $product->getCreatedAt()->format('Y-m-d H:i:s') . ' ' . $product->getPrice() . '</div>' . '<br>';
        }
        $logger->info(json_encode($product_list));
        $colors = $entityManager->getRepository(Color::class)->findAll();
        $sizes = $entityManager->getRepository(Size::class)->findAll();
        $categories = $entityManager->getRepository(Category::class)->findAll();
        return $this->renderLocalized('product/product_list.html.twig', [
            'products' => $products,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'form' => $form,
            'colors' => $colors,
            'sizes' => $sizes,
            'categories' => $categories,
        ]);
    }

    #[Route('/bms/modify_category', name: 'modify_category', methods: ['POST'])]
    public function modifyCategory(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = $data['id'] ?? null;
        $newName = $data['new_name'] ?? null;

        if (!$id || !$newName) {
            return new JsonResponse(['success' => false, 'message' => 'Missing ID or new name'], 400);
        }

        $category = $em->getRepository(Category::class)->find($id);
        if (!$category) {
            return new JsonResponse(['success' => false, 'message' => 'Category not found'], 404);
        }

        $category->setName($newName);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/bms/product_edit/{id}', name: 'edit_product')]
    public function edit(
        EntityManagerInterface $entityManager,
        $id,
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker,
        LoggerInterface $logger
    ): Response {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }

        $productRepository = $entityManager->getRepository(Product::class);
        $product = $productRepository->find($id);

        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $productsWithSameSku = $productRepository->findBy(['sku' => $product->getSku()], ['version' => 'DESC']);

        $categories = $entityManager->getRepository(Category::class)->findAllCategories();
        $colors = $entityManager->getRepository(Color::class)->findAll();
        $sizes = $entityManager->getRepository(Size::class)->findAll();

        return $this->renderLocalized('product/product_edit.html.twig', [
            'product' => $product,
            'productsWithSameSku' => $productsWithSameSku,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->params->get('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->params->get('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->params->get('CONTENT_MAX_LENGTH'),
            'categories' => $categories,
            'colors' => $colors,
            'sizes' => $sizes,
        ]);
    }
    #[Route('/bms/product_save/{id}', name: 'save_product', methods: ['POST'])]
    public function saveProduct(
        Request $request,
        EntityManagerInterface $entityManager,
        $id,
        LoggerInterface $logger,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }

        try {
            $productRepository = $entityManager->getRepository(Product::class);
            $currentProduct = $productRepository->find($id);

            if (!$currentProduct) {
                return new JsonResponse(["status" => "Error", "message" => "Product not found"], 404);
            }

            $data = json_decode($request->getContent(), true);

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

                $currentProduct->setWidth(isset($data['width']) ? (float)$data['width'] : $currentProduct->getWidth());
                $currentProduct->setHeight(isset($data['height']) ? (float)$data['height'] : $currentProduct->getHeight());
                $currentProduct->setLength(isset($data['length']) ? (float)$data['length'] : $currentProduct->getLength());
                $currentProduct->setWeight(isset($data['weight']) ? (float)$data['weight'] : $currentProduct->getWeight());

                if (!empty($data['size'])) {
                    $size = $entityManager->getRepository(Size::class)->find($data['size']);
                    if ($size) {
                        $currentProduct->setSize($size);
                    }
                }

                if (!empty($data['color'])) {
                    $color = $entityManager->getRepository(Color::class)->find($data['color']);
                    if ($color) {
                        $currentProduct->setColor($color);
                    }
                }

                if (!empty($data['category'])) {
                    $category = $entityManager->getRepository(Category::class)->find($data['category']);
                    if ($category) {
                        $currentProduct->setCategory($category);
                    }
                }

                if (isset($data['image_urls'])) {
                    $validUrls = array_filter($data['image_urls'], fn($url) => !empty($url));
                    $currentProduct->setImageUrls($validUrls);
                }

                if (isset($data['hidden'])) {
                    $currentProduct->setHidden((bool)$data['hidden']);
                }

                $currentProduct->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                return new JsonResponse(["status" => "Success", "message" => "Updated current version"]);
            }

            $maxVersion = $entityManager->createQueryBuilder()
                ->select('MAX(p.version)')
                ->from(Product::class, 'p')
                ->where('p.sku = :sku')
                ->setParameter('sku', $currentProduct->getSku())
                ->getQuery()
                ->getSingleScalarResult();
            $newVersion = $maxVersion + 1;

            $newProduct = new Product();
            $newProduct->setSku($currentProduct->getSku());
            $newProduct->setVersion($newVersion);
            $newProduct->setCreatedAt($currentProduct->getCreatedAt());

            $newProduct->setName($data['name'] ?? $currentProduct->getName());
            $newProduct->setDescription($data['description'] ?? $currentProduct->getDescription());
            $newProduct->setNumberInStock($data['number_in_stock'] ?? $currentProduct->getNumberInStock());
            $newProduct->setPrice($data['price'] ?? $currentProduct->getPrice());
            $newProduct->setDiscount($data['discount'] ?? $currentProduct->getDiscount());
            $newProduct->setTaxRate($data['tax_rate'] ?? $currentProduct->getTaxRate());
            $newProduct->setMaterial($data['material'] ?? $currentProduct->getMaterial());
            $newProduct->setAttributes($data['attributes'] ?? []);

            $newProduct->setWidth(isset($data['width']) ? (float)$data['width'] : $currentProduct->getWidth());
            $newProduct->setHeight(isset($data['height']) ? (float)$data['height'] : $currentProduct->getHeight());
            $newProduct->setLength(isset($data['length']) ? (float)$data['length'] : $currentProduct->getLength());
            $newProduct->setWeight(isset($data['weight']) ? (float)$data['weight'] : $currentProduct->getWeight());

            if (!empty($data['category'])) {
                $category = $entityManager->getRepository(Category::class)->find($data['category']);
                $newProduct->setCategory($category);
            } else {
                $newProduct->setCategory($currentProduct->getCategory());
            }

            if (!empty($data['size'])) {
                $size = $entityManager->getRepository(Size::class)->find($data['size']);
                $newProduct->setSize($size);
            } else {
                $newProduct->setSize($currentProduct->getSize());
            }

            if (!empty($data['color'])) {
                $color = $entityManager->getRepository(Color::class)->find($data['color']);
                $newProduct->setColor($color);
            } else {
                $newProduct->setColor($currentProduct->getColor());
            }

            $currency = $entityManager->getRepository(Currency::class)->findOneBy(['isDefault' => true]);
            $newProduct->setCurrency($currency);

            $newProduct->setHidden($data['hidden'] ?? false);

            $imageUrls = array_filter($data['image_urls'] ?? [], fn($url) => !empty($url));
            $existingUrls = $currentProduct->getImageUrls() ?? [];
            $newProduct->setImageUrls(array_merge($existingUrls, $imageUrls));

            $newProduct->setUpdatedAt(
                isset($data['edit_time'])
                    ? new \DateTimeImmutable($data['edit_time'])
                    : new \DateTimeImmutable()
            );

            $entityManager->persist($newProduct);
            $entityManager->flush();

            return new JsonResponse(["status" => "Success", "new_product_id" => $newProduct->getId()]);
        } catch (\Exception $e) {
            $logger->error('❌ Error in saveProduct: ' . $e->getMessage());
            return new JsonResponse(["status" => "Error", "message" => $e->getMessage()], 500);
        }
    }
    #[Route('/bms/product_delete/{id}', name: 'delete_product', methods: ['DELETE'])]
    public function deleteProduct(
        $id,  
        EntityManagerInterface $entityManager, 
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }

        $productRepository = $entityManager->getRepository(Product::class);
        $product = $productRepository->find($id);

        if ($product) {
            $sku = $product->getSku();

            $productsWithSameSku = $productRepository->findBy(['sku' => $sku]);

            foreach ($productsWithSameSku as $productToDelete) {
                $entityManager->remove($productToDelete);
            }

            $entityManager->flush();

            return new JsonResponse(['success' => true]);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Product not found'], 404);
        }
    }
    #[Route('/image_save/{id}', name: 'save_image')]
    public function saveImage($id, Request $request, EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker, LoggerInterface $logger): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }

        $product = $entityManager->getRepository(Product::class)->find($id);
        if (!$product) {
            $logger->error("Product not found. ID: ".$id);
            return new JsonResponse(['status' => 'Product not found'], 404);
        }

        $name = $product->getName();
        $files = $request->files->get('images');
        $existingImageUrls = $product->getImageUrls() ?? [];

        $this->image_count = count($existingImageUrls) + 1;
        $newImageUrls = [];

        if (!$files) {
            $logger->info('No files received');
            return new JsonResponse(['status' => 'No files received'], 400);
        }

        $logger->info('Files received:', ['files' => $files]);
        foreach ($files as $file) {
            $newFilename = $name . $this->image_count . '.' . $file->guessExtension();
            $file->move($this->getParameter('images_directory'), $newFilename);
            
            $newImageUrls[] = $newFilename;
            $this->image_count++;
        }

        $product->setImageUrls(array_merge($existingImageUrls, $newImageUrls));
        $entityManager->persist($product);
        $entityManager->flush();

        return new JsonResponse(['filePaths' => $newImageUrls]);
    }
    #[Route('/delete_image/{id}', name: 'delete_image', methods: ['POST'])]
    public function deleteImage($id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $imageUrl = $data['imageUrl'] ?? null;

        if ($imageUrl) {
            $product = $entityManager->getRepository(Product::class)->find($id);
            if ($product) {
                $imageUrls = $product->getImageUrls();
                $updatedUrls = array_filter($imageUrls, fn($url) => $url !== $imageUrl);
                $product->setImageUrls($updatedUrls);

                $entityManager->persist($product);
                $entityManager->flush();

                $fileSystem = new Filesystem();
                $filePath = $this->getParameter('images_directory') . '/' . $imageUrl;
                if ($fileSystem->exists($filePath)) {
                    $fileSystem->remove($filePath);
                }

                return new JsonResponse(['status' => 'success']);
            }
        }

        return new JsonResponse(['status' => 'error', 'message' => 'Image not found'], 400);
    }
    /**
     * @Route("/search", name="search")
     * @IsGranted("ROLE_WAREHOUSE_MANAGER")
     */
    #[Route('/bms/search', name: 'bms_search', methods: ['POST'])]
    public function search(
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        AuthorizationCheckerInterface $authChecker,
        LoggerInterface $logger,
        Request $request
    ): Response {

        if (!$authChecker->isGranted('ROLE_WAREHOUSE_MANAGER')) {
            throw new AccessDeniedException('You do not have permission to perform this action.');
        }

        if (!$authChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }

        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        $query = $input['query'] ?? '';

        if (empty($query)) {
            return new JsonResponse(['error' => 'Empty search query'], 400);
        }

        $escapedQuery = escapeshellarg($query);
        $projectRoot = $this->getParameter('kernel.project_dir');
        $pythonPath = $projectRoot . '/python_scripts/venv/bin/python';
        $scriptPath = $projectRoot . '/python_scripts/tf-idf.py';
        $command = "$pythonPath $scriptPath $escapedQuery 2>&1";

        $logger->info("Executing search command: $command");
        $output = shell_exec($command);

        if ($output === null) {
            $logger->error("Python script execution failed with no output");
            return new JsonResponse(['error' => 'Search command failed'], 500);
        }

        $searchResults = json_decode($output, true);
        if (!is_array($searchResults)) {
            $logger->error("Python script output: $output");
            return new JsonResponse(['error' => 'Search system error'], 500);
        }

        $skuToSimilarity = [];
        foreach ($searchResults as $result) {
            $skuToSimilarity[$result['product_sku']] = $result['similarity'];
        }

        $productSkus = array_keys($skuToSimilarity);

        if (empty($productSkus)) {
            return new JsonResponse(['results' => []]);
        }

        $queryBuilder = $entityManager->createQueryBuilder();
        $queryBuilder
            ->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where($queryBuilder->expr()->in('p.sku', ':skus'))
            ->setParameter('skus', $productSkus)
            ->orderBy('p.id', 'DESC');

        $products = $queryBuilder->getQuery()->getResult();

        $skuToLatestId = [];
        foreach ($products as $product) {
            if (!isset($skuToLatestId[$product['sku']])) {
                $skuToLatestId[$product['sku']] = $product['id'];
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
    #[Route('/bms/results', name: 'results')]
    public function results(
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }

        $searchResults = $session->get('search_results', []);

        if (empty($searchResults)) {
            return $this->renderLocalized('product/no_results.html.twig', []);
        }

        $ids = array_column($searchResults, 'id');

        $productRepository = $entityManager->getRepository(Product::class);
        $products = $productRepository->findBy(['id' => $ids]);

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
        $colors = $entityManager->getRepository(Color::class)->findAll();
        $sizes = $entityManager->getRepository(Size::class)->findAll();

        return $this->renderLocalized('product/results.html.twig', [
            'products' => $sortedProducts,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'colors' => $colors,
            'sizes' => $sizes,
            'form' => $form
        ]);
    }
    #[Route('/bms/save_category', name: 'save_category')]
    public function createCategory(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);
        $name = $input["name"];

        $category = new Category();
        $category->setName($name);

        $entityManager->persist($category);

        $entityManager->flush();

        return new JsonResponse([]);
    }
    #[Route('/bms/save_color', name: 'save_color')]
    public function createColor(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);
        $name = $input["name"];
        $hex = $input["hex"];

        $color = new Color();
        $color->setName($name);
        $color->setHex($hex);

        $entityManager->persist($color);

        $entityManager->flush();

        return new JsonResponse([]);
    }

    #[Route('/bms/modify_color', name: 'modify_color', methods: ['POST'])]
    public function modifyColor(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $colorId = $data['id'] ?? null;
        $newName = $data['new_name'] ?? null;
        $newHex = $data['new_hex'] ?? null;

        if (!$colorId || !$newName || !$newHex) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid data'], 400);
        }

        $colorRepository = $entityManager->getRepository(Color::class);
        $color = $colorRepository->find($colorId);

        if (!$color) {
            return new JsonResponse(['status' => 'error', 'message' => 'Color not found'], 404);
        }

        $color->setName($newName);
        $color->setHex($newHex);

        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }
    #[Route('/bms/create_size', name: 'size_create', methods: ['POST'])]
    public function createSize(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sizeName = $data['name'] ?? '';

        if (empty($sizeName)) {
            return new JsonResponse(['success' => false, 'message' => 'Size name cannot be empty.'], 400);
        }

        $size = new Size();
        $size->setName($sizeName);
        $entityManager->persist($size);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/bms/modify_size/{id}', name: 'size_modify', methods: ['POST'])]
    public function modifySize(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newSizeName = $data['name'] ?? '';

        if (empty($newSizeName)) {
            return new JsonResponse(['success' => false, 'message' => 'New size name cannot be empty.'], 400);
        }

        $size = $entityManager->getRepository(Size::class)->find($id);
        if (!$size) {
            return new JsonResponse(['success' => false, 'message' => 'Size not found.'], 404);
        }

        $size->setName($newSizeName);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
