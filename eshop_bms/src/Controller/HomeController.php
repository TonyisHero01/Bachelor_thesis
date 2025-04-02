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
    public function home(TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $authorizationChecker, EntityManagerInterface $entityManager, Request $request): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }
        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);

        $user = $tokenStorage->getToken()->getUser();
        $roles = $user->getRoles();

        $currencies = $entityManager->getRepository(Currency::class)->findAll();

        return $this->renderLocalized('bms_home/home.html.twig', [
            'shopInfo' => $shopInfo,
            'roles' => $roles,
            'currencies' => $currencies,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'translations' => $this->getTranslations($request),
        ], $request);
    }

    #[Route('/not-logged', name: 'not_logged')]
    public function notLogged(Request $request): Response
    {
        return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
    }

    #[Route('/logo_save', name: 'save_logo', methods: ['POST'])]
    public function saveLogo(Request $request, ParameterBagInterface $params, LoggerInterface $logger): JsonResponse
    {
        $file = $request->files->get('logo');
        if (!$file) {
            return new JsonResponse(['error' => 'No file received'], 400);
        }

        $newFilename = 'logo_' . '.' . $file->guessExtension();
        $logger->info($newFilename);
        try {
            $file->move($params->get('images_directory'), $newFilename);
            return new JsonResponse(['filePath' => $newFilename]);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'File upload failed'], 500);
        }
    }

    #[Route('/eshop_save', name: 'save_eshop', methods: ['POST'])]
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
            $shopInfo->setColorCode($input["color"] ?? null);

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
            $logger->info('No files received');
            return new JsonResponse(['status' => 'No files received'], 400);
        }

        $logger->info('Files received:', ['files' => $files]);
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
                $logger->info("Deleted file: " . $fullFilePath);
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