<?php
// src/Controller/CodePlaygroundController.php
namespace App\Controller;

use App\Entity\TrustedCode;
use App\Repository\TrustedCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\AdminCode;

class CodePlaygroundController extends AbstractController
{
    private string $codeDir;

    public function __construct()
    {
        $this->codeDir = __DIR__ . '/../../var/code_snippets';
        if (!is_dir($this->codeDir)) {
            mkdir($this->codeDir, 0777, true);
        }
    }

    #[Route('/code-playground', name: 'code_playground')]
    /**
     * Displays the code playground interface for writing, saving, trusting, and running PHP snippets.
     *
     * Features:
     * - Load and edit saved PHP snippets from the local filesystem.
     * - Save snippets as files under /var/code_snippets.
     * - Execute code securely with admin code verification (for untrusted files).
     * - Trust a file to allow future executions without requiring a code.
     * - Only users with ROLE_SUPER_ADMIN can mark a file as trusted.
     *
     * Behavior depends on the `action` POST field:
     * - `run`: Executes the current code. Requires valid admin code if file is not trusted.
     * - `save`: Saves current code to file.
     * - `trust`: Marks a file as trusted (ROLE_SUPER_ADMIN only).
     *
     * @param Request $request
     * @param EntityManagerInterface $em Used for persisting trusted files and retrieving admin codes.
     * @param TrustedCodeRepository $trustedRepo Repository for managing trusted files.
     * @return Response Renders the Twig page with code editor, file list, and execution result.
     */
    public function index(
        Request $request,
        EntityManagerInterface $em,
        TrustedCodeRepository $trustedRepo
    ) {
        $output = '';
        $code = '';
        $selectedFile = $request->query->get('file', '');

        if ($selectedFile && file_exists($this->codeDir . '/' . $selectedFile)) {
            $code = file_get_contents($this->codeDir . '/' . $selectedFile);
        }

        $isTrusted = $selectedFile && $trustedRepo->findOneBy(['filename' => $selectedFile]);

        if ($request->isMethod('POST') && $request->request->get('action') === 'run') {
            $code = $request->request->get('code', '');
            $adminCodeInput = $request->request->get('admin_code', '');
        
            $cutoff = new \DateTimeImmutable('-30 minutes');
            $latestValidCode = $em->getRepository(AdminCode::class)->createQueryBuilder('c')
                ->where('c.createdAt >= :cutoff')
                ->setParameter('cutoff', $cutoff)
                ->orderBy('c.createdAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        
            if (!$isTrusted && (!$latestValidCode || !password_verify($adminCodeInput, $latestValidCode->getCodeHash()))) {
                $output = '❌ Invalid or expired verification code, the operation fails.';
            } else {
                ob_start();
                try {
                    eval($code);
                } catch (\Throwable $e) {
                    echo 'Error: ' . $e->getMessage();
                }
                $output = ob_get_clean();
            }
        }

        if ($request->isMethod('POST') && $request->request->get('action') === 'save') {
            $code = $request->request->get('code', '');
            $filename = basename($request->request->get('filename', 'snippet_' . time() . '.php'));
            file_put_contents($this->codeDir . '/' . $filename, $code);
            $this->addFlash('success', "Saved As $filename");
            return $this->redirectToRoute('code_playground', ['file' => $filename]);
        }

        if ($request->isMethod('POST') && $request->request->get('action') === 'trust') {
            if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
                throw $this->createAccessDeniedException();
            }

            $filename = basename($request->request->get('filename', ''));
            if ($filename && !$trustedRepo->findOneBy(['filename' => $filename])) {
                $trusted = new TrustedCode();
                $trusted->setFilename($filename);
                $trusted->setCreatedAt(new \DateTimeImmutable());
                $trusted->setCreatedBy($this->getUser()?->getUserIdentifier() ?? 'unknown');
                $em->persist($trusted);
                $em->flush();
                $this->addFlash('success', "The file $filename has been permanently trusted.");
            }

            return $this->redirectToRoute('code_playground', ['file' => $filename]);
        }

        $files = array_filter(scandir($this->codeDir), fn($f) => pathinfo($f, PATHINFO_EXTENSION) === 'php');
        $trustedFilenames = array_map(
            fn($tc) => $tc->getFilename(),
            $trustedRepo->findAll()
        );

        return $this->render('code_playground/index.html.twig', [
            'code' => $code,
            'output' => $output,
            'files' => $files,
            'selectedFile' => $selectedFile,
            'isTrusted' => $isTrusted,
            'trustedFilenames' => $trustedFilenames,
        ]);
    }
}