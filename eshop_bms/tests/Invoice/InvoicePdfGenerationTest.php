<?php

namespace App\Tests\Invoice;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

final class InvoicePdfGenerationTest extends KernelTestCase
{
    public function testInvoicePdfIsGeneratedInSubprocess(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $script = sys_get_temp_dir() . '/invoice_pdf_test_' . uniqid() . '.php';

        $code = <<<PHP
<?php
require '{$projectRoot}/vendor/autoload.php';

use App\Kernel;
use App\Entity\Order;
use Dompdf\Dompdf;
use Dompdf\Options;

\$kernel = new Kernel('test', true);
\$kernel->boot();

\$container = \$kernel->getContainer();
\$doctrine = \$container->get('doctrine');
\$em = \$doctrine->getManager();

\$order = \$em->getRepository(Order::class)->findOneBy(['isCompleted' => true]);

if (!\$order) {
    fwrite(STDERR, "No completed order found\\n");
    exit(2);
}

\$options = new Options();
\$options->set('defaultFont', 'DejaVu Sans');
\$options->set('isRemoteEnabled', false);

\$tmp = sys_get_temp_dir();
\$options->set('tempDir', \$tmp);
\$options->set('fontDir', \$tmp);
\$options->set('fontCache', \$tmp);

\$dompdf = new Dompdf(\$options);
\$dompdf->loadHtml('<html><body><h1>Invoice '.\$order->getId().'</h1></body></html>');
\$dompdf->setPaper('A4', 'portrait');
\$dompdf->render();

\$pdf = \$dompdf->output();

if (substr(\$pdf, 0, 4) !== '%PDF') {
    fwrite(STDERR, "Not a PDF output\\n");
    exit(3);
}

if (strlen(\$pdf) < 100) {
    fwrite(STDERR, "PDF too small\\n");
    exit(4);
}

echo "PDF_OK\\n";
PHP;

        file_put_contents($script, $code);

        $process = new Process([
            'php',
            '-d', 'xdebug.mode=off',
            $script
        ]);

        $process->setTimeout(15);
        $process->run();

        @unlink($script);

        $this->assertTrue(
            $process->isSuccessful(),
            "PDF generation failed.\nSTDOUT:\n{$process->getOutput()}\nSTDERR:\n{$process->getErrorOutput()}"
        );

        $this->assertStringContainsString('PDF_OK', $process->getOutput());
    }
}