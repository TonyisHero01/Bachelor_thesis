<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\ReturnRequest;
use App\Entity\ShopInfo;
use App\Repository\OrderItemRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class ReturnController extends BaseController
{
    private ?ShopInfo $shopInfo = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        Environment $twig,
        LoggerInterface $logger,
    ) {
        parent::__construct($twig, $logger);

        $this->shopInfo = $this->entityManager
            ->getRepository(ShopInfo::class)
            ->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Renders the return request form for a completed order.
     */
    #[Route('/order/{id}/return-form', name: 'return_form', methods: ['GET'])]
    public function returnForm(
        int $id,
        OrderRepository $orderRepository,
        OrderItemRepository $orderItemRepository,
        Request $request,
    ): Response {
        $order = $orderRepository->find($id);
        if (!$order instanceof Order || !$order->getIsCompleted()) {
            return $this->redirectToRoute('customer_orders');
        }

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop_order/return_form.html.twig',
            [
                'show_sidebar' => false,
                'shopInfo' => $this->shopInfo,
                'locale' => (string) $request->getLocale(),
                'languages' => $this->getAvailableLanguages(),
                'order' => $order,
                'orderItems' => $orderItemRepository->findBy(['order' => $order]),
                'user' => $this->getUser(),
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Creates a return request for a completed order.
     */
    #[Route('/order/{id}/submit-return', name: 'submit_return', methods: ['POST'])]
    public function submitReturn(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        OrderRepository $orderRepository,
    ): JsonResponse {
        $order = $orderRepository->find($id);
        if (!$order instanceof Order || !$order->getIsCompleted()) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid order'], 400);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body'], 400);
        }

        if (!isset($data['items']) || !is_array($data['items']) || $data['items'] === []) {
            return new JsonResponse(['success' => false, 'message' => 'No items selected for return'], 400);
        }

        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));

        if ($email === '') {
            return new JsonResponse(['success' => false, 'message' => 'Email is required'], 400);
        }

        if ($phone === '') {
            return new JsonResponse(['success' => false, 'message' => 'Phone is required'], 400);
        }

        if ($name === '') {
            return new JsonResponse(['success' => false, 'message' => 'Name is required'], 400);
        }

        $items = array_values(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $data['items'],
        ), static fn (string $v): bool => $v !== ''));

        if ($items === []) {
            return new JsonResponse(['success' => false, 'message' => 'No items selected for return'], 400);
        }

        try {
            $returnRequest = new ReturnRequest();
            $returnRequest->setOrder($order);
            $returnRequest->setUserEmail($email);
            $returnRequest->setUserPhone($phone);
            $returnRequest->setUserName($name);
            $returnRequest->setProductSkus(implode(',', $items));
            $returnRequest->setReturnReason(
                array_key_exists('reason', $data) ? ($data['reason'] === null ? null : (string) $data['reason']) : null
            );
            $returnRequest->setUserMessage(
                array_key_exists('message', $data) ? ($data['message'] === null ? null : (string) $data['message']) : null
            );

            $entityManager->persist($returnRequest);
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Return request submitted']);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error processing return request',
            ], 500);
        }
    }
}