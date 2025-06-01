<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class FrontwebTranslatorController extends AbstractController
{
    #[Route('/translation/frontweb/{lang}', name: 'frontweb_translator_page_list', requirements: ['lang' => '^(?!submit$)[a-zA-Z]+'])]
    public function showPageList(string $lang): Response
    {
        return $this->render('translation/page_list.html.twig', [
            'lang' => $lang
        ]);
    }

    #[Route('/translation/frontweb/form/{path}/{lang}', name: 'frontweb_translator_form', requirements: ['path' => '.+'])]
    public function showTranslationForm(string $path, string $lang): Response
    {
        $normalized = str_replace('/', '_', $path);
        $template = 'frontweb_translator/translation_' . $normalized;

        return $this->render($template, [
            'lang' => $lang,
        ]);
    }

    #[Route('/translation/frontweb/submit', name: 'frontweb_translation_submit', methods: ['POST'])]
    public function handleTranslationSubmit(Request $request, KernelInterface $kernel, LoggerInterface $logger): Response
    {
        $language = $request->request->get('target_language');
        $originalPath = $request->request->get('original_path');

        if (!$language || !$originalPath) {
            return new Response('❌ Missing required fields', 400);
        }

        $bmsDir = $kernel->getProjectDir();
        $frontwebDir = realpath($bmsDir . '/../eshop_frontweb');

        if (!$frontwebDir) {
            return new Response('❌ Cannot locate frontweb directory', 500);
        }

        $bmsPath = $bmsDir . '/templates/' . ltrim($originalPath, '/');
        $frontwebPath = $frontwebDir . '/templates/' . ltrim($originalPath, '/');

        $sourceFile = file_exists($bmsPath) ? $bmsPath : (file_exists($frontwebPath) ? $frontwebPath : null);
        if (!$sourceFile) {
            $logger->error("[Frontweb Translator] ❌ Original file not found in either BMS or Frontweb: $originalPath");
            return new Response("❌ Original file not found: $originalPath", 404);
        }

        $relativePath = preg_replace('#^templates/#', '', ltrim($originalPath, '/'));
        $translatedFile = $frontwebDir . '/templates/locale/' . $language . '/' . $relativePath;
        $translatedDir = dirname($translatedFile);

        if (!is_dir($translatedDir) && !mkdir($translatedDir, 0777, true) && !is_dir($translatedDir)) {
            $logger->error("[Frontweb Translator] ❌ Failed to create directory: $translatedDir");
            return new Response("❌ Failed to create directory: $translatedDir", 500);
        }

        $originalContent = file_get_contents($sourceFile);
        $logger->info("[Frontweb Translator] 📄 原始模板内容加载完成");

        // 替换 {% extends ... %}
        $encodedOriginalExtends = $request->request->get('original__template_extends');
        $encodedTranslatedExtends = $request->request->get('field__template_extends');

        if ($encodedOriginalExtends && $encodedTranslatedExtends) {
            $decodedOriginal = base64_decode($encodedOriginalExtends);
            $decodedTranslated = base64_decode($encodedTranslatedExtends);

            if ($decodedOriginal && $decodedTranslated) {
                $originalContent = str_replace($decodedOriginal, $decodedTranslated, $originalContent);
                $logger->info("[Frontweb Translator] ✅ Extends replaced: $decodedOriginal → $decodedTranslated");
            }
        }

        $tokens = preg_split('/({{.*?}}|{%\s.*?%})/s', $originalContent, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($request->request->all() as $key => $value) {
            if (str_starts_with($key, 'original__') && $key !== 'original__template_extends') {
                $suffix = substr($key, strlen('original__'));
                $originalText = $value;
                $translatedText = $request->request->get('field__' . $suffix);

                if ($translatedText && $translatedText !== $originalText) {
                    $escapedOriginal = preg_quote($originalText, '/');
                    $escapedOriginal = preg_replace('/\\\\\{\\\\\{.*?\\\\\}\\\\\}/', '.*?', $escapedOriginal);
                    $pattern = '/' . $escapedOriginal . '/s';

                    foreach ($tokens as $i => $token) {
                        if (!str_starts_with(trim($token), '{%') && !str_starts_with(trim($token), '{{')) {
                            $tokens[$i] = preg_replace_callback($pattern, function ($match) use ($translatedText, $logger) {
                                $logger->info("✅ 正则命中片段：{$match[0]} → $translatedText");
                                return $translatedText;
                            }, $token, 1);
                        }
                    }
                }
            }
        }

        $translatedContent = implode('', $tokens);
        file_put_contents($translatedFile, $translatedContent);
        $logger->info("[Frontweb Translator] ✅ Translation written to $translatedFile");

        return new Response("✅ Translation saved to: <code>frontweb/templates/locale/{$language}/{$relativePath}</code>");
    }

    #[Route('/frontweb/translator/generate-form', name: 'frontweb_auto_translation_form')]
    public function generateFrontwebForm(Request $request): Response
    {
        $sourcePath = $request->query->get('path');
        $basePath = $this->getParameter('kernel.project_dir') . '/../frontweb/templates/';

        if (!$sourcePath || !file_exists($basePath . $sourcePath)) {
            return new Response("❌ frontweb 模板文件未找到", 404);
        }

        $content = file_get_contents($basePath . $sourcePath);

        preg_match_all('/>([^<>]*?){{.*?}}([^<>]*?)</', $content, $mixedMatches);
        preg_match_all('/>([^<]*?)</', $content, $pureMatches);

        $snippets = [];
        foreach ($mixedMatches[1] as $index => $before) {
            $after = $mixedMatches[2][$index];
            if (trim($before)) $snippets[] = trim($before);
            if (trim($after)) $snippets[] = trim($after);
        }
        foreach ($pureMatches[1] as $text) {
            if (trim($text) && !in_array(trim($text), $snippets)) {
                $snippets[] = trim($text);
            }
        }

        $formFields = "";
        foreach ($snippets as $i => $text) {
            $safeKey = 'auto_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower(substr($text, 0, 30))) . '_' . $i;
            $formFields .= <<<HTML
    <div class="field-group">
        <label>{$text}</label>
        <input type="hidden" name="original__{$safeKey}" value="{$text}">
        <input type="text" name="field__{$safeKey}" placeholder="{$text}">
    </div>
    HTML;
        }

        $output = <<<TWIG
    {% extends 'base.html.twig' %}

    {% block title %}Translate – Auto Generated{% endblock %}

    {% block body %}
    <form method="post" action="{{ path('frontweb_translation_submit') }}">
    <input type="hidden" name="original_path" value="{$sourcePath}">
    <input type="hidden" name="target_language" value="{{ lang }}">

    {$formFields}

    <button type="submit">Generate Translated File</button>
    </form>
    {% endblock %}
    TWIG;

        $outputPath = $basePath . 'translator/translation_' . str_replace(['/', '.html.twig'], ['_', ''], $sourcePath) . '.html.twig';
        (new \Symfony\Component\Filesystem\Filesystem())->dumpFile($outputPath, $output);

        return new Response("✅ 自动翻译表单生成成功", 200);
    }
}
