<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ShipmentRepository;
use App\Repository\ShopInfoRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class TrackController extends BaseController
{
    public function __construct(
        private ShopInfoRepository $shopInfoRepo,
        private ProductRepository $productRepo,
        private CategoryRepository $categoryRepo,
        Environment $twig,
        LoggerInterface $logger,
    ) {
        parent::__construct($twig, $logger);
    }

    /**
     * Displays public shipment tracking detail by tracking number.
     *
     * @param string $trackingNumber Shipment tracking number
     * @param ShipmentRepository $shipRepo Shipment repository
     * @param Request $request HTTP request
     */
    #[Route('/track/{trackingNumber}', name: 'frontweb_track_public', methods: ['GET'])]
    public function trackPublic(
        string $trackingNumber,
        ShipmentRepository $shipRepo,
        Request $request,
    ): Response {
        $shipment = $shipRepo->findOneBy(['trackingNumber' => $trackingNumber]);
        if (!$shipment) {
            throw $this->createNotFoundException('Tracking number not found.');
        }

        $order = $shipment->getOrder();

        return $this->renderLocalized(
            'eshop_order/tracking.html.twig',
            array_merge(
                [
                    'shipment' => $shipment,
                    'order' => $order,
                    'public' => true,
                ],
                $this->layoutGlobals($request),
            ),
            $request,
        );
    }

    /**
     * Displays shipment tracking for an authenticated customer's order.
     *
     * @param int $id Order ID
     * @param OrderRepository $orders Order repository
     * @param Request $request HTTP request
     */
    #[Route('/my-order/{id}/tracking', name: 'frontweb_track_my_order', methods: ['GET'])]
    public function trackMyOrder(
        int $id,
        OrderRepository $orders,
        Request $request,
    ): Response {
        /** @var Order|null $order */
        $order = $orders->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $customer = $order->getCustomer();
        if (!$customer || $customer->getEmail() !== $this->getUser()?->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        $shipment = $order->getShipment();

        return $this->renderLocalized(
            'eshop_order/tracking.html.twig',
            array_merge(
                [
                    'shipment' => $shipment,
                    'order' => $order,
                    'public' => false,
                ],
                $this->layoutGlobals($request),
            ),
            $request,
        );
    }

    /**
     * Builds common template variables for header/footer layout rendering.
     *
     * @param Request $request HTTP request
     *
     * @return array<string, mixed>
     */
    private function layoutGlobals(Request $request): array
    {
        $locale = (string) ($request->get('_locale') ?? $request->query->get('_locale') ?? $request->getLocale());

        $shopInfo = $this->shopInfoRepo->findOneBy([], ['id' => 'DESC']);

        $newProducts = method_exists($this->productRepo, 'findNewProducts')
            ? $this->productRepo->findNewProducts(8)
            : $this->productRepo->findBy([], ['id' => 'DESC'], 8);

        $popularProducts = method_exists($this->productRepo, 'findPopularProducts')
            ? $this->productRepo->findPopularProducts(8)
            : $this->productRepo->findBy([], ['id' => 'DESC'], 8);

        $categories = method_exists($this->categoryRepo, 'findAllCategories')
            ? $this->categoryRepo->findAllCategories()
            : $this->categoryRepo->findAll();

        return [
            'show_sidebar' => false,
            'shopInfo' => $shopInfo,
            'locale' => $locale,
            'languages' => $this->getAvailableLanguages(),
            'new_products' => $newProducts,
            'popular_products' => $popularProducts,
            'categories' => $categories,
        ];
    }
}