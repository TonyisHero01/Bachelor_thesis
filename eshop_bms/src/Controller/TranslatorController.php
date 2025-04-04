<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class TranslatorController extends AbstractController
{
    #[Route('/translation', name: 'translator_languages')]
    public function showLanguages(KernelInterface $kernel): Response
    {
        $localeDir = $kernel->getProjectDir() . '/templates/locale';
        $languages = [];

        if (is_dir($localeDir)) {
            foreach (scandir($localeDir) as $item) {
                if ($item !== '.' && $item !== '..' && is_dir($localeDir . '/' . $item)) {
                    $languages[] = $item;
                }
            }
        }

        return $this->render('translation/languages.html.twig', [
            'languages' => $languages
        ]);
    }

    #[Route('/translation/add-language', name: 'translator_add_language', methods: ['POST'])]
    public function addLanguage(Request $request, KernelInterface $kernel, LoggerInterface $logger): Response
    {
        $language = $request->request->get('language');
        $logger->info('Language input received: ' . $language);

        if (!$language) {
            return new Response('Missing language', 400);
        }

        $localeDir = $kernel->getProjectDir() . '/templates/locale/' . $language;

        if (!is_dir($localeDir)) {
            mkdir($localeDir, 0777, true);
        }

        return $this->redirectToRoute('translator_languages');
    }

    #[Route('/translation/{lang}', name: 'translator_page_list', requirements: ['lang' => '^(?!submit$)[a-zA-Z]+'])]
    public function showPageListForLanguage(string $lang): Response
    {
        $translatorDir = $this->getParameter('kernel.project_dir') . '/templates/translator/';
        $finder = new Finder();
        $finder->files()->in($translatorDir)->name('/^translation_.*\.html\.twig$/');

        $frontwebPages = [];
        $bmsPages = [];

        foreach ($finder as $file) {
            $filename = $file->getFilename();
            $isFrontweb = str_starts_with($filename, 'translation_frontweb_');

            $key = $isFrontweb
                ? preg_replace('/^translation_frontweb_|\.html\.twig$/', '', $filename)
                : preg_replace('/^translation_|\.html\.twig$/', '', $filename);

            if ($isFrontweb) {
                $frontwebPages[$key] = $filename;
            } else {
                $bmsPages[$key] = $filename;
            }
        }

        ksort($bmsPages);
        ksort($frontwebPages);

        return $this->render('translation/page_list.html.twig', [
            'lang' => $lang,
            'bmsPages' => $bmsPages,
            'frontwebPages' => $frontwebPages,
        ]);
    }

    #[Route('/translator/form/{path}/{lang}', name: 'translator_form', requirements: ['path' => '.+'])]
    public function showTranslationForm(string $path, string $lang): Response
    {
        $normalized = str_replace('/', '_', $path);
        $template = 'translator/' . $normalized;

        return $this->render($template, [
            'lang' => $lang,
        ]);
    }

    #[Route('/frontweb/translator/form/{path}/{lang}', name: 'frontweb_translator_form', requirements: ['path' => '.+'])]
    public function showFrontwebTranslationForm(string $path, string $lang): Response
    {
        $normalized = str_replace('/', '_', $path);
        $template = 'translator/' . $normalized;

        return $this->render($template, [
            'lang' => $lang,
        ]);
    }

    #[Route('/translation/delete-language/{lang}', name: 'translator_delete_language', methods: ['POST'])]
    public function deleteLanguage(string $lang, KernelInterface $kernel): Response
    {
        $dir = $kernel->getProjectDir() . '/templates/locale/' . $lang;

        if (is_dir($dir)) {
            $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file) : unlink($file);
            }
            rmdir($dir);
        }

        return $this->redirectToRoute('translator_languages');
    }

    #[Route('/translation/submit', name: 'translation_submit', methods: ['POST'])]
    public function handleTranslationSubmit(Request $request, KernelInterface $kernel, LoggerInterface $logger): Response
    {
        $language = $request->request->get('target_language');
        $originalPath = $request->request->get('original_path');

        if (!$language || !$originalPath) {
            return new Response('Missing required fields', 400);
        }

        $projectDir = $kernel->getProjectDir();

        $isFrontweb = $request->request->get('is_frontweb') === '1'
            || str_starts_with($originalPath, 'eshop/')
            || str_starts_with($originalPath, 'customer/');

        $sourceFile = $isFrontweb
            ? realpath($projectDir . '/../eshop_frontweb/templates/' . $originalPath)
            : realpath($projectDir . '/templates/' . $originalPath);

        if (!file_exists($sourceFile)) {
            $logger->error("❌ Original file not found: $sourceFile");
            return new Response("Original file not found: $sourceFile", 404);
        }

        $baseDir = $isFrontweb
            ? $projectDir . '/../eshop_frontweb/templates/locale/' . $language
            : $projectDir . '/templates/locale/' . $language;

        $translatedFile = $baseDir . '/' . $originalPath;
        $translatedDir = dirname($translatedFile);

        if (!is_dir($translatedDir)) {
            if (!mkdir($translatedDir, 0777, true) && !is_dir($translatedDir)) {
                $logger->error("❌ Failed to create directory: $translatedDir");
                return new Response("Failed to create directory: $translatedDir", 500);
            }
        }

        $originalContent = file_get_contents($sourceFile);
        $logger->info("📄 原始内容:\n" . $originalContent);

        $twigPlaceholders = [];

        $originalContent = preg_replace_callback('/{{.*?}}/s', function ($matches) use (&$twigPlaceholders) {
            $key = '__TWIG_EXPR__' . count($twigPlaceholders) . '__';
            $twigPlaceholders[$key] = $matches[0];
            return $key;
        }, $originalContent);

        $originalContent = preg_replace_callback('/{%\s*(.*?)\s*%}/s', function ($matches) use (&$twigPlaceholders) {
            $content = trim($matches[1]);
            if (str_starts_with($content, 'extends')) {
                return $matches[0];
            }
            $key = '__TWIG_BLOCK__' . count($twigPlaceholders) . '__';
            $twigPlaceholders[$key] = $matches[0];
            return $key;
        }, $originalContent);

        foreach ($request->request->all() as $key => $value) {
            if (str_starts_with($key, 'original__')) {
                $suffix = substr($key, strlen('original__'));
                $originalText = $value;
                $translatedText = $request->request->get('field__' . $suffix);

                if ($translatedText && $translatedText !== $originalText) {
                    $logger->info("🔄 替换: '$originalText' → '$translatedText'");
                    $originalContent = preg_replace_callback(
                        '/(?<=>)([^<]*)(?=<)/',
                        function ($matches) use ($originalText, $translatedText) {
                            return trim($matches[1]) === $originalText ? $translatedText : $matches[1];
                        },
                        $originalContent
                    );
                }
            }
        }

        if (
            $request->request->has('original__template_extends') &&
            $request->request->has('field__template_extends')
        ) {
            $originalExtends = base64_decode($request->request->get('original__template_extends'));
            $translatedExtends = base64_decode($request->request->get('field__template_extends'));

            $logger->info("🧩 替换 extends 指令: $originalExtends → $translatedExtends");

            $originalContent = preg_replace(
                '/' . preg_quote($originalExtends, '/') . '/',
                $translatedExtends,
                $originalContent
            );
        }

        $originalContent = strtr($originalContent, $twigPlaceholders);

        $logger->info("💾 最终翻译文件内容:\n" . $originalContent);

        // 检查翻译模板是否调用了不存在的 shopInfo 方法
        if (preg_match_all('/shopInfo\.get([a-zA-Z0-9_]+)\(\)/', $originalContent, $matches)) {
            foreach ($matches[1] as $methodSuffix) {
                $fullMethod = 'get' . $methodSuffix;
                if (!method_exists(\App\Entity\ShopInfo::class, $fullMethod)) {
                    $logger->warning("⚠️ 翻译文件中调用了不存在的 shopInfo 方法: $fullMethod()");
                }
            }
        }

        if ($isFrontweb) {
            $this->logger->warning('Is Frontweb!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
            // 💡 检查翻译内容是否有危险方法
            if (str_contains($translatedTemplateContent, 'shopInfo.getHideCenas')) {
                $this->logger->warning('[Frontweb Translator] ⚠️ 翻译文件中调用了不存在的 shopInfo 方法: getHideCenas()');
            }
        
            // 然后才写入文件
            file_put_contents($targetFilePath, $translatedTemplateContent);
            $this->logger->info("[Frontweb Translator] ✅ Translation written to $targetFilePath");
        }

        #file_put_contents($translatedFile, $originalContent);
        #$logger->info("✅ 已保存翻译至: $translatedFile");

        return new Response("✅ Translation saved to: $translatedFile");
    }
}
