<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Currency;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HomeController extends BaseController
{
    #[Route('/home', name: 'home')]
    /**
     * Renders the dashboard homepage for authenticated users.
     * Displays shop info, user roles, currencies, recent sales (last 30 days),
     * top 5 best-selling products, and top 5 spending customers.
     *
     * @param TokenStorageInterface $tokenStorage
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function home(TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $authorizationChecker, EntityManagerInterface $entityManager, Request $request): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
        $user = $tokenStorage->getToken()->getUser();
        $roles = $user->getRoles();
        $currencies = $entityManager->getRepository(Currency::class)->findAll();

        $sales = $entityManager->getConnection()->executeQuery("
            SELECT DATE(order_created_at) AS date, SUM(total_price) AS total
            FROM orders
            WHERE order_created_at >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(order_created_at)
            ORDER BY date ASC
        ")->fetchAllAssociative();

        $topProducts = $entityManager->getConnection()->executeQuery("
            SELECT product_name, SUM(quantity) AS total_quantity
            FROM order_items
            GROUP BY product_name
            ORDER BY total_quantity DESC
            LIMIT 5
        ")->fetchAllAssociative();

        $topCustomers = $entityManager->getConnection()->executeQuery("
            SELECT c.email, SUM(o.total_price) AS total_spent
            FROM orders o
            JOIN customer c ON c.id = o.customer_id
            GROUP BY c.email
            ORDER BY total_spent DESC
            LIMIT 5
        ")->fetchAllAssociative();

        return $this->renderLocalized('bms_home/home.html.twig', [
            'shopInfo' => $shopInfo,
            'roles' => $roles,
            'currencies' => $currencies,
            'sales' => $sales,
            'topProducts' => $topProducts,
            'topCustomers' => $topCustomers,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'translations' => $this->getTranslations($request),
        ], $request);
    }

    #[Route('/not-logged', name: 'not_logged')]
    /**
     * Displays the "not logged in" page for unauthorized users.
     *
     * @param Request $request
     * @return Response
     */
    public function notLogged(Request $request): Response
    {
        return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
    }

    #[Route('/logo_save', name: 'save_logo', methods: ['POST'])]
    /**
     * Handles the upload and saving of the shop logo.
     *
     * @param Request $request
     * @param ParameterBagInterface $params
     * @param LoggerInterface $logger
     * @return JsonResponse
     */
    public function saveLogo(Request $request, ParameterBagInterface $params, LoggerInterface $logger): JsonResponse
    {
        $file = $request->files->get('logo');
        if (!$file) {
            return new JsonResponse(['error' => 'No file received'], 400);
        }

        $newFilename = 'logo_' . '.' . $file->guessExtension();
        try {
            $file->move($params->get('images_directory'), $newFilename);
            return new JsonResponse(['filePath' => $newFilename]);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'File upload failed'], 500);
        }
    }

    #[Route('/eshop_save', name: 'save_eshop', methods: ['POST'])]
    /**
     * Saves general e-shop settings (name, contact info, color, logo, etc.).
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param LoggerInterface $logger
     * @return Response
     */
    public function saveEshop(Request $request, EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker, LoggerInterface $logger): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        try {
            $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, true);

            if (isset($input["hidePrices"])) {
                $shopInfo->setHidePrices((bool) $input["hidePrices"]);
            }

            $shopInfo->setEshopName($input["eshopName"] ?? null);
            $shopInfo->setCompanyName($input["companyName"] ?? null);
            $shopInfo->setCin($input["cin"] ?? null);
            $shopInfo->setAddress($input["address"] ?? null);
            $shopInfo->setTelephone($input["tel"] ?? null);
            $shopInfo->setEmail($input["email"] ?? null);
            $shopInfo->setAboutUs($input["about"] ?? null);
            $shopInfo->setHowToOrder($input["howToOrder"] ?? null);
            $shopInfo->setBusinessConditions($input["conditions"] ?? null);
            $shopInfo->setPrivacyPolicy($input["privacy"] ?? null);
            $shopInfo->setShippingInfo($input["shipping"] ?? null);
            $shopInfo->setPayment($input["payment"] ?? null);
            $shopInfo->setRefund($input["refund"] ?? null);

            if (isset($input["currencies"])) {
                $currencyRepo = $entityManager->getRepository(Currency::class);
                $existingCurrencies = $currencyRepo->findAll();
                $existingMap = [];
                foreach ($existingCurrencies as $currency) {
                    $existingMap[$currency->getName()] = $currency;
                }
            
                foreach ($input["currencies"] as $data) {
                    $name = $data["name"] ?? null;
                    $value = $data["value"] ?? null;
                    $isDefault = $data["isDefault"] ?? false;
            
                    if (!$name || $value === null) continue;
            
                    if (isset($existingMap[$name])) {
                        $currency = $existingMap[$name];
                        $currency->setValue((float) $value);
                        $currency->setIsDefault((bool) $isDefault);
                    } else {
                        $currency = new Currency();
                        $currency->setName($name);
                        $currency->setValue((float) $value);
                        $currency->setIsDefault((bool) $isDefault);
                        $entityManager->persist($currency);
                    }
                }
            }

            if (!empty($input["logo_url"])) {
                $originalFilename = preg_replace('/^C:\\fakepath\\/', '', $input["logo_url"]);
                $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
                $logo_url = "images/logo_." . $extension;
                $shopInfo->setLogo($logo_url);
            }

            $entityManager->persist($shopInfo);
            $entityManager->flush();

            return new JsonResponse(["status" => "Success"]);
        } catch (\Exception $e) {
            $logger->error('An error occurred: ' . $e->getMessage());
            $logger->error('Stack trace: ' . $e->getTraceAsString());
            return new JsonResponse(["status" => "Error"], 500);
        }
    }

    #[Route('/image_save', name: 'save_eshop_image')]
    /**
     * Handles upload of new carousel images for the homepage.
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param LoggerInterface $logger
     * @return Response
     */
    public function saveImage(Request $request, EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker, LoggerInterface $logger): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);

        $files = $request->files->get('images');
        $existingImageUrls = $shopInfo->getCarouselPictures() ?? [];

        $this->image_count = count($existingImageUrls) + 1;
        $newImageUrls = [];

        if (!$files) {
            return new JsonResponse(['status' => 'No files received'], 400);
        }

        foreach ($files as $file) {
            $newFilename = "carousel" . $this->image_count . '.' . $file->guessExtension();
            $file->move($this->getParameter('images_directory'), $newFilename);

            $newImageUrls[] = $newFilename;
            $this->image_count++;
        }

        $shopInfo->setCarouselPictures(array_merge($existingImageUrls, $newImageUrls));
        $entityManager->persist($shopInfo);
        $entityManager->flush();

        return new JsonResponse(['filePaths' => $newImageUrls]);
    }

    #[Route('/delete_cimage/{imageName}', name: 'delete_cimage', methods: ['POST'], requirements: ['imageName' => '.+'])]
    /**
     * Deletes a carousel image from disk and removes its reference in the database.
     *
     * @param string $imageName Name of the image file to delete
     * @param ParameterBagInterface $params
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     * @return JsonResponse
     */
    public function deleteCImage($imageName, ParameterBagInterface $params, EntityManagerInterface $entityManager, LoggerInterface $logger): JsonResponse
    {
        if (!$imageName) {
            return new JsonResponse(['status' => 'Error', 'message' => 'No file name provided'], 400);
        }

        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
        if (!$shopInfo) {
            $logger->error("ShopInfo not found");
            return new JsonResponse(['status' => 'Error', 'message' => 'ShopInfo not found']);
        }

        $existingImageUrls = $shopInfo->getCarouselPictures() ?? [];

        if (in_array($imageName, $existingImageUrls)) {
            $fullFilePath = $params->get('images_directory') . '/' . $imageName;
            if (file_exists($fullFilePath)) {
                unlink($fullFilePath);
            } else {
                $logger->error("File not found on disk");
                return new JsonResponse(['status' => 'Error', 'message' => 'File not found on disk']);
            }

            $updatedImageUrls = array_filter($existingImageUrls, fn($url) => $url !== $imageName);
            $shopInfo->setCarouselPictures(array_values($updatedImageUrls));
            $entityManager->persist($shopInfo);
            $entityManager->flush();

            return new JsonResponse(['status' => 'Success', 'message' => 'File deleted successfully']);
        } else {
            $logger->error("File not in database record");
            return new JsonResponse(['status' => 'Error', 'message' => 'File not in database record']);
        }
    }
}