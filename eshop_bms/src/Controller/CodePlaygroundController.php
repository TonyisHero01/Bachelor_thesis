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
    public function index(
        Request $request,
        EntityManagerInterface $em,
        TrustedCodeRepository $trustedRepo
    ) {
        $output = '';
        $code = '';
        $selectedFile = $request->query->get('file', '');

        // 加载指定文件
        if ($selectedFile && file_exists($this->codeDir . '/' . $selectedFile)) {
            $code = file_get_contents($this->codeDir . '/' . $selectedFile);
        }

        // 是否信任此文件
        $isTrusted = $selectedFile && $trustedRepo->findOneBy(['filename' => $selectedFile]);

        // 执行代码
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
                $output = '❌ 无效或过期的验证码，运行失败。';
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

        // 保存代码
        if ($request->isMethod('POST') && $request->request->get('action') === 'save') {
            $code = $request->request->get('code', '');
            $filename = basename($request->request->get('filename', 'snippet_' . time() . '.php'));
            file_put_contents($this->codeDir . '/' . $filename, $code);
            $this->addFlash('success', "已保存为 $filename");
            return $this->redirectToRoute('code_playground', ['file' => $filename]);
        }

        // 信任代码
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
                $this->addFlash('success', "文件 $filename 已被永久信任。");
            }

            return $this->redirectToRoute('code_playground', ['file' => $filename]);
        }

        // 获取所有保存的文件列表
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