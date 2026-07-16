<?php

namespace App\Controller;

use App\Entity\Currency;
use App\Entity\ShopInfo;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\SearchRelevanceConfig;

class HomeController extends BaseController
{
    private const CSRF_LOGO_UPLOAD = 'save_logo';
    private const CSRF_CAROUSEL_UPLOAD = 'save_eshop_image';
    private const CSRF_DELETE_CAROUSEL = 'delete_cimage';
    private const CSRF_SAVE_ESHOP = 'save_eshop';
    private const SEARCH_METHODS = [
        'lexical' => 'Lexical Search',
        'semantic_vector' => 'Semantic Vector Search',
        'elasticsearch_bm25' => 'Elasticsearch BM25 Search',
    ];

    private const SEARCH_CONFIG_NAMES = [
        'lexical' => 'Lexical configuration',
        'semantic_vector' => 'Semantic vector configuration',
        'elasticsearch_bm25' => 'Elasticsearch BM25 configuration',
    ];

    #[Route('/', name: 'app_root', methods: ['GET'])]
    public function root(): RedirectResponse
    {
        return $this->redirectToRoute('home');
    }

    /**
     * Renders the dashboard homepage for authenticated users.
     */
    #[Route('/home', name: 'home', methods: ['GET'])]
    public function home(
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $token = $tokenStorage->getToken();
        $user = $token?->getUser();

        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
        $roles = \is_object($user) && method_exists($user, 'getRoles') ? $user->getRoles() : [];
        $currencies = $entityManager->getRepository(Currency::class)->findAll();

        $searchConfigRepository = $entityManager
            ->getRepository(SearchRelevanceConfig::class);

        $searchConfigs = [];

        foreach (self::SEARCH_METHODS as $method => $label) {
            $configRow = $searchConfigRepository->findOneBy([
                'searchMethod' => $method,
            ]);

            if ($configRow === null) {
                $configRow = new SearchRelevanceConfig();
                $configRow->setName(self::SEARCH_CONFIG_NAMES[$method]);
                $configRow->setSearchMethod($method);
                $configRow->setActive(false);
                $configRow->setAlgorithmSettings(
                    $this->getDefaultAlgorithmSettings($method)
                );
                $configRow->touch();

                $entityManager->persist($configRow);
            }

            $searchConfigs[$method] = $configRow;
        }

        $searchConfig = null;

        foreach ($searchConfigs as $configRow) {
            if ($configRow->isActive()) {
                $searchConfig = $configRow;
                break;
            }
        }

        if ($searchConfig === null) {
            $searchConfig = $searchConfigs['lexical'];
            $searchConfig->setActive(true);
        }

        $entityManager->flush();

        $searchConfigData = [];

        foreach ($searchConfigs as $method => $configRow) {
            $searchConfigData[$method] =
                $this->serializeSearchConfig($configRow);
        }

        $orderRows = $entityManager->createQueryBuilder()
            ->select('o.orderCreatedAt AS createdAt')
            ->addSelect('o.totalPrice AS totalPrice')
            ->from(\App\Entity\Order::class, 'o')
            ->where('o.orderCreatedAt >= :fromDate')
            ->setParameter('fromDate', new \DateTimeImmutable('-30 days'))
            ->orderBy('o.orderCreatedAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $salesMap = [];

        foreach ($orderRows as $row) {
            $createdAt = $row['createdAt'];

            if ($createdAt instanceof \DateTimeInterface) {
                $date = $createdAt->format('Y-m-d');
            } else {
                $date = (new \DateTimeImmutable((string) $createdAt))->format('Y-m-d');
            }

            if (!isset($salesMap[$date])) {
                $salesMap[$date] = 0.0;
            }

            $salesMap[$date] += (float) $row['totalPrice'];
        }

        $sales = [];

        foreach ($salesMap as $date => $total) {
            $sales[] = [
                'date' => $date,
                'total' => $total,
            ];
        }

        $topProducts = $entityManager->createQueryBuilder()
            ->select('oi.sku AS product_name')
            ->addSelect('SUM(oi.quantity) AS total_quantity')
            ->from(\App\Entity\OrderItem::class, 'oi')
            ->where('oi.sku IS NOT NULL')
            ->andWhere('oi.sku <> :emptySku')
            ->setParameter('emptySku', '')
            ->groupBy('oi.sku')
            ->orderBy('total_quantity', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();

        $topCustomers = $entityManager->createQueryBuilder()
            ->select('c.email AS email')
            ->addSelect('SUM(o.totalPrice) AS total_spent')
            ->from(\App\Entity\Order::class, 'o')
            ->join('o.customer', 'c')
            ->groupBy('c.email')
            ->orderBy('total_spent', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();


        return $this->renderLocalized('bms_home/home.html.twig', [
            'shopInfo' => $shopInfo,
            'roles' => $roles,
            'currencies' => $currencies,
            'searchConfig' => $searchConfig,
            'searchConfigs' => $searchConfigs,
            'searchConfigData' => $searchConfigData,
            'activeSearchMethod' => $searchConfig->getSearchMethod(),
            'searchMethods' => self::SEARCH_METHODS,
            'sales' => $sales,
            'topProducts' => $topProducts,
            'topCustomers' => $topCustomers,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'translations' => $this->getTranslations($request),

            'csrf_logo_upload' => $this->generateCsrfToken(self::CSRF_LOGO_UPLOAD),
            'csrf_carousel_upload' => $this->generateCsrfToken(self::CSRF_CAROUSEL_UPLOAD),
            'csrf_delete_carousel' => $this->generateCsrfToken(self::CSRF_DELETE_CAROUSEL),
            'csrf_save_eshop' => $this->generateCsrfToken(self::CSRF_SAVE_ESHOP),
        ], $request);
    }

    /**
     * Displays the "not logged in" page for unauthorized users.
     */
    #[Route('/not-logged', name: 'not_logged', methods: ['GET'])]
    public function notLogged(Request $request): Response
    {
        return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
    }

    /**
     * Uploads and saves the shop logo (multipart/form-data) with CSRF protection and basic validation.
     */
    #[Route('/logo_save', name: 'save_logo', methods: ['POST'])]
    public function saveLogo(
        Request $request,
        ParameterBagInterface $params,
        AuthorizationCheckerInterface $authorizationChecker,
        LoggerInterface $logger
    ): JsonResponse {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_LOGO_UPLOAD, $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
        }

        $file = $request->files->get('logo');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file received'], Response::HTTP_BAD_REQUEST);
        }

        if (!$file->isValid()) {
            return new JsonResponse(['error' => 'Upload failed'], Response::HTTP_BAD_REQUEST);
        }

        $allowedMime = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
        $mime = (string) $file->getMimeType();

        if (!\in_array($mime, $allowedMime, true)) {
            return new JsonResponse(['error' => 'Invalid file type'], Response::HTTP_BAD_REQUEST);
        }

        $ext = strtolower((string) $file->guessExtension());
        if ($ext === '') {
            $ext = match ($mime) {
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                default => 'bin',
            };
        }

        $imagesDir = (string) $params->get('images_directory');
        $newFilename = 'logo.' . $ext;

        try {
            $file->move($imagesDir, $newFilename);
            return new JsonResponse(['filePath' => $newFilename]);
        } catch (FileException $e) {
            $logger->error('[HomeController] saveLogo upload failed: ' . $e->getMessage());
            return new JsonResponse(['error' => 'File upload failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Saves general e-shop settings (JSON body) with CSRF protection and input validation.
     */
    #[Route('/eshop_save', name: 'save_eshop', methods: ['POST'])]
    public function saveEshop(
        Request $request,
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $authorizationChecker,
        LoggerInterface $logger
    ): Response {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $csrfId = self::CSRF_SAVE_ESHOP;

        try {
            $searchConfigResult = null;
            $raw = (string) $request->getContent();
            $input = \json_decode($raw, true);

            if (!\is_array($input)) {
                return new JsonResponse(['status' => 'Error', 'message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
            }

            $tokenFromHeader = (string) $request->headers->get('X-CSRF-TOKEN', '');
            $tokenFromJson   = (string) ($input['_csrf_token'] ?? '');
            $tokenFromForm   = (string) $request->request->get('_token', '');

            $token = $tokenFromHeader !== ''
                ? $tokenFromHeader
                : ($tokenFromJson !== '' ? $tokenFromJson : $tokenFromForm);

            if (!$this->isCsrfTokenValid($csrfId, $token)) {
                return new JsonResponse(['status' => 'Error', 'message' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
            }

            $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
            if ($shopInfo === null) {
                return new JsonResponse(['status' => 'Error', 'message' => 'ShopInfo not found'], Response::HTTP_NOT_FOUND);
            }

            if (\array_key_exists('hidePrices', $input)) {
                $shopInfo->setHidePrices((bool) $input['hidePrices']);
            }

            $shopInfo->setEshopName(isset($input['eshopName']) ? (string) $input['eshopName'] : null);
            $shopInfo->setCompanyName(isset($input['companyName']) ? (string) $input['companyName'] : null);
            $shopInfo->setCin(isset($input['cin']) ? (string) $input['cin'] : null);
            $shopInfo->setAddress(isset($input['address']) ? (string) $input['address'] : null);
            $shopInfo->setTelephone(isset($input['tel']) ? (string) $input['tel'] : null);
            $shopInfo->setEmail(isset($input['email']) ? (string) $input['email'] : null);
            $shopInfo->setAboutUs(isset($input['about']) ? (string) $input['about'] : null);
            $shopInfo->setHowToOrder(isset($input['howToOrder']) ? (string) $input['howToOrder'] : null);
            $shopInfo->setBusinessConditions(isset($input['conditions']) ? (string) $input['conditions'] : null);
            $shopInfo->setPrivacyPolicy(isset($input['privacy']) ? (string) $input['privacy'] : null);
            $shopInfo->setShippingInfo(isset($input['shipping']) ? (string) $input['shipping'] : null);
            $shopInfo->setPayment(isset($input['payment']) ? (string) $input['payment'] : null);
            $shopInfo->setRefund(isset($input['refund']) ? (string) $input['refund'] : null);

            if (
                isset($input['searchConfig'])
                && is_array($input['searchConfig'])
            ) {
                $searchConfigResult = $this->saveSearchConfigFromArray(
                    $input['searchConfig'],
                    $entityManager,
                    $logger
                );
            }

            if (isset($input['currencies']) && \is_array($input['currencies'])) {
                $currencyRepo = $entityManager->getRepository(Currency::class);
                $existingCurrencies = $currencyRepo->findAll();

                $existingMap = [];
                foreach ($existingCurrencies as $currency) {
                    $existingMap[$currency->getName()] = $currency;
                }

                $defaultCount = 0;

                foreach ($input['currencies'] as $data) {
                    if (!\is_array($data)) {
                        continue;
                    }

                    $name = isset($data['name']) ? trim((string) $data['name']) : '';
                    $value = $data['value'] ?? null;
                    $isDefault = (bool) ($data['isDefault'] ?? false);

                    if ($name === '' || !\is_numeric($value)) {
                        continue;
                    }

                    if ($isDefault) {
                        $defaultCount++;
                    }

                    if (isset($existingMap[$name])) {
                        $currency = $existingMap[$name];
                        $currency->setValue((float) $value);
                        $currency->setIsDefault($isDefault);
                    } else {
                        $currency = new Currency();
                        $currency->setName($name);
                        $currency->setValue((float) $value);
                        $currency->setIsDefault($isDefault);
                        $entityManager->persist($currency);
                    }
                }

                if ($defaultCount > 1) {
                    return new JsonResponse(['status' => 'Error', 'message' => 'Only one default currency is allowed'], Response::HTTP_BAD_REQUEST);
                }
            }

            if (!empty($input['logo_url'])) {
                $logoName = $this->sanitizeBasename((string) $input['logo_url']);
                if ($logoName !== '') {
                    $ext = strtolower(pathinfo($logoName, PATHINFO_EXTENSION));
                    if (\in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
                        $normalizedExt = $ext === 'jpeg'
                            ? 'jpg'
                            : $ext;

                        $shopInfo->setLogo(
                            'logo.' . $normalizedExt
                        );
                    }
                }
            }

            $entityManager->persist($shopInfo);
            $entityManager->flush();

            if ($searchConfigResult !== null) {
                $this->notifySearchServiceAfterSave(
                    $searchConfigResult,
                    $logger
                );
            }

            return new JsonResponse(['status' => 'Success']);
        } catch (\Throwable $e) {
            $logger->error('[HomeController] saveEshop error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return new JsonResponse(['status' => 'Error', 'message' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Uploads and appends carousel images (multipart/form-data) with CSRF protection and validation.
     */
    #[Route('/image_save', name: 'save_eshop_image', methods: ['POST'])]
    public function saveImage(
        Request $request,
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $authorizationChecker,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ): Response {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_CAROUSEL_UPLOAD, $token)) {
            return new JsonResponse(['status' => 'Error', 'message' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
        }

        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
        if ($shopInfo === null) {
            return new JsonResponse(['status' => 'Error', 'message' => 'ShopInfo not found'], Response::HTTP_NOT_FOUND);
        }

        $files = $request->files->all('images');
        if (!\is_array($files) || $files === []) {
            return new JsonResponse(['status' => 'No files received'], Response::HTTP_BAD_REQUEST);
        }

        $allowedMime = ['image/png', 'image/jpeg', 'image/webp'];
        $imagesDir = (string) $params->get('images_directory');

        $existingImageUrls = $shopInfo->getCarouselPictures() ?? [];
        $nextIndex = \count($existingImageUrls) + 1;
        $newImageUrls = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $mime = (string) $file->getMimeType();
            if (!\in_array($mime, $allowedMime, true)) {
                continue;
            }

            $ext = strtolower((string) $file->guessExtension());
            if ($ext === '') {
                $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
            }

            $newFilename = 'carousel' . $nextIndex . '.' . $ext;

            try {
                $file->move($imagesDir, $newFilename);
                $newImageUrls[] = $newFilename;
                $nextIndex++;
            } catch (FileException $e) {
                $logger->error('[HomeController] saveImage upload failed: ' . $e->getMessage());
            }
        }

        if ($newImageUrls === []) {
            return new JsonResponse(['status' => 'Error', 'message' => 'No valid images uploaded'], Response::HTTP_BAD_REQUEST);
        }

        $shopInfo->setCarouselPictures(array_values(array_merge($existingImageUrls, $newImageUrls)));
        $entityManager->persist($shopInfo);
        $entityManager->flush();

        return new JsonResponse(['filePaths' => $newImageUrls]);
    }

    /**
     * Deletes a carousel image from disk and removes it from ShopInfo (CSRF protected).
     */
    #[Route('/delete_cimage/{imageName}', name: 'delete_cimage', methods: ['POST'], requirements: ['imageName' => '.+'])]
    public function deleteCImage(
        string $imageName,
        Request $request,
        ParameterBagInterface $params,
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $authorizationChecker,
        LoggerInterface $logger
    ): JsonResponse {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return new JsonResponse(['status' => 'Error', 'message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_DELETE_CAROUSEL, $token)) {
            return new JsonResponse(['status' => 'Error', 'message' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
        }

        $imageName = $this->sanitizeBasename($imageName);
        if ($imageName === '') {
            return new JsonResponse(['status' => 'Error', 'message' => 'Invalid image name'], Response::HTTP_BAD_REQUEST);
        }

        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
        if ($shopInfo === null) {
            $logger->error('[HomeController] deleteCImage: ShopInfo not found');
            return new JsonResponse(['status' => 'Error', 'message' => 'ShopInfo not found'], Response::HTTP_NOT_FOUND);
        }

        $existing = $shopInfo->getCarouselPictures() ?? [];
        if (!\in_array($imageName, $existing, true)) {
            return new JsonResponse(['status' => 'Error', 'message' => 'File not in database record'], Response::HTTP_BAD_REQUEST);
        }

        $imagesDir = (string) $params->get('images_directory');
        $fullPath = Path::join($imagesDir, $imageName);

        if (\is_file($fullPath)) {
            @unlink($fullPath);
        }

        $updated = array_values(array_filter($existing, fn(string $v) => $v !== $imageName));
        $shopInfo->setCarouselPictures($updated);

        $entityManager->persist($shopInfo);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Success', 'message' => 'File deleted successfully']);
    }

    #[Route('/api/search-config/update', name: 'api_search_config_update', methods: ['POST'])]
    public function updateSearchConfigApi(
        Request $request,
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $authorizationChecker,
        LoggerInterface $logger
    ): JsonResponse {
        $isLoggedIn = $authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY');

        $apiKey = (string) $this->getParameter('search_api_key');
        $requestApiKey = (string) $request->headers->get('X-API-KEY', '');

        $isValidApiKey = $apiKey !== '' && hash_equals($apiKey, $requestApiKey);

        if (!$isLoggedIn && !$isValidApiKey) {
            return new JsonResponse([
                'status' => 'Error',
                'message' => 'Forbidden',
            ], Response::HTTP_FORBIDDEN);
        }

        $input = json_decode((string) $request->getContent(), true);

        if (!is_array($input)) {
            return new JsonResponse(['status' => 'Error', 'message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->saveSearchConfigFromArray(
                $input,
                $entityManager,
                $logger
            );

            $entityManager->flush();

            $this->notifySearchServiceAfterSave(
                $result,
                $logger
            );

            return new JsonResponse([
                'status' => 'Success',
                'searchMethod' => $result['searchMethod'],
                'activeMethodChanged' => $result['activeMethodChanged'],
                'fieldWeightsChanged' => $result['fieldWeightsChanged'],
                'algorithmSettingsChanged' => $result['algorithmSettingsChanged'],
                'runtimeConfigChanged' => $result['runtimeConfigChanged'],
                'requiresFullReindex' => $result['requiresFullReindex'],
            ]);
        } catch (\Throwable $e) {
            $logger->error('[HomeController] updateSearchConfigApi error: ' . $e->getMessage());

            return new JsonResponse([
                'status' => 'Error',
                'message' => 'Internal Server Error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    
    /**
     * Returns a safe basename for filenames coming from user input.
     */
    private function sanitizeBasename(string $value): string
    {
        $value = trim(str_replace('\\', '/', $value));
        $value = basename($value);

        if ($value === '.' || $value === '..') {
            return '';
        }

        if (preg_match('/^[a-zA-Z0-9._-]+$/', $value) !== 1) {
            return '';
        }

        return $value;
    }

    /**
     * Generates a CSRF token string for the given token id.
     */
    private function generateCsrfToken(string $id): string
    {
        return $this->container->get('security.csrf.token_manager')->getToken($id)->getValue();
    }

    private function notifySearchConfigReload(LoggerInterface $logger): void
    {
        $this->notifySearchService(
            $logger,
            '/config/reload',
            [
                'reason' => 'search runtime config changed',
                'context' => [
                    'source' => 'bms_settings',
                ],
            ]
        );
    }

    private function notifySearchFullReindex(LoggerInterface $logger): void
    {
        $this->notifySearchService(
            $logger,
            '/reindex',
            [
                'mode' => 'full',
                'reason' => 'search configuration requiring reindex changed',
                'context' => [
                    'source' => 'bms_settings',
                ],
            ]
        );
    }

    private function saveSearchConfigFromArray(
        array $config,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ): array {
        $searchMethod = trim(
            (string) ($config['searchMethod'] ?? 'lexical')
        );

        if (!array_key_exists($searchMethod, self::SEARCH_METHODS)) {
            throw new \InvalidArgumentException(
                'Unsupported search method: ' . $searchMethod
            );
        }

        $repository = $entityManager
            ->getRepository(SearchRelevanceConfig::class);

        $searchConfig = $repository->findOneBy([
            'searchMethod' => $searchMethod,
        ]);

        if ($searchConfig === null) {
            $searchConfig = new SearchRelevanceConfig();
            $searchConfig->setSearchMethod($searchMethod);
            $searchConfig->setName(
                self::SEARCH_CONFIG_NAMES[$searchMethod]
            );
            $searchConfig->setActive(false);
            $searchConfig->setAlgorithmSettings(
                $this->getDefaultAlgorithmSettings($searchMethod)
            );

            $entityManager->persist($searchConfig);
        }

        $oldAlgorithmSettings =
            $searchConfig->getAlgorithmSettings() ?? [];

        $submittedAlgorithmSettings =
            $config['algorithmSettings'] ?? [];

        if (is_string($submittedAlgorithmSettings)) {
            $decoded = json_decode(
                $submittedAlgorithmSettings,
                true
            );

            $submittedAlgorithmSettings =
                is_array($decoded) ? $decoded : [];
        }

        if (!is_array($submittedAlgorithmSettings)) {
            $submittedAlgorithmSettings = [];
        }

        $newAlgorithmSettings = $this->mergeConfigArrays(
            $oldAlgorithmSettings,
            $submittedAlgorithmSettings
        );

        $newNameWeight = (int) (
            $config['nameWeight']
            ?? $searchConfig->getNameWeight()
        );

        $newDescriptionWeight = (int) (
            $config['descriptionWeight']
            ?? $searchConfig->getDescriptionWeight()
        );

        $newCategoryWeight = (int) (
            $config['categoryWeight']
            ?? $searchConfig->getCategoryWeight()
        );

        $newMaterialWeight = (int) (
            $config['materialWeight']
            ?? $searchConfig->getMaterialWeight()
        );

        $newColorWeight = (int) (
            $config['colorWeight']
            ?? $searchConfig->getColorWeight()
        );

        $newSizeWeight = (int) (
            $config['sizeWeight']
            ?? $searchConfig->getSizeWeight()
        );

        $newAttributesWeight = (int) (
            $config['attributesWeight']
            ?? $searchConfig->getAttributesWeight()
        );

        $fieldWeightsChanged =
            $newNameWeight !== $searchConfig->getNameWeight()
            || $newDescriptionWeight !== $searchConfig->getDescriptionWeight()
            || $newCategoryWeight !== $searchConfig->getCategoryWeight()
            || $newMaterialWeight !== $searchConfig->getMaterialWeight()
            || $newColorWeight !== $searchConfig->getColorWeight()
            || $newSizeWeight !== $searchConfig->getSizeWeight()
            || $newAttributesWeight !== $searchConfig->getAttributesWeight();

        $algorithmSettingsChanged =
            $newAlgorithmSettings !== $oldAlgorithmSettings;

        $activeMethodChanged = !$searchConfig->isActive();

        $newSameCategoryBonus = (float) (
            $config['sameCategoryBonus']
            ?? $searchConfig->getSameCategoryBonus()
        );

        $newSameMaterialBonus = (float) (
            $config['sameMaterialBonus']
            ?? $searchConfig->getSameMaterialBonus()
        );

        $newSameColorBonus = (float) (
            $config['sameColorBonus']
            ?? $searchConfig->getSameColorBonus()
        );

        $newSameSizeBonus = (float) (
            $config['sameSizeBonus']
            ?? $searchConfig->getSameSizeBonus()
        );

        $newSameCategoryRecommendationWeight = (float) (
            $config['sameCategoryRecommendationWeight']
            ?? $searchConfig->getSameCategoryRecommendationWeight()
        );

        $newSameColorRecommendationWeight = (float) (
            $config['sameColorRecommendationWeight']
            ?? $searchConfig->getSameColorRecommendationWeight()
        );

        $newSameSizeRecommendationWeight = (float) (
            $config['sameSizeRecommendationWeight']
            ?? $searchConfig->getSameSizeRecommendationWeight()
        );

        $newWishlistRecommendationWeight = (float) (
            $config['wishlistRecommendationWeight']
            ?? $searchConfig->getWishlistRecommendationWeight()
        );

        $newOrderHistoryRecommendationWeight = (float) (
            $config['orderHistoryRecommendationWeight']
            ?? $searchConfig->getOrderHistoryRecommendationWeight()
        );

        $newSearchHistoryRecommendationWeight = (float) (
            $config['searchHistoryRecommendationWeight']
            ?? $searchConfig->getSearchHistoryRecommendationWeight()
        );

        $newViewHistoryRecommendationWeight = (float) (
            $config['viewHistoryRecommendationWeight']
            ?? $searchConfig->getViewHistoryRecommendationWeight()
        );

        $newMaxRecommendationPerCategory = (int) (
            $config['maxRecommendationPerCategory']
            ?? $searchConfig->getMaxRecommendationPerCategory()
        );

        $newRecommendationDiversityPenalty = (float) (
            $config['recommendationDiversityPenalty']
            ?? $searchConfig->getRecommendationDiversityPenalty()
        );

        $newRecommendationEnabled = $this->readBoolean(
            $config,
            'recommendationEnabled',
            $searchConfig->isRecommendationEnabled()
        );

        $newRecommendationLoggingEnabled = $this->readBoolean(
            $config,
            'recommendationLoggingEnabled',
            $searchConfig->isRecommendationLoggingEnabled()
        );

        $runtimeConfigChanged =
            $activeMethodChanged
            || $algorithmSettingsChanged
            || $newSameCategoryBonus
                !== $searchConfig->getSameCategoryBonus()
            || $newSameMaterialBonus
                !== $searchConfig->getSameMaterialBonus()
            || $newSameColorBonus
                !== $searchConfig->getSameColorBonus()
            || $newSameSizeBonus
                !== $searchConfig->getSameSizeBonus()
            || $newSameCategoryRecommendationWeight
                !== $searchConfig->getSameCategoryRecommendationWeight()
            || $newSameColorRecommendationWeight
                !== $searchConfig->getSameColorRecommendationWeight()
            || $newSameSizeRecommendationWeight
                !== $searchConfig->getSameSizeRecommendationWeight()
            || $newWishlistRecommendationWeight
                !== $searchConfig->getWishlistRecommendationWeight()
            || $newOrderHistoryRecommendationWeight
                !== $searchConfig->getOrderHistoryRecommendationWeight()
            || $newSearchHistoryRecommendationWeight
                !== $searchConfig->getSearchHistoryRecommendationWeight()
            || $newViewHistoryRecommendationWeight
                !== $searchConfig->getViewHistoryRecommendationWeight()
            || $newMaxRecommendationPerCategory
                !== $searchConfig->getMaxRecommendationPerCategory()
            || $newRecommendationDiversityPenalty
                !== $searchConfig->getRecommendationDiversityPenalty()
            || $newRecommendationEnabled
                !== $searchConfig->isRecommendationEnabled()
            || $newRecommendationLoggingEnabled
                !== $searchConfig->isRecommendationLoggingEnabled();

        $requiresFullReindex =
            $this->requiresFullReindex(
                $searchMethod,
                $fieldWeightsChanged,
                $oldAlgorithmSettings,
                $newAlgorithmSettings
            );

        /*
        * The partial unique index only permits one active row.
        * First deactivate the old active row and flush, then activate
        * the selected row.
        */
        if ($activeMethodChanged) {
            $activeConfigs = $repository->findBy([
                'active' => true,
            ]);

            foreach ($activeConfigs as $activeConfig) {
                $activeConfig->setActive(false);
            }

            $entityManager->flush();

            $searchConfig->setActive(true);
        }

        $searchConfig->setName(
            trim((string) (
                $config['name']
                ?? $searchConfig->getName()
                ?? self::SEARCH_CONFIG_NAMES[$searchMethod]
            ))
        );

        $searchConfig->setSearchMethod($searchMethod);

        $searchConfig->setNameWeight($newNameWeight);
        $searchConfig->setDescriptionWeight($newDescriptionWeight);
        $searchConfig->setCategoryWeight($newCategoryWeight);
        $searchConfig->setMaterialWeight($newMaterialWeight);
        $searchConfig->setColorWeight($newColorWeight);
        $searchConfig->setSizeWeight($newSizeWeight);
        $searchConfig->setAttributesWeight($newAttributesWeight);

        $searchConfig->setSameCategoryBonus(
            $newSameCategoryBonus
        );
        $searchConfig->setSameMaterialBonus(
            $newSameMaterialBonus
        );
        $searchConfig->setSameColorBonus(
            $newSameColorBonus
        );
        $searchConfig->setSameSizeBonus(
            $newSameSizeBonus
        );

        $searchConfig->setSameCategoryRecommendationWeight(
            $newSameCategoryRecommendationWeight
        );
        $searchConfig->setSameColorRecommendationWeight(
            $newSameColorRecommendationWeight
        );
        $searchConfig->setSameSizeRecommendationWeight(
            $newSameSizeRecommendationWeight
        );
        $searchConfig->setWishlistRecommendationWeight(
            $newWishlistRecommendationWeight
        );
        $searchConfig->setOrderHistoryRecommendationWeight(
            $newOrderHistoryRecommendationWeight
        );
        $searchConfig->setSearchHistoryRecommendationWeight(
            $newSearchHistoryRecommendationWeight
        );
        $searchConfig->setViewHistoryRecommendationWeight(
            $newViewHistoryRecommendationWeight
        );
        $searchConfig->setMaxRecommendationPerCategory(
            $newMaxRecommendationPerCategory
        );
        $searchConfig->setRecommendationDiversityPenalty(
            $newRecommendationDiversityPenalty
        );

        $searchConfig->setRecommendationEnabled(
            $newRecommendationEnabled
        );

        $searchConfig->setRecommendationLoggingEnabled(
            $newRecommendationLoggingEnabled
        );

        $searchConfig->setAlgorithmSettings(
            $newAlgorithmSettings
        );

        $searchConfig->touch();

        $entityManager->persist($searchConfig);

        $logger->info('[SearchConfig] change detection', [
            'searchMethod' => $searchMethod,
            'activeMethodChanged' => $activeMethodChanged,
            'fieldWeightsChanged' => $fieldWeightsChanged,
            'algorithmSettingsChanged' => $algorithmSettingsChanged,
            'runtimeConfigChanged' => $runtimeConfigChanged,
            'requiresFullReindex' => $requiresFullReindex,
        ]);

        return [
            'searchMethod' => $searchMethod,
            'activeMethodChanged' => $activeMethodChanged,
            'fieldWeightsChanged' => $fieldWeightsChanged,
            'algorithmSettingsChanged' => $algorithmSettingsChanged,
            'runtimeConfigChanged' => $runtimeConfigChanged,
            'requiresFullReindex' => $requiresFullReindex,
        ];
    }

    private function notifySearchServiceAfterSave(
        array $result,
        LoggerInterface $logger
    ): void {
        if ($result['requiresFullReindex'] ?? false) {
            $this->notifySearchFullReindex($logger);
            return;
        }

        if (
            ($result['runtimeConfigChanged'] ?? false)
            || ($result['fieldWeightsChanged'] ?? false)
        ) {
            $this->notifySearchConfigReload($logger);
        }
    }

    private function readBoolean(
        array $input,
        string $key,
        bool $default
    ): bool {
        if (!array_key_exists($key, $input)) {
            return $default;
        }

        $value = filter_var(
            $input[$key],
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        return $value ?? $default;
    }

    private function mergeConfigArrays(
        array $base,
        array $updates
    ): array {
        foreach ($updates as $key => $value) {
            if (
                is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && !array_is_list($value)
                && !array_is_list($base[$key])
            ) {
                $base[$key] = $this->mergeConfigArrays(
                    $base[$key],
                    $value
                );

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    private function serializeSearchConfig(
        SearchRelevanceConfig $config
    ): array {
        return [
            'id' => $config->getId(),
            'name' => $config->getName(),
            'searchMethod' => $config->getSearchMethod(),
            'active' => $config->isActive(),

            'nameWeight' => $config->getNameWeight(),
            'descriptionWeight' => $config->getDescriptionWeight(),
            'categoryWeight' => $config->getCategoryWeight(),
            'materialWeight' => $config->getMaterialWeight(),
            'colorWeight' => $config->getColorWeight(),
            'sizeWeight' => $config->getSizeWeight(),
            'attributesWeight' => $config->getAttributesWeight(),

            'sameCategoryBonus' =>
                $config->getSameCategoryBonus(),
            'sameMaterialBonus' =>
                $config->getSameMaterialBonus(),
            'sameColorBonus' =>
                $config->getSameColorBonus(),
            'sameSizeBonus' =>
                $config->getSameSizeBonus(),

            'sameCategoryRecommendationWeight' =>
                $config->getSameCategoryRecommendationWeight(),
            'sameColorRecommendationWeight' =>
                $config->getSameColorRecommendationWeight(),
            'sameSizeRecommendationWeight' =>
                $config->getSameSizeRecommendationWeight(),

            'wishlistRecommendationWeight' =>
                $config->getWishlistRecommendationWeight(),
            'orderHistoryRecommendationWeight' =>
                $config->getOrderHistoryRecommendationWeight(),
            'searchHistoryRecommendationWeight' =>
                $config->getSearchHistoryRecommendationWeight(),
            'viewHistoryRecommendationWeight' =>
                $config->getViewHistoryRecommendationWeight(),

            'maxRecommendationPerCategory' =>
                $config->getMaxRecommendationPerCategory(),
            'recommendationDiversityPenalty' =>
                $config->getRecommendationDiversityPenalty(),

            'recommendationEnabled' =>
                $config->isRecommendationEnabled(),
            'recommendationLoggingEnabled' =>
                $config->isRecommendationLoggingEnabled(),

            'algorithmSettings' =>
                $config->getAlgorithmSettings() ?? [],
        ];
    }

    private function getDefaultAlgorithmSettings(
        string $searchMethod
    ): array {
        if ($searchMethod === 'semantic_vector') {
            return [
                'document_fields' => [
                    'name' => true,
                    'category' => true,
                    'description' => true,
                    'material' => true,
                    'color' => true,
                    'size' => true,
                    'attributes' => false,
                ],
                'embedding' => [
                    'batch_size' => 32,
                    'normalize_embeddings' => true,
                ],
                'reranking' => [
                    'semantic_similarity_weight' => 0.75,
                    'lexical_overlap_weight' => 0.25,
                    'minimum_token_length' => 2,
                ],
                'candidate_pool' => [
                    'multiplier' => 5,
                    'minimum_candidates' => 50,
                ],
                'vector_search' => [
                    'ivfflat_probes' => 10,
                ],
                'session_recommendation' => [
                    'current_product_weight' => 1.0,
                    'viewed_product_weight' => 0.70,
                    'cart_product_weight' => 0.90,
                    'max_viewed_seeds' => 5,
                    'max_cart_seeds' => 5,
                    'max_total_seeds' => 8,
                    'candidate_multiplier' => 2,
                    'minimum_candidates' => 10,
                ],
            ];
        }

        if ($searchMethod === 'elasticsearch_bm25') {
            return [
                'search_query' => [
                    'type' => 'best_fields',
                    'operator' => 'or',
                    'field_weights' => [
                        'name' => 5,
                        'category' => 3,
                        'description' => 2,
                        'material' => 1,
                        'color' => 1,
                        'size' => 1,
                        'sku' => 2,
                    ],
                ],
                'recommendation_query' => [
                    'type' => 'best_fields',
                    'operator' => 'or',
                    'field_weights' => [
                        'name' => 5,
                        'category' => 4,
                        'description' => 2,
                        'material' => 2,
                        'color' => 1,
                        'size' => 1,
                        'sku' => 2,
                    ],
                    'candidate_multiplier' => 3,
                    'minimum_candidates' => 20,
                    'exclude_source_sku' => true,
                ],
                'session_recommendation' => [
                    'current_product_weight' => 1.0,
                    'viewed_product_weight' => 0.70,
                    'cart_product_weight' => 0.90,
                    'max_viewed_seeds' => 5,
                    'max_cart_seeds' => 5,
                    'max_total_seeds' => 8,
                    'candidate_multiplier' => 2,
                    'minimum_candidates' => 10,
                ],
            ];
        }

        return [
            'vectorizer' => [
                'lowercase' => true,
                'ngram_range' => [1, 2],
                'n_features' => 262144,
                'alternate_sign' => false,
                'normalization' => 'l2',
                'token_pattern' => '\\b\\w+\\b',
            ],
            'candidate_filter' => [
                'minimum_query_token_matches' => 1,
                'fallback_to_all_documents' => true,
            ],
            'partial_match' => [
                'require_all_query_tokens' => true,
                'minimum_query_token_matches' => 1,
                'base_score' => 1.0,
                'merge_bonus_weight' => 0.20,
            ],
            'session_recommendation' => [
                'current_product_weight' => 1.0,
                'viewed_product_weight' => 0.70,
                'cart_product_weight' => 0.90,
                'max_viewed_seeds' => 5,
                'max_cart_seeds' => 5,
                'max_total_seeds' => 8,
                'candidate_multiplier' => 3,
                'minimum_candidates' => 10,
            ],
        ];
    }

    private function requiresFullReindex(
        string $searchMethod,
        bool $fieldWeightsChanged,
        array $oldSettings,
        array $newSettings
    ): bool {
        if ($searchMethod === 'lexical') {
            return $fieldWeightsChanged
                || ($oldSettings['vectorizer'] ?? [])
                    !== ($newSettings['vectorizer'] ?? []);
        }

        if ($searchMethod === 'semantic_vector') {
            return ($oldSettings['document_fields'] ?? [])
                    !== ($newSettings['document_fields'] ?? [])
                || ($oldSettings['embedding'] ?? [])
                    !== ($newSettings['embedding'] ?? []);
        }

        /*
        * BM25 field weights and query options are applied at request
        * time, so changing them only requires config reload.
        */
        return false;
    }

    private function notifySearchService(
        LoggerInterface $logger,
        string $path,
        array $json
    ): void {
        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');
            $apiKey = (string) $this->getParameter('search_api_key');

            $logger->info('[SearchService] Preparing request', [
                'baseUrl' => $baseUrl,
                'path' => $path,
                'hasApiKey' => $apiKey !== '',
            ]);

            if ($baseUrl === '' || $apiKey === '') {
                $logger->warning('[SearchService] Missing base URL or API key');
                return;
            }

            $client = \Symfony\Component\HttpClient\HttpClient::create();

            $options = [
                'headers' => [
                    'X-API-KEY' => $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
            ];

            if ($json !== []) {
                $options['json'] = $json;
            }

            $response = $client->request('POST', $baseUrl . $path, $options);

            $logger->info('[SearchService] Response', [
                'path' => $path,
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(false),
            ]);
        } catch (\Throwable $e) {
            $logger->warning('[SearchService] Request failed: ' . $e->getMessage());
        }
    }
}