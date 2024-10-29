<?php

namespace App\Controller;
use App\Entity\ShopInfo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'home')]
    public function home(TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $authorizationChecker, EntityManagerInterface $entityManager): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee_not_logged.html.twig', []);
        }
        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);

        $user = $tokenStorage->getToken()->getUser();
        $roles = $user->getRoles();

        return $this->render('home.html.twig', [
            'shopInfo' => $shopInfo,
            'roles' => $roles,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
        ]);
    }
    #[Route('/logo_save', name: 'save_logo', methods: ['POST'])]
    public function saveLogo(Request $request, ParameterBagInterface $params, LoggerInterface $logger): JsonResponse
    {
        $file = $request->files->get('logo');
        if (!$file) {
            return new JsonResponse(['error' => '未收到文件'], 400);
        }

        $newFilename = 'logo_' . '.' . $file->guessExtension();
        $logger->info($newFilename);
        try {
            $file->move($params->get('images_directory'), $newFilename);
            return new JsonResponse(['filePath' => $newFilename]);
        } catch (FileException $e) {
            return new JsonResponse(['error' => '文件上传失败'], 500);
        }
    }
    #[Route('/eshop_save', name: 'save_eshop', methods: ['POST'])]
    public function saveEshop(Request $request, EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker, LoggerInterface $logger): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee_not_logged.html.twig', []);
        }
        try {
            $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, true);

            // 获取主要信息
            $shopInfo->setEshopName($input["eshopName"]);
            $shopInfo->setCompanyName($input["companyName"]);
            $shopInfo->setCin($input["cin"]);
            $shopInfo->setAddress($input["address"]);
            $shopInfo->setTelephone($input["tel"]);
            $shopInfo->setEmail($input["email"]);
            $shopInfo->setAboutUs($input["about"]);
            $shopInfo->setHowToOrder($input["howToOrder"]);
            $shopInfo->setBusinessConditions($input["conditions"]);
            $shopInfo->setPrivacyPolicy($input["privacy"]);
            $shopInfo->setShippingInfo($input["shipping"]);
            $shopInfo->setPayment($input["payment"]);
            $shopInfo->setRefund($input["refund"]);
            $shopInfo->setColorCode($input["color"]);
            // 获取 logo_url 并去除 C:\fakepath\
            $originalFilename = str_replace("C:\\fakepath\\", "", $input["logo_url"]);

            // 获取文件的扩展名
            $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);

            // 构造新的文件名并保存
            $logo_url = "logo_" . '.' . $fileExtension;
            $shopInfo->setLogo($logo_url);

            // 处理 carousel_pictures
            $imageUrls = $input["carousel_urls"] ?? [];
            $existingImageUrls = $shopInfo->getCarouselPictures() ?? [];
            $shopInfo->setCarouselPictures(array_merge($existingImageUrls, $imageUrls));

            // 保存更改
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
            return $this->render('employee_not_logged.html.twig', []);
        }

        $shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);

        $files = $request->files->get('images'); // 获取上传的图片
        $existingImageUrls = $shopInfo->getCarouselPictures() ?? []; // 获取现有的图片路径数组

        // 设置 image_count 从现有图片数量加 1 开始
        $this->image_count = count($existingImageUrls) + 1;
        $newImageUrls = []; // 存储新上传的图片路径

        if (!$files) {
            $logger->info('No files received');
            return new JsonResponse(['status' => 'No files received'], 400);
        }

        $logger->info('Files received:', ['files' => $files]);
        foreach ($files as $file) {
            $newFilename = "carousel" . $this->image_count . '.' . $file->guessExtension();
            $file->move($this->getParameter('images_directory'), $newFilename);
            
            // 将新文件名添加到数组中
            $newImageUrls[] = $newFilename;
            $this->image_count++;
        }

        // 合并新旧图片路径并保存到数据库
        $shopInfo->setCarouselPictures(array_merge($existingImageUrls, $newImageUrls));
        $entityManager->persist($shopInfo);
        $entityManager->flush();

        return new JsonResponse(['filePaths' => $newImageUrls]);
    }
}
