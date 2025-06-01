<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManagerInterface;

class TranslationAutoFormController extends AbstractController
{

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    #[Route('/translator/generate-form', name: 'generate_translation_form')]
    #[Route('/frontweb/translator/generate-form', name: 'frontweb_auto_translation_form')]
    public function generateForm(Request $request, LoggerInterface $logger): Response
    {
        return $this->handleSingleGeneration($request, $logger);
    }

    #[Route('/translator/generate-all', name: 'generate_all_translation_forms')]
    public function generateAll(Request $request, LoggerInterface $logger): Response
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $bmsTemplates = $projectDir . '/templates';
        $frontwebTemplates = realpath($projectDir . '/../eshop_frontweb/templates');

        $finder = new Finder();
        $finder->files()
            ->in([$bmsTemplates, $frontwebTemplates])
            ->name('*.html.twig')
            ->filter(function (\SplFileInfo $file) {
                $path = $file->getRealPath();
                return !preg_match('#/(translator|translation|locale)(/|_)#', $path);
            });

        $success = [];
        $errors = [];
        $scanned = [];

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            $isFrontweb = str_starts_with($realPath, $frontwebTemplates);

            if (str_contains($realPath, '_form')) {
                $logger->info("\u{1F6AB} Skipped _form partial: $realPath");
                continue;
            }
            
            if (!$isFrontweb && str_ends_with($realPath, 'base.html.twig')) {
                $logger->info("\u{1F6AB} Skipped BMS base template: $realPath");
                continue;
            }

            $sourcePath = str_replace(($isFrontweb ? $frontwebTemplates : $bmsTemplates) . '/', '', $realPath);
            $scanned[] = $sourcePath;
            $subRequest = clone $request;
            $subRequest->query->set('path', $sourcePath);

            try {
                $result = $this->handleSingleGeneration($subRequest, $logger);
                if ($result->getStatusCode() === 200) {
                    $success[] = $sourcePath;
                } else {
                    $errors[] = "$sourcePath → HTTP {$result->getStatusCode()}";
                }
            } catch (\Throwable $e) {
                $errors[] = "$sourcePath → Exception: {$e->getMessage()}";
            }
        }

        $localeDir = $projectDir . '/templates/locale';
        $langs = array_filter(scandir($localeDir), fn($l) => !in_array($l, ['.', '..']) && is_dir($localeDir . '/' . $l));
        $this->generateShopInfoForm($logger);

        $html = "<h1>✅ Auto Translation Summary</h1>";
        $html .= "<h2>Scanned:</h2><ul>" . implode('', array_map(fn($p) => "<li>$p</li>", $scanned)) . "</ul>";
        $html .= "<h2>✅ Success:</h2><ul>" . implode('', array_map(fn($p) => "<li>$p</li>", $success)) . "</ul>";
        $html .= "<h2>❌ Errors:</h2><ul>" . implode('', array_map(fn($e) => "<li style='color:red;'>$e</li>", $errors)) . "</ul>";

        return new Response($html);
    }

    private function generateShopInfoForm(LoggerInterface $logger): void
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $fields = [
            'aboutUs' => 'About Us',
            'howToOrder' => 'How to Order',
            'businessConditions' => 'Business Conditions',
            'privacyPolicy' => 'Privacy Policy',
            'shippingInfo' => 'Shipping Info',
            'payment' => 'Payment',
            'refund' => 'Refund',
        ];

        $shopInfo = $this->entityManager->getRepository(\App\Entity\ShopInfo::class)->find(1);

        $formFields = '';
        foreach ($fields as $field => $label) {
            $escapedLabel = htmlspecialchars($label, ENT_QUOTES);
            $originalValue = htmlspecialchars($shopInfo->{'get' . ucfirst($field)}() ?? '', ENT_QUOTES);

            $formFields .= <<<HTML
    <div class="field-group">
        <label>{$escapedLabel}</label>
        <div class="original-text" style="margin: 5px 0;"><strong>Original:</strong> <em>{$originalValue}</em></div>
        <input type="hidden" name="original__{$field}" value="{$escapedLabel}">
        <textarea name="field__{$field}" placeholder="{$escapedLabel}"></textarea>
    </div>

    HTML;
        }

        $output = <<<TWIG
    {% extends 'base.html.twig' %}

    {% block title %}Translate Shop Info{% endblock %}

    {% block body %}
    <p style="padding: 10px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">
        ⚠️ Please fill in the localized version of the website content for selected language.
    </p>

    <form method="post" action="{{ path('translation_shop_info_submit') }}">
        <input type="hidden" name="target_language" value="{{ lang }}">

    {$formFields}
        <button type="submit">Save Translations</button>
    </form>
    {% endblock %}
    TWIG;

        $outputPath = "$projectDir/templates/translator/translation_shop_info.html.twig";
        (new Filesystem())->dumpFile($outputPath, $output);
        $logger->info("✅ Generated shop_info form");
    }

    private function handleSingleGeneration(Request $request, LoggerInterface $logger): Response
    {
        $sourcePath = $request->get('path');
        $targetLang = $request->query->get('target_language');

        if (!$targetLang) {
            $referer = $request->headers->get('referer');
            if (preg_match('#/([A-Z]{2})(\?.*)?$#', $referer ?? '', $matches)) {
                $targetLang = $matches[1];
            } else {
                $targetLang = 'en';
            }
        }

        $logger->info("🌐 target_language resolved as: $targetLang");
        $logger->info("🔍 Requested template path: $sourcePath with target_language: $targetLang");

        if (!$sourcePath) {
            return new Response("Missing 'path' parameter.", 400);
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $bmsPath = $projectDir . '/templates/' . $sourcePath;
        $frontwebPath = $projectDir . '/../eshop_frontweb/templates/' . $sourcePath;

        $fullPath = null;
        $isFrontweb = false;

        if (file_exists($frontwebPath)) {
            $fullPath = $frontwebPath;
            $isFrontweb = true;
        } elseif (file_exists($bmsPath)) {
            $fullPath = $bmsPath;
        }

        if (!$fullPath) {
            return new Response("Source template not found.", 404);
        }

        $content = file_get_contents($fullPath);
        $content = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content);
        $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/\{\#.*?\#\}/s', '', $content);
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        preg_match_all('/>([^<>]*?){{.*?}}([^<>]*?)</', $content, $mixedMatches);
        preg_match_all('/>([^<]*?)</', $content, $pureMatches);

        $snippets = [];
        foreach ($mixedMatches[1] as $i => $before) {
            $after = $mixedMatches[2][$i];
            if (trim($before)) $snippets[] = trim($before);
            if (trim($after)) $snippets[] = trim($after);
        }
        foreach ($pureMatches[1] as $text) {
            $clean = trim($text);
            if ($clean && !in_array($clean, $snippets)) {
                $snippets[] = $clean;
            }
        }

        $formFields = "";
        foreach ($snippets as $i => $text) {
            if (str_contains($text, '{%') || str_contains($text, '{{')) continue;
            $safeKey = 'auto_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower(substr($text, 0, 30))) . "_" . $i;
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
            $formFields .= "<input type=\"hidden\" name=\"original__template_extends\" value=\"" . base64_encode($originalExtends) . "\">";
            $formFields .= "<input type=\"hidden\" name=\"field__template_extends\" value=\"" . base64_encode($translatedExtends) . "\">";
        }

        $prefix = $isFrontweb ? 'translation_frontweb_' : 'translation_';
        $filename = $prefix . str_replace(['/', '.html.twig'], ['_', ''], $sourcePath) . '.html.twig';
        $outputPath = $projectDir . '/templates/translator/' . $filename;
        $actionRoute = $isFrontweb ? 'frontweb_translation_submit' : 'translation_submit';

        $output = <<<TWIG
{% extends 'base.html.twig' %}

{% block title %}Translate – Auto Generated{% endblock %}

{% block body %}
<form method="post" action="{{ path('{$actionRoute}') }}">
    <input type="hidden" name="original_path" value="{$sourcePath}">
    <input type="hidden" name="target_language" value="{{ lang }}">

{$formFields}
    <button type="submit">Generate Translated File</button>
</form>
{% endblock %}
TWIG;

        (new Filesystem())->dumpFile($outputPath, $output);
        return new Response("✅ Generated: $filename", 200);
    }
}