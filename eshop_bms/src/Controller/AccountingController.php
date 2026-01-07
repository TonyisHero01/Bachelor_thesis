<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\ShopInfoRepository;
use Doctrine\Persistence\ManagerRegistry;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

class AccountingController extends BaseController
{
    private const ROLE_ACCOUNTING = 'ROLE_ACCOUNTING';

    private $em;

    public function __construct(
        ManagerRegistry $doctrine,
        Environment $twig,
        LoggerInterface $logger
    ) {
        parent::__construct($twig, $logger, $doctrine);

        $this->em = $doctrine->getManager();
    }

    /**
     * Displays a list of completed orders for accounting overview.
     */
    #[Route('/bms/accounting', name: 'accounting')]
    public function index(
        OrderRepository $orderRepo,
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted(self::ROLE_ACCOUNTING)) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $orders = $orderRepo->findBy(
            ['isCompleted' => true],
            ['orderCreatedAt' => 'DESC']
        );

        return $this->renderLocalized(
            'accounting/index.html.twig',
            ['orders' => $orders],
            $request
        );
    }

    /**
     * Displays the detail view of a completed order.
     */
    #[Route('/bms/accounting/order/{id}', name: 'accounting_order_detail')]
    public function orderDetail(
        Order $order,
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted(self::ROLE_ACCOUNTING)) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        return $this->renderLocalized(
            'accounting/order_detail.html.twig',
            [
                'order' => $order,
                'translations' => $this->getTranslations($request),
            ],
            $request
        );
    }

    /**
     * Generates a downloadable PDF invoice for the given order.
     */
    #[Route('/bms/accounting/order/{id}/invoice/pdf', name: 'accounting_invoice_pdf')]
    public function generatePdf(
        Order $order,
        Request $request,
        ShopInfoRepository $shopInfoRepository,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted(self::ROLE_ACCOUNTING)) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $shopInfo = $shopInfoRepository->findOneBy([]);

        $html = $this->renderViewLocalized(
            'accounting/invoice.html.twig',
            [
                'order' => $order,
                'shopInfo' => $shopInfo,
            ],
            $request
        );

        $options = new Options();
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="invoice_order_' . $order->getId() . '.pdf"',
            ]
        );
    }

    /**
     * Sends the invoice PDF to the customer via email.
     */
    #[Route('/bms/accounting/order/{id}/invoice/send', name: 'accounting_invoice_send', methods: ['POST'])]
    public function sendInvoice(
        Order $order,
        MailerInterface $mailer,
        KernelInterface $kernel,
        ShopInfoRepository $shopInfoRepo,
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted(self::ROLE_ACCOUNTING)) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        if (!$this->isCsrfTokenValid('send_invoice_' . $order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRouteLocalized(
                'accounting_order_detail',
                ['id' => $order->getId()],
                Response::HTTP_FOUND,
                $request
            );
        }

        $shopInfo = $shopInfoRepo->findOneBy([]);

        $html = $this->renderViewLocalized(
            'accounting/invoice.html.twig',
            [
                'order' => $order,
                'shopInfo' => $shopInfo,
            ],
            $request
        );

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $tmp = \tempnam($kernel->getProjectDir() . '/var', 'invoice_');
        if ($tmp === false) {
            $this->addFlash('danger', 'Failed to create invoice file.');

            return $this->redirectToRouteLocalized(
                'accounting_order_detail',
                ['id' => $order->getId()],
                Response::HTTP_FOUND,
                $request
            );
        }

        $pdfPath = $tmp . '.pdf';
        \rename($tmp, $pdfPath);
        \file_put_contents($pdfPath, $dompdf->output());

        try {
            $email = (new Email())
                ->from(($shopInfo?->getEmail()) ?? 'noreply@example.com')
                ->to($order->getCustomer()->getEmail())
                ->subject('Your Invoice from ' . (($shopInfo?->getEshopName()) ?? 'Our Shop'))
                ->text('Please find your invoice attached.')
                ->attachFromPath($pdfPath, 'invoice.pdf');

            $mailer->send($email);

            $this->addFlash('success', 'Invoice email sent successfully!');
        } finally {
            @\unlink($pdfPath);
        }

        return $this->redirectToRouteLocalized(
            'accounting_order_detail',
            ['id' => $order->getId()],
            Response::HTTP_FOUND,
            $request
        );
    }
}