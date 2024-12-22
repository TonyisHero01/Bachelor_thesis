<?php

namespace App\Controller;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use App\Entity\Product;
use App\Entity\Category;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Filesystem\Filesystem;
function compareProductIds($a, $b) {
    return $a['similarity'] <=> $b['similarity'];
}
class ProductController extends AbstractController
{
    private $params;
    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }
    private $image_count = 1;
    #[Route('/bms/product_create', name: 'create_product')]
    public function createProduct(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);
        $name = $input["name"];
        $number_in_stock = $input["number_in_stock"];
        $add_time = $input["add_time"];
        $price = $input["price"];
        $sku = $input["sku"];

        $product = new Product();
        $product->setName($name);
        $product->setNumberInStock($number_in_stock);
        $product->setAddTime($add_time);
        $product->setPrice($price);
        $product->setSku($sku);

        $entityManager->persist($product);

        $entityManager->flush();

        $productRepository = $entityManager->getRepository(Product::class);
        $new_product = $productRepository->findOneByMaxId();
        $new_product_info = [
            'id' => $new_product->getId(),
            'name' => $new_product->getName(),
            'number_in_stock' => $new_product->getNumberInStock(),
            'price' => $new_product->getPrice(),
            'sku' => $new_product->getSku(),
        ];
        return new JsonResponse(['id' => $new_product_info['id']]);
    }

    #[Route('/bms/product/{id}', name: 'show_product')]
    public function show(EntityManagerInterface $entityManager, int $id, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }
        $product = $entityManager->getRepository(Product::class)->findProductById($id);
        if(!$product)
        {
            throw $this->createNotFoundException(
                'No product found for id '.$id
            );
        }

        return $this->render('product/product_show.html.twig', [
            $product
        ]);
    }

    #[Route('/bms/product_list', name: 'show_All_products')]
    public function showAllProducts(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker, LoggerInterface $logger): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }
        $user = $this->getUser();
        if ($authorizationChecker->isGranted('ROLE_CUSTOMER', $user)) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }
        if ($user) {
            // 获取用户的角色，返回一个数组
            $roles = $user->getRoles();
            
            // 打印用户角色以进行调试
            $logger->info(json_encode($roles));  // 或使用日志记录
        } else {
            // 用户未登录
            dump('用户未登录');
        }
        $products = $entityManager->getRepository(Product::class)->findLatestVersionProducts();
        $form = $this->createForm(ProductType::class, new Product());

        $product_list = '';

        foreach ($products as $product) 
        {
            $product_list .= '<div>' . $product->getName() . ' ' . $product->getNumberInStock() . ' ' . $product->getAddTime() . ' ' . $product->getPrice() . '</div>' . '<br>';
        }
        $logger->info(json_encode($product_list));
        return $this->render('product/product_list.html.twig', [
            'products' => $products,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'form' => $form
        ]);
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
            return $this->render('employee/employee_not_logged.html.twig', []);
        }

        $productRepository = $entityManager->getRepository(Product::class);
        $product = $productRepository->find($id);

        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        // 获取同一 SKU 的所有版本的产品
        $productsWithSameSku = $productRepository->findBy(['sku' => $product->getSku()], ['version' => 'DESC']);

        $categories = $entityManager->getRepository(Category::class)->findAllCategories();

        return $this->render('product/product_edit.html.twig', [
            'product' => $product,
            'productsWithSameSku' => $productsWithSameSku, // 将所有版本传递给模板
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->params->get('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->params->get('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->params->get('CONTENT_MAX_LENGTH'),
            'categories' => $categories,
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
            return $this->render('employee/employee_not_logged.html.twig', []);
        }

        try {
            $productRepository = $entityManager->getRepository(Product::class);
            $currentProduct = $productRepository->find($id);

            if (!$currentProduct) {
                return new JsonResponse(["status" => "Error", "message" => "Product not found"], 404);
            }

            // 从请求内容中解码 JSON 数据
            $data = json_decode($request->getContent(), true);

            // 查询数据库，获取当前 SKU 下的最大版本号
            $maxVersion = $entityManager->createQueryBuilder()
                ->select('MAX(p.version)')
                ->from(Product::class, 'p')
                ->where('p.sku = :sku')
                ->setParameter('sku', $currentProduct->getSku())
                ->getQuery()
                ->getSingleScalarResult();

            // 新版本号 = 当前最大版本号 + 1
            $newVersion = $maxVersion + 1;

            // 创建新产品实例，基于当前产品（无论版本如何）
            $newProduct = new Product();
            $newProduct->setName($data['name'] ?? $currentProduct->getName());
            $newProduct->setCategory($data['category'] ?? $currentProduct->getCategory());
            $newProduct->setDescription($data['description'] ?? $currentProduct->getDescription());
            $newProduct->setNumberInStock(isset($data['number_in_stock']) ? (int)$data['number_in_stock'] : $currentProduct->getNumberInStock());
            $newProduct->setWidth(isset($data['width']) ? (float)$data['width'] : $currentProduct->getWidth());
            $newProduct->setHeight(isset($data['height']) ? (float)$data['height'] : $currentProduct->getHeight());
            $newProduct->setLength(isset($data['length']) ? (float)$data['length'] : $currentProduct->getLength());
            $newProduct->setWeight(isset($data['weight']) ? (float)$data['weight'] : $currentProduct->getWeight());
            $newProduct->setMaterial($data['material'] ?? $currentProduct->getMaterial());
            $newProduct->setColor($data['color'] ?? $currentProduct->getColor());
            $newProduct->setPrice(isset($data['price']) ? (float)$data['price'] : $currentProduct->getPrice());
            $newProduct->setHidden(isset($data['hidden']) ? (bool)$data['hidden'] : $currentProduct->getHidden());
            $newProduct->setDiscount(isset($data['discount']) ? (float)$data['discount'] : $currentProduct->getDiscount());
            $newProduct->setSku($currentProduct->getSku()); // 保留相同的 SKU
            // 设置 attributes 数据
            if (isset($data['attributes']) && is_array($data['attributes'])) {
                $newProduct->setAttributes($data['attributes']);
            } else {
                $newProduct->setAttributes([]);
            }
            // 设置图片：合并现有图片和新图片
            $imageUrls = array_filter($data['image_urls'] ?? [], fn($url) => !empty($url));
            $existingImageUrls = $currentProduct->getImageUrls() ?? [];
            $newProduct->setImageUrls(array_merge($existingImageUrls, $imageUrls));

            // 设置版本号为新版本号
            $newProduct->setVersion($newVersion);

            // 设置添加时间为前端传递的 edit_time 或当前时间
            if (isset($data['edit_time']) && !empty($data['edit_time'])) {
                $newProduct->setAddTime($data['edit_time']);
            } else {
                $newProduct->setAddTime((new \DateTime())->format('Y-m-d H:i:s'));
            }

            // 保存新产品
            $entityManager->persist($newProduct);
            $entityManager->flush();

            return new JsonResponse(["status" => "Success", "new_product_id" => $newProduct->getId()]);
        } catch (\Exception $e) {
            $logger->error('An error occurred: ' . $e->getMessage());
            $logger->error('Stack trace: ' . $e->getTraceAsString());
            return new JsonResponse(["status" => "Error"], 500);
        }
    }
    #[Route('/bms/product_delete/{id}', name: 'delete_product', methods: ['DELETE'])]
    public function deleteProduct($id,  EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }
        $productRepository = $entityManager->getRepository(Product::class);
        $product = $productRepository->find($id);

        if ($product) {
            $productRepository->deleteProduct($product);
            return new JsonResponse();
        } else {
            return new JsonResponse();
        }
    }
    #[Route('/image_save/{id}', name: 'save_image')]
    public function saveImage($id, Request $request, EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker, LoggerInterface $logger): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }

        $product = $entityManager->getRepository(Product::class)->find($id);
        if (!$product) {
            $logger->error("Product not found. ID: ".$id);
            return new JsonResponse(['status' => 'Product not found'], 404);
        }

        $name = $product->getName();
        $files = $request->files->get('images'); // 获取上传的图片
        $existingImageUrls = $product->getImageUrls() ?? []; // 获取现有的图片路径数组

        // 设置 image_count 从现有图片数量加 1 开始
        $this->image_count = count($existingImageUrls) + 1;
        $newImageUrls = []; // 存储新上传的图片路径

        if (!$files) {
            $logger->info('No files received');
            return new JsonResponse(['status' => 'No files received'], 400);
        }

        $logger->info('Files received:', ['files' => $files]);
        foreach ($files as $file) {
            $newFilename = $name . $this->image_count . '.' . $file->guessExtension();
            $file->move($this->getParameter('images_directory'), $newFilename);
            
            // 将新文件名添加到数组中
            $newImageUrls[] = $newFilename;
            $this->image_count++;
        }

        // 合并新旧图片路径并保存到数据库
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
            // 从产品的 imageUrls 字段中删除该图片路径
            $product = $entityManager->getRepository(Product::class)->find($id);
            if ($product) {
                $imageUrls = $product->getImageUrls();
                $updatedUrls = array_filter($imageUrls, fn($url) => $url !== $imageUrl);
                $product->setImageUrls($updatedUrls);

                $entityManager->persist($product);
                $entityManager->flush();

                // 删除图片文件
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
    #[Route('/bms/search', name: 'search', methods: ['POST'])]
    public function search(EntityManagerInterface $entityManager, SessionInterface $session, AuthorizationCheckerInterface $authorizationChecker, LoggerInterface $logger): Response
    {
        $logger->info('当前用户身份验证状态: ' . ($this->isGranted('IS_AUTHENTICATED_FULLY') ? '已认证' : '未认证'));
        $logger->info('当前用户角色: ' . json_encode($this->getUser()->getRoles()));
        
        if (!$this->isGranted('ROLE_WAREHOUSE_MANAGER')) {
            throw new AccessDeniedException('您没有权限执行此操作。');
        }
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            $logger->info('未登录: ');
            return $this->render('employee/employee_not_logged.html.twig', []);
        }
        $user = $this->getUser();
        if ($user) {
            $logger->info('用户身份认证: ' . json_encode([
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]));
        } else {
            $logger->info('用户未找到。');
        }
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);
        $query = $input["query"];
        $command = 'python3 ../python_scripts/tf-idf.py "' . $query . '"';
        $output = shell_exec($command);
        #$command = escapeshellcmd('python3 ../python_scripts/tf-idf.py "' . $query . '"');
        #$output = shell_exec($command);

        if ($output === null) {
            $logger->error('命令执行失败: ' . $command);
            return new JsonResponse(['error' => '命令执行失败'], 500);
        }

        preg_match_all('/"product_id":\s*(\d+)/', $output, $matches);

        // 获取所有提取到的 product_id
        $results = $matches[1];
        $session->set('search_results', $results);
        $logger->info('Query：'.$query);
        $logger->info('Python搜索结果：'.json_encode($results));
        return new JsonResponse(["results" => $results]);
    }
    #[Route('/bms/results', name: 'results')]
    public function results(SessionInterface $session, EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        // 检查用户是否登录
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }

        // 从会话中获取 product_id 列表
        $ids = $session->get('search_results', []);

        // 确保 ids 不为空
        if (empty($ids)) {
            return $this->render('product/no_results.html.twig', []);
        }

        // 使用 product_id 查询产品实体
        $productRepository = $entityManager->getRepository(Product::class);
        $products = $productRepository->findBy(['id' => $ids]);

        // 根据 ids 的顺序对 products 进行排序
        $productsById = [];
        foreach ($products as $product) {
            $productsById[$product->getId()] = $product;
        }

        // 按照 ids 的顺序重新排序
        $sortedProducts = [];
        foreach ($ids as $id) {
            if (isset($productsById[$id])) {
                $sortedProducts[] = $productsById[$id];
            }
        }

        $form = $this->createForm(ProductType::class, new Product());
        return $this->render('product/product_list.html.twig', [
            'products' => $sortedProducts,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'form' => $form
        ]);
    }
    #[Route('/bms/save_category', name: 'save_category')]
    public function createCategory(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
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
}
