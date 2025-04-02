<?php
// src/Controller/AccountingController.php
namespace App\Controller;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\OrderRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use App\Entity\ShopInfo;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ShopInfoRepository;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class AccountingController extends BaseController
{
    private $shopInfo;
    private $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        Environment $twig,
        LoggerInterface $logger
    ) {
        parent::__construct($twig, $logger);
        $this->entityManager = $entityManager;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }

    #[Route('/bms/accounting', name: 'accounting')]
    public function index(OrderRepository $orderRepo, Request $request): Response
    {
        $orders = $orderRepo->findBy(['isCompleted' => true], ['orderCreatedAt' => 'DESC']);

        return $this->renderLocalized('accounting/index.html.twig', [
            'orders' => $orders
        ], $request);
    }

    #[Route('/bms/accounting/order/{id}', name: 'accounting_order_detail')]
    public function orderDetail(Order $order, Request $request): Response
    {
        return $this->renderLocalized('accounting/order_detail.html.twig', [
            'order' => $order,
            'translations' => $this->getTranslations($request),
        ], $request);
    }

    #[Route('/bms/accounting/order/{id}/invoice/pdf', name: 'accounting_invoice_pdf')]
    public function generatePdf(Order $order, Request $request): Response
    {
        $html = $this->renderViewLocalized('accounting/invoice.html.twig', [
            'order' => $order,
        ], $request);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice_order_' . $order->getId() . '.pdf"',
        ]);
    }

    #[Route('/bms/accounting/order/{id}/invoice/send', name: 'accounting_invoice_send')]
    public function sendInvoice(
        Order $order,
        MailerInterface $mailer,
        KernelInterface $kernel,
        ShopInfoRepository $shopInfoRepo,
        Request $request
    ): Response {
        $shopInfo = $shopInfoRepo->findOneBy([]);

        $html = $this->renderViewLocalized('accounting/invoice.html.twig', [
            'order' => $order,
            'shopInfo' => $shopInfo
        ], $request);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfPath = $kernel->getProjectDir() . '/var/invoice_' . $order->getId() . '.pdf';
        file_put_contents($pdfPath, $dompdf->output());

        $email = (new Email())
            ->from($shopInfo->getEmail() ?? 'noreply@example.com')
            ->to($order->getCustomer()->getEmail())
            ->subject('Your Invoice from ' . ($shopInfo->getEshopName() ?? 'Our Shop'))
            ->text('Please find your invoice attached.')
            ->attachFromPath($pdfPath, 'invoice.pdf');

        $mailer->send($email);

        unlink($pdfPath);

        $this->addFlash('success', 'Invoice email sent successfully!');

        // ✅ 使用 redirectToRouteLocalized，保持语言一致
        return $this->redirectToRouteLocalized('accounting_order_detail', [
            'id' => $order->getId()
        ], 302, $request);
    }
}