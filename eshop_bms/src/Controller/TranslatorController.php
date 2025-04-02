<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Psr\Log\LoggerInterface;

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
        return $this->render('translation/page_list.html.twig', [
            'lang' => $lang
        ]);
    }

    #[Route('/translator/form/{path}/{lang}', name: 'translator_form', requirements: ['path' => '.+'])]
    public function showTranslationForm(string $path, string $lang): Response
    {
        $normalized = str_replace('/', '_', $path);  // 'accounting/index.html.twig' → 'accounting_index.html.twig'
        $template = 'bms_translator/' . $normalized;

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
        $sourceFile = $projectDir . '/' . ltrim($originalPath, '/');

        if (!file_exists($sourceFile)) {
            $logger->error("Original file not found: $sourceFile");
            return new Response("Original file not found: $sourceFile", 404);
        }

        // 拼接翻译路径，保留相对目录结构
        $relativePath = preg_replace('#^templates/#', '', ltrim($originalPath, '/'));
        $translatedFile = $projectDir . '/templates/locale/' . $language . '/' . $relativePath;
        $translatedDir = dirname($translatedFile);

        if (!is_dir($translatedDir)) {
            if (!mkdir($translatedDir, 0777, true) && !is_dir($translatedDir)) {
                $logger->error("Failed to create directory: $translatedDir");
                return new Response("Failed to create directory: $translatedDir", 500);
            }
        }

        $originalContent = file_get_contents($sourceFile);

        // 替换原文为翻译
        foreach ($request->request->all() as $key => $value) {
            if (str_starts_with($key, 'original__')) {
                $suffix = substr($key, strlen('original__'));
                $originalText = preg_quote($value, '/');
                $translatedText = $request->request->get('field__' . $suffix);
        
                if ($translatedText && $translatedText !== $value) {
                    // 只替换 HTML 标签之间的纯文本
                    $originalContent = preg_replace(
                        "/(?<=>)\s*" . $originalText . "\s*(?=<)/",
                        $translatedText,
                        $originalContent
                    );
                }
            }
        }

        // 保存翻译后的文件
        file_put_contents($translatedFile, $originalContent);
        $logger->info("Translation written to $translatedFile");

        return new Response("Translation saved to: $translatedFile");
    }
}