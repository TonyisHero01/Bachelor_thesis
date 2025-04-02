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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ReturnController extends AbstractController
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }
    #[Route('/order/{id}/return-form', name: 'return_form')]
    public function returnForm(int $id, OrderRepository $orderRepository, OrderItemRepository $orderItemRepository): \Symfony\Component\HttpFoundation\Response
    {
        $order = $orderRepository->find($id);
        if (!$order || !$order->getIsCompleted()) {
            return $this->redirectToRoute('customer_orders');
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->render('/eshop_order/return_form.html.twig', [
            'show_sidebar' => false,
            'shopInfo' => $this->shopInfo,
            'order' => $order,
            'orderItems' => $orderItemRepository->findBy(['order' => $order]),
            'user' => $this->getUser(),
            'categories' => $categories
        ]);
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

        // 🛑 确保 `items` 存在且是数组
        if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
            return new JsonResponse(["success" => false, "message" => "No items selected for return"], 400);
        }

        try {
            // ✅ `items` 已经是字符串形式，例如 `"SKU1234 x2, SKU5678 x1"`
            $formattedSkus = implode(', ', $data['items']);

            $returnRequest = new ReturnRequest();
            $returnRequest->setUserEmail($data['email'] ?? '');
            $returnRequest->setUserPhone($data['phone'] ?? '');
            $returnRequest->setUserName($data['name'] ?? '');
            $returnRequest->setOrder($order);
            $returnRequest->setProductSkus($formattedSkus);  // ✅ 直接存储逗号分隔的字符串
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