<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EventManagerController extends BaseController
{
    private const ROLE_EVENT_MANAGER = 'ROLE_EVENT_MANAGER';

    /**
     * Displays the product discount management page with optional SKU/name filtering.
     */
    #[Route('/event-manager/discounts', name: 'event_manager_discounts', methods: ['GET'])]
    public function manageDiscounts(
        Request $request,
        ProductRepository $productRepository,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted(self::ROLE_EVENT_MANAGER)) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $skuFilter = (string) $request->query->get('sku', '');
        $nameFilter = (string) $request->query->get('name', '');

        $products = $productRepository->findLatestVersionProducts(
            $skuFilter !== '' ? $skuFilter : null,
            $nameFilter !== '' ? $nameFilter : null
        );

        return $this->renderLocalized(
            'event_manager/discounts.html.twig',
            [
                'products' => $products,
            ],
            $request
        );
    }

    /**
     * Applies bulk discount updates to products and ensures only the latest SKU versions are updated.
     */
    #[Route('/event-manager/update-discounts', name: 'event_manager_update_discounts', methods: ['POST'])]
    public function updateDiscounts(
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
        AuthorizationCheckerInterface $authorizationChecker
    ): JsonResponse {
        if (!$authorizationChecker->isGranted(self::ROLE_EVENT_MANAGER)) {
            return new JsonResponse(
                ['success' => false, 'message' => 'Forbidden'],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = \json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(
                ['success' => false, 'message' => 'Invalid JSON body'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $token = (string) ($data['_token'] ?? '');
        if (!$this->isCsrfTokenValid('event_manager_update_discounts', $token)) {
            return new JsonResponse(
                ['success' => false, 'message' => 'Invalid CSRF token'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $discounts = $data['discounts'] ?? null;
        if (!\is_array($discounts) || $discounts === []) {
            return new JsonResponse(
                ['success' => false, 'message' => 'Invalid request'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $latestProducts = $productRepository->findLatestVersionProducts(null, null);

        $latestBySku = [];
        foreach ($latestProducts as $p) {
            $sku = (string) $p->getSku();
            if ($sku !== '') {
                $latestBySku[$sku] = $p;
            }
        }

        $updated = 0;

        foreach ($discounts as $id => $discount) {
            if (!\is_numeric($id) || !\is_numeric($discount)) {
                continue;
            }

            $productId = (int) $id;
            $value = (float) $discount;

            if ($value < 0 || $value > 100) {
                continue;
            }

            $clicked = $productRepository->find($productId);
            if ($clicked === null) {
                continue;
            }

            $sku = (string) $clicked->getSku();
            if ($sku === '') {
                continue;
            }

            $latest = $latestBySku[$sku] ?? null;
            if ($latest === null) {
                continue;
            }

            $latest->setDiscount($value);
            $updated++;
        }

        $em->flush();

        return new JsonResponse(
            [
                'success' => true,
                'message' => 'Bulk discounts updated successfully',
                'updated' => $updated,
            ],
            Response::HTTP_OK
        );
    }
}