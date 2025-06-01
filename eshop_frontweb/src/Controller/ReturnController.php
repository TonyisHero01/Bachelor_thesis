<?php

namespace App\Controller;

use App\Entity\ReturnRequest;
use App\Entity\Order;
use App\Entity\ShopInfo;
use App\Entity\Category;
use App\Repository\OrderRepository;
use App\Repository\OrderItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Controller\BaseController;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class ReturnController extends BaseController
{
    public function __construct(EntityManagerInterface $entityManager, Environment $twig, LoggerInterface $logger)
    {
        parent::__construct($twig, $logger);
        $this->entityManager = $entityManager;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }

    #[Route('/order/{id}/return-form', name: 'return_form')]
    public function returnForm(int $id, OrderRepository $orderRepository, OrderItemRepository $orderItemRepository, Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $order = $orderRepository->find($id);
        if (!$order || !$order->getIsCompleted()) {
            return $this->redirectToRoute('customer_orders');
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->renderLocalized('eshop_order/return_form.html.twig', [
            'show_sidebar' => false,
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'order' => $order,
            'orderItems' => $orderItemRepository->findBy(['order' => $order]),
            'user' => $this->getUser(),
            'categories' => $categories
        ], $request);
    }

    #[Route('/order/{id}/submit-return', name: 'submit_return', methods: ['POST'])]
    public function submitReturn(
        int $id, 
        Request $request, 
        EntityManagerInterface $entityManager, 
        OrderRepository $orderRepository
    ): JsonResponse {
        $order = $orderRepository->find($id);
        if (!$order || !$order->getIsCompleted()) {
            return new JsonResponse(["success" => false, "message" => "Invalid order"], 400);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
            return new JsonResponse(["success" => false, "message" => "No items selected for return"], 400);
        }

        try {
            $formattedSkus = implode(', ', $data['items']);

            $returnRequest = new ReturnRequest();
            $returnRequest->setUserEmail($data['email'] ?? '');
            $returnRequest->setUserPhone($data['phone'] ?? '');
            $returnRequest->setUserName($data['name'] ?? '');
            $returnRequest->setOrder($order);
            $returnRequest->setProductSkus($formattedSkus);
            $returnRequest->setReturnReason($data['reason'] ?? null);
            $returnRequest->setUserMessage($data['message'] ?? null);

            $entityManager->persist($returnRequest);
            $entityManager->flush();

            return new JsonResponse(["success" => true, "message" => "Return request submitted"]);
        } catch (\Exception $e) {
            return new JsonResponse(["success" => false, "message" => "Error processing return request: " . $e->getMessage()], 500);
        }
    }
}
