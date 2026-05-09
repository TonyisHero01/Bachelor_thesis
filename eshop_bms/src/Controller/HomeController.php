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

        $searchConfig = $entityManager
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(['active' => true], ['id' => 'DESC']);

        if ($searchConfig === null) {
            $searchConfig = new SearchRelevanceConfig();
            $searchConfig->setActive(true);
            $entityManager->persist($searchConfig);
            $entityManager->flush();
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
            ->select('oi.productName AS product_name')
            ->addSelect('SUM(oi.quantity) AS total_quantity')
            ->from(\App\Entity\OrderItem::class, 'oi')
            ->groupBy('oi.productName')
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

            $searchConfig = $entityManager
                ->getRepository(SearchRelevanceConfig::class)
                ->findOneBy(['active' => true], ['id' => 'DESC']);

            if ($searchConfig === null) {
                $searchConfig = new SearchRelevanceConfig();
                $searchConfig->setActive(true);
                $entityManager->persist($searchConfig);
            }

            $fieldWeightsChanged = false;
            $runtimeConfigChanged = false;
            if (isset($input['searchConfig']) && is_array($input['searchConfig'])) {
                $config = $input['searchConfig'];

                $fieldWeightsChanged =
                    (int) ($config['nameWeight'] ?? 20) !== $searchConfig->getNameWeight()
                    || (int) ($config['descriptionWeight'] ?? 5) !== $searchConfig->getDescriptionWeight()
                    || (int) ($config['categoryWeight'] ?? 4) !== $searchConfig->getCategoryWeight()
                    || (int) ($config['materialWeight'] ?? 2) !== $searchConfig->getMaterialWeight()
                    || (int) ($config['colorWeight'] ?? 2) !== $searchConfig->getColorWeight()
                    || (int) ($config['sizeWeight'] ?? 2) !== $searchConfig->getSizeWeight()
                    || (int) ($config['attributesWeight'] ?? 2) !== $searchConfig->getAttributesWeight();

                $runtimeConfigChanged =
                    (float) ($config['sameCategoryBonus'] ?? 0.35) !== $searchConfig->getSameCategoryBonus()
                    || (float) ($config['sameMaterialBonus'] ?? 0.15) !== $searchConfig->getSameMaterialBonus()
                    || (float) ($config['sameColorBonus'] ?? 0.10) !== $searchConfig->getSameColorBonus()
                    || (float) ($config['sameSizeBonus'] ?? 0.10) !== $searchConfig->getSameSizeBonus()

                    || (float) ($config['tfidfRecommendationWeight'] ?? 1.0) !== $searchConfig->getTfidfRecommendationWeight()
                    || (float) ($config['sameCategoryRecommendationWeight'] ?? 0.35) !== $searchConfig->getSameCategoryRecommendationWeight()
                    || (float) ($config['sameColorRecommendationWeight'] ?? 0.10) !== $searchConfig->getSameColorRecommendationWeight()
                    || (float) ($config['sameSizeRecommendationWeight'] ?? 0.10) !== $searchConfig->getSameSizeRecommendationWeight()

                    || (float) ($config['wishlistRecommendationWeight'] ?? 0.30) !== $searchConfig->getWishlistRecommendationWeight()
                    || (float) ($config['orderHistoryRecommendationWeight'] ?? 0.25) !== $searchConfig->getOrderHistoryRecommendationWeight()
                    || (float) ($config['searchHistoryRecommendationWeight'] ?? 0.20) !== $searchConfig->getSearchHistoryRecommendationWeight()
                    || (float) ($config['viewHistoryRecommendationWeight'] ?? 0.35) !== $searchConfig->getViewHistoryRecommendationWeight()

                    || (int) ($config['maxRecommendationPerCategory'] ?? 4) !== $searchConfig->getMaxRecommendationPerCategory()
                    || (float) ($config['recommendationDiversityPenalty'] ?? 0.10) !== $searchConfig->getRecommendationDiversityPenalty();

                $searchConfig->setName((string) ($config['name'] ?? 'Default relevance configuration'));

                $searchConfig->setNameWeight((int) ($config['nameWeight'] ?? 20));
                $searchConfig->setDescriptionWeight((int) ($config['descriptionWeight'] ?? 5));
                $searchConfig->setCategoryWeight((int) ($config['categoryWeight'] ?? 4));
                $searchConfig->setMaterialWeight((int) ($config['materialWeight'] ?? 2));
                $searchConfig->setColorWeight((int) ($config['colorWeight'] ?? 2));
                $searchConfig->setSizeWeight((int) ($config['sizeWeight'] ?? 2));
                $searchConfig->setAttributesWeight((int) ($config['attributesWeight'] ?? 2));

                $searchConfig->setSameCategoryBonus((float) ($config['sameCategoryBonus'] ?? 0.35));
                $searchConfig->setSameMaterialBonus((float) ($config['sameMaterialBonus'] ?? 0.15));
                $searchConfig->setSameColorBonus((float) ($config['sameColorBonus'] ?? 0.10));
                $searchConfig->setSameSizeBonus((float) ($config['sameSizeBonus'] ?? 0.10));

                $searchConfig->setTfidfRecommendationWeight((float) ($config['tfidfRecommendationWeight'] ?? 1.0));
                $searchConfig->setSameCategoryRecommendationWeight((float) ($config['sameCategoryRecommendationWeight'] ?? 0.35));
                $searchConfig->setSameColorRecommendationWeight((float) ($config['sameColorRecommendationWeight'] ?? 0.10));
                $searchConfig->setSameSizeRecommendationWeight((float) ($config['sameSizeRecommendationWeight'] ?? 0.10));
                $searchConfig->setWishlistRecommendationWeight((float) ($config['wishlistRecommendationWeight'] ?? 0.30));
                $searchConfig->setOrderHistoryRecommendationWeight((float) ($config['orderHistoryRecommendationWeight'] ?? 0.25));
                $searchConfig->setSearchHistoryRecommendationWeight((float) ($config['searchHistoryRecommendationWeight'] ?? 0.20));
                $searchConfig->setViewHistoryRecommendationWeight((float) ($config['viewHistoryRecommendationWeight'] ?? 0.35));
                $searchConfig->setMaxRecommendationPerCategory((int) ($config['maxRecommendationPerCategory'] ?? 4));
                $searchConfig->setRecommendationDiversityPenalty((float) ($config['recommendationDiversityPenalty'] ?? 0.10));
                $searchConfig->touch();
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
                        $shopInfo->setLogo('images/logo.' . ($ext === 'jpeg' ? 'jpg' : $ext));
                    }
                }
            }

            $entityManager->persist($shopInfo);
            $entityManager->flush();
            
            if ($fieldWeightsChanged) {
                $this->notifySearchFullReindex($logger);
            }

            if (!$fieldWeightsChanged && $runtimeConfigChanged) {
                $this->notifySearchConfigReload($logger);
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
                'reason' => 'search relevance field weights changed',
                'context' => [
                    'source' => 'bms_settings',
                ],
            ]
        );
    }

    private function notifySearchService(
        LoggerInterface $logger,
        string $path,
        array $json
    ): void {
        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');
            $apiKey = (string) $this->getParameter('search_api_key');

            if ($baseUrl === '' || $apiKey === '') {
                return;
            }

            $client = \Symfony\Component\HttpClient\HttpClient::create();

            $options = [
                'headers' => [
                    'X-API-KEY' => $apiKey,
                ],
                'timeout' => 10,
            ];

            if ($json !== []) {
                $options['json'] = $json;
            }

            $response = $client->request('POST', $baseUrl . $path, $options);

            if ($response->getStatusCode() >= 400) {
                $logger->warning('[SearchService] Request failed', [
                    'path' => $path,
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(false),
                ]);
            }
        } catch (\Throwable $e) {
            $logger->warning('[SearchService] Request failed: ' . $e->getMessage());
        }
    }
}