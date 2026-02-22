<?php

namespace App\Controller;

use App\Service\ShipmentService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bms/orders')]
#[IsGranted('ROLE_WAREHOUSE_MANAGER')]
class OrderAdminController extends AbstractController
{
    private const CSRF_SHIPMENT_ADVANCE = 'bms_order_shipment_advance';
    private const ALLOWED = [
        'PACKED',
        'SHIPPED',
        'IN_TRANSIT',
        'OUT_FOR_DELIVERY',
        'DELIVERED',
        'RETURNED',
    ];

    #[Route('/{id}/shipment/advance', name: 'bms_order_shipment_advance', methods: ['POST'])]
    /**
     * Advances shipment status for a given order to the requested target state.
     */
    public function advance(int $id, Request $request, ShipmentService $service, LoggerInterface $logger): RedirectResponse
    {
        $user = $this->getUser();
        $actor = \is_object($user) && method_exists($user, 'getUserIdentifier') ? (string) $user->getUserIdentifier() : '';

        if ($actor === '') {
            return $this->redirectToRoute('order_detail', ['id' => $id]);
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_SHIPMENT_ADVANCE, $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('order_detail', ['id' => $id]);
        }

        $to = strtoupper(trim((string) $request->request->get('to', '')));
        if (!\in_array($to, self::ALLOWED, true)) {
            $this->addFlash('error', 'Invalid target shipment status.');
            return $this->redirectToRoute('order_detail', ['id' => $id]);
        }

        try {
            $service->advanceTo($id, $to, $actor);
            $this->addFlash('success', 'Shipment status updated.');
        } catch (\Throwable $e) {
            $logger->error('[OrderAdminController] advance failed: ' . $e->getMessage());
            $this->addFlash('error', 'Failed to update shipment status.');
        }

        return $this->redirectToRoute('order_detail', ['id' => $id]);
    }
}