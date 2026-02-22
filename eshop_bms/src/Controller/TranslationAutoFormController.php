<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TRANSLATOR')]
class TranslationAutoFormController extends AbstractController
{
    private const LANG_PATTERN = '/^[a-zA-Z]{2,10}$/';
    private const CSRF_AUTOFORM = 'translation_auto_form';

    private EntityManagerInterface $entityManager;
    private Filesystem $fs;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->fs = new Filesystem();
    }

    /**
     * Generates a translation form for a single template file based on the "path" query parameter.
     */
    #[Route('/translator/generate-form', name: 'generate_translation_form', methods: ['GET'])]
    #[Route('/frontweb/translator/generate-form', name: 'frontweb_auto_translation_form', methods: ['GET'])]
    public function generateForm(Request $request, KernelInterface $kernel, LoggerInterface $logger): Response
    {
        $token = (string) $request->query->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_AUTOFORM, $token)) {
            return new Response('❌ Invalid CSRF token', Response::HTTP_BAD_REQUEST);
        }

        return $this->handleSingleGeneration($request, $kernel, $logger);
    }

    /**
     * Scans templates in both BMS and Frontweb directories and generates translation forms for each eligible file.
     */
    #[Route('/translator/generate-all', name: 'generate_all_translation_forms', methods: ['GET'])]
    public function generateAll(Request $request, KernelInterface $kernel, LoggerInterface $logger): Response
    {
        $token = (string) $request->query->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_AUTOFORM, $token)) {
            return new Response('❌ Invalid CSRF token', Response::HTTP_BAD_REQUEST);
        }

        $projectDir = $kernel->getProjectDir();
        $bmsTemplates = realpath($projectDir . '/templates') ?: null;

        $frontwebTemplatesRaw = '/var/www/eshop_frontweb_templates';
        $frontwebTemplates = realpath($frontwebTemplatesRaw) ?: null;

        $scanDirs = array_values(array_filter([$bmsTemplates, $frontwebTemplates], fn ($p) => is_string($p) && is_dir($p)));

        if ($scanDirs === []) {
            return new Response('❌ No valid template directories found.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $finder = new Finder();
        $finder->files()
            ->in($scanDirs)
            ->name('*.html.twig')
            ->filter(function (\SplFileInfo $file) {
                $path = (string) $file->getRealPath();
                return $path !== '' && !preg_match('#/(translator|translation|locale)(/|_)#', $path);
            });

        $scanned = [];
        $success = [];
        $errors = [];

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            if (!is_string($realPath) || $realPath === '') {
                continue;
            }

            $isFrontweb = $frontwebTemplates !== null && str_starts_with($realPath, $frontwebTemplates . DIRECTORY_SEPARATOR);

            if (str_contains($realPath, '_form')) {
                continue;
            }

            if (!$isFrontweb && str_ends_with($realPath, 'base.html.twig')) {
                continue;
            }

            $basePath = $isFrontweb ? $frontwebTemplates : $bmsTemplates;
            if (!is_string($basePath) || !str_starts_with($realPath, $basePath . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $sourcePath = Path::makeRelative($realPath, $basePath);
            $scanned[] = $sourcePath;

            $subRequest = clone $request;
            $subRequest->query->set('path', $sourcePath);

            try {
                $result = $this->handleSingleGeneration($subRequest, $kernel, $logger);
                if ($result->getStatusCode() === 200) {
                    $success[] = $sourcePath;
                } else {
                    $errors[] = $sourcePath . ' → HTTP ' . $result->getStatusCode();
                }
            } catch (\Throwable $e) {
                $errors[] = $sourcePath . ' → Exception: ' . $e->getMessage();
            }
        }

        try {
            $this->generateShopInfoForm($kernel, $logger);
        } catch (\Throwable $e) {
            $errors[] = 'shop_info → Exception: ' . $e->getMessage();
        }

        $li = static fn (string $s): string => '<li>' . htmlspecialchars($s, ENT_QUOTES) . '</li>';

        $html = '<h1>✅ Auto Translation Summary</h1>';
        $html .= '<h2>Scanned:</h2><ul>' . implode('', array_map($li, $scanned)) . '</ul>';
        $html .= '<h2>✅ Success:</h2><ul>' . implode('', array_map($li, $success)) . '</ul>';
        $html .= '<h2>❌ Errors:</h2><ul>' . implode('', array_map(fn ($e) => '<li style="color:red;">' . htmlspecialchars($e, ENT_QUOTES) . '</li>', $errors)) . '</ul>';

        return new Response($html, Response::HTTP_OK);
    }

    /**
     * Generates a ShopInfo translation form template and saves it under templates/translator/.
     */
    private function generateShopInfoForm(KernelInterface $kernel, LoggerInterface $logger): void
    {
        $projectDir = $kernel->getProjectDir();

        $shopInfo = $this->entityManager->getRepository(\App\Entity\ShopInfo::class)->find(1);
        if ($shopInfo === null) {
            throw new \RuntimeException('ShopInfo not found (id=1).');
        }

        $fields = [
            'aboutUs' => 'About Us',
            'howToOrder' => 'How to Order',
            'businessConditions' => 'Business Conditions',
            'privacyPolicy' => 'Privacy Policy',
            'shippingInfo' => 'Shipping Info',
            'payment' => 'Payment',
            'refund' => 'Refund',
        ];

        $formFields = '';
        foreach ($fields as $field => $label) {
            $getter = 'get' . ucfirst($field);
            $originalValue = method_exists($shopInfo, $getter) ? (string) ($shopInfo->$getter() ?? '') : '';

            $escapedLabel = htmlspecialchars($label, ENT_QUOTES);
            $escapedOriginalValue = htmlspecialchars($originalValue, ENT_QUOTES);

            $formFields .= <<<HTML
<div class="field-group">
    <label>{$escapedLabel}</label>
    <div class="original-text" style="margin: 5px 0;"><strong>Original:</strong> <em>{$escapedOriginalValue}</em></div>
    <textarea name="field__{$field}" placeholder="{$escapedLabel}"></textarea>
</div>

HTML;
        }

        $output = <<<TWIG
{% extends 'base.html.twig' %}

{% block title %}Translate Shop Info{% endblock %}

{% block body %}
<link rel="stylesheet" href="{{ asset('../assets/styles/translation_form.css') }}">

<form method="post" action="{{ path('translation_shop_info_submit') }}">
    <input type="hidden" name="_token" value="{{ csrf_token('translation_shop_info_submit') }}">
    <input type="hidden" name="target_language" value="{{ lang }}">

{$formFields}

    <button type="submit">Save Translations</button>
</form>
{% endblock %}
TWIG;

        $outputPath = $projectDir . '/templates/translator/translation_shop_info.html.twig';
        $this->fs->dumpFile($outputPath, $output);
    }

    /**
     * Reads a Twig template, extracts translatable static text, and writes a Twig form under templates/translator/.
     */
    private function handleSingleGeneration(Request $request, KernelInterface $kernel, LoggerInterface $logger): Response
    {
        $sourcePathRaw = (string) $request->query->get('path', '');
        if ($sourcePathRaw === '') {
            return new Response("Missing 'path' parameter.", Response::HTTP_BAD_REQUEST);
        }

        $targetLang = (string) $request->query->get('target_language', '');
        if ($targetLang === '') {
            $referer = (string) $request->headers->get('referer', '');
            if (preg_match('#/([a-zA-Z]{2,10})(\?.*)?$#', $referer, $m)) {
                $targetLang = $m[1];
            } else {
                $targetLang = 'en';
            }
        }

        $targetLang = strtolower($targetLang);
        if (!preg_match(self::LANG_PATTERN, $targetLang)) {
            return new Response('Invalid target_language.', Response::HTTP_BAD_REQUEST);
        }

        $projectDir = $kernel->getProjectDir();

        $bmsBase = realpath($projectDir . '/templates') ?: null;
        $frontwebBase = realpath('/var/www/eshop_frontweb_templates') ?: null;

        $sourcePath = $this->normalizeRelativeTwigPath($sourcePathRaw);

        $bmsFull = $bmsBase ? realpath($bmsBase . '/' . $sourcePath) : false;
        $frontwebFull = $frontwebBase ? realpath($frontwebBase . '/' . $sourcePath) : false;

        $fullPath = null;
        $isFrontweb = false;

        if (is_string($frontwebFull) && $frontwebBase && str_starts_with($frontwebFull, $frontwebBase . DIRECTORY_SEPARATOR) && is_file($frontwebFull)) {
            $fullPath = $frontwebFull;
            $isFrontweb = true;
        } elseif (is_string($bmsFull) && $bmsBase && str_starts_with($bmsFull, $bmsBase . DIRECTORY_SEPARATOR) && is_file($bmsFull)) {
            $fullPath = $bmsFull;
        }

        if ($fullPath === null) {
            return new Response('Source template not found.', Response::HTTP_NOT_FOUND);
        }

        $content = (string) file_get_contents($fullPath);

        $content = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content) ?? $content;
        $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content) ?? $content;
        $content = preg_replace('/\{\#.*?\#\}/s', '', $content) ?? $content;
        $content = preg_replace('/<!--.*?-->/s', '', $content) ?? $content;

        preg_match_all('/>([^<>]*?){{.*?}}([^<>]*?)</', $content, $mixedMatches);
        preg_match_all('/>([^<]*?)</', $content, $pureMatches);

        $snippets = [];
        foreach ($mixedMatches[1] as $i => $before) {
            $after = $mixedMatches[2][$i] ?? '';
            if (trim((string) $before) !== '') {
                $snippets[] = trim((string) $before);
            }
            if (trim((string) $after) !== '') {
                $snippets[] = trim((string) $after);
            }
        }
        foreach ($pureMatches[1] as $text) {
            $clean = trim((string) $text);
            if ($clean !== '' && !in_array($clean, $snippets, true)) {
                $snippets[] = $clean;
            }
        }

        $formFields = '';
        foreach ($snippets as $i => $text) {
            if (str_contains($text, '{%') || str_contains($text, '{{')) {
                continue;
            }

            $keyBase = strtolower(substr($text, 0, 30));
            $keyBase = preg_replace('/[^a-z0-9]+/i', '_', $keyBase) ?: 'text';
            $safeKey = 'auto_' . $keyBase . '_' . $i;

            $escapedText = htmlspecialchars($text, ENT_QUOTES);

            $formFields .= <<<HTML
<div class="field-group">
    <label>{$escapedText}</label>
    <input type="hidden" name="original__{$safeKey}" value="{$escapedText}">
    <input type="text" name="field__{$safeKey}" placeholder="{$escapedText}">
</div>

HTML;
        }

        if ($isFrontweb && preg_match("/{%\\s*extends\\s+'(eshop_base\\.html\\.twig|base\\.html\\.twig)'\\s*%}/", $content, $match)) {
            $originalExtends = "{% extends '" . $match[1] . "' %}";
            $translatedExtends = $match[1] === 'eshop_base.html.twig'
                ? "{% extends 'locale/{$targetLang}/eshop_base.html.twig' %}"
                : $originalExtends;

            $formFields .= '<input type="hidden" name="original__template_extends" value="' . htmlspecialchars(base64_encode($originalExtends), ENT_QUOTES) . '">' . "\n";
            $formFields .= '<input type="hidden" name="field__template_extends" value="' . htmlspecialchars(base64_encode($translatedExtends), ENT_QUOTES) . '">' . "\n";
        }

        $prefix = $isFrontweb ? 'translation_frontweb_' : 'translation_';
        $filename = $prefix . str_replace(['/', '.html.twig'], ['_', ''], $sourcePath) . '.html.twig';
        $outputPath = $projectDir . '/templates/translator/' . $filename;

        $actionRoute = $isFrontweb ? 'frontweb_translation_submit' : 'translation_submit';
        $csrfId = $isFrontweb ? 'frontweb_translation_submit' : 'translation_submit';

        $escapedSourcePath = htmlspecialchars($sourcePath, ENT_QUOTES);

        $output = <<<TWIG
{% extends 'base.html.twig' %}

{% block title %}Translate – Auto Generated{% endblock %}

{% block body %}
<link rel="stylesheet" href="{{ asset('../assets/styles/translation_form.css') }}">

<form method="post" action="{{ path('{$actionRoute}') }}">
    <input type="hidden" name="_token" value="{{ csrf_token('{$csrfId}') }}">
    <input type="hidden" name="original_path" value="{$escapedSourcePath}">
    <input type="hidden" name="target_language" value="{{ lang }}">

{$formFields}

    <button type="submit">Generate Translated File</button>
</form>
{% endblock %}
TWIG;

        $this->fs->dumpFile($outputPath, $output);

        return new Response('✅ Generated: ' . htmlspecialchars($filename, ENT_QUOTES), Response::HTTP_OK);
    }

    /**
     * Normalizes and validates a relative Twig template path to prevent traversal.
     */
    private function normalizeRelativeTwigPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '' || str_contains($path, "\0")) {
            throw $this->createNotFoundException('Invalid path');
        }

        if (str_starts_with($path, '/')) {
            throw $this->createNotFoundException('Invalid path');
        }

        if (preg_match('#(^|/)\.\.(?:/|$)#', $path) === 1) {
            throw $this->createNotFoundException('Invalid path');
        }

        return ltrim($path, '/');
    }
}