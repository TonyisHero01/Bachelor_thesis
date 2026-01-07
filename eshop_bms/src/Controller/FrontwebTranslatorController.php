<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TRANSLATOR')]
class FrontwebTranslatorController extends AbstractController
{
    /**
     * Displays a list of translatable frontweb pages for the selected language.
     */
    #[Route(
        '/translation/frontweb/{lang}',
        name: 'frontweb_translator_page_list',
        requirements: ['lang' => '^(?!submit$)[a-zA-Z]+'],
        methods: ['GET']
    )]
    public function showPageList(string $lang): Response
    {
        $lang = strtolower($lang);

        return $this->render('translation/page_list.html.twig', [
            'lang' => $lang,
            'bmsPages' => [],
            'frontwebPages' => [
                // 这里按你自己维护的 frontweb 页面列表写
                // key 用来显示标题，value 是 templates 下的相对路径（不带 templates/ 前缀）
                'frontweb_home' => 'eshop/index.html.twig',
                // 'frontweb_cart' => 'eshop/cart.html.twig',
            ],
        ]);
    }

    /**
     * Renders the translation form template for a given frontweb page and target language.
     */
    #[Route(
        '/translation/frontweb/form/{path}/{lang}',
        name: 'frontweb_translator_form',
        requirements: ['path' => '.+'],
        methods: ['GET']
    )]
    public function showTranslationForm(string $path, string $lang): Response
    {
        $lang = strtolower($lang);
        $path = $this->normalizeRelativeTwigPath($path);

        $normalized = str_replace('/', '_', $path);
        $template = 'frontweb_translator/translation_' . $normalized;

        return $this->render($template, [
            'lang' => $lang,
            'path' => $path,
        ]);
    }

    /**
     * Handles submission of the frontweb translation form and writes a localized twig file
     * into eshop_frontweb/templates/locale/{lang}/...
     */
    #[Route(
        '/translation/frontweb/submit',
        name: 'frontweb_translation_submit',
        methods: ['POST']
    )]
    public function handleTranslationSubmit(
        Request $request,
        KernelInterface $kernel,
        LoggerInterface $logger
    ): Response {
        $language = strtolower((string) $request->request->get('target_language', ''));
        $originalPath = (string) $request->request->get('original_path', '');
        $token = (string) $request->request->get('_token', '');

        if ($language === '' || $originalPath === '') {
            return new Response('❌ Missing required fields', Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isCsrfTokenValid('frontweb_translation_submit', $token)) {
            return new Response('❌ Invalid CSRF token', Response::HTTP_BAD_REQUEST);
        }

        $originalPath = $this->normalizeRelativeTwigPath($originalPath);

        $bmsDir = $kernel->getProjectDir();
        $frontwebDir = realpath($bmsDir . '/../eshop_frontweb');

        if ($frontwebDir === false) {
            return new Response('❌ Cannot locate frontweb directory', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $bmsPath = $bmsDir . '/templates/' . $originalPath;
        $frontwebPath = $frontwebDir . '/templates/' . $originalPath;

        $sourceFile = is_file($bmsPath) ? $bmsPath : (is_file($frontwebPath) ? $frontwebPath : null);

        if ($sourceFile === null) {
            $logger->error(sprintf(
                '[Frontweb Translator] Original file not found. originalPath=%s',
                $originalPath
            ));

            return new Response('❌ Original file not found', Response::HTTP_NOT_FOUND);
        }

        $translatedFile = $frontwebDir . '/templates/locale/' . $language . '/' . $originalPath;
        $translatedDir = dirname($translatedFile);

        if (!is_dir($translatedDir) && !mkdir($translatedDir, 0777, true) && !is_dir($translatedDir)) {
            $logger->error(sprintf(
                '[Frontweb Translator] Failed to create directory: %s',
                $translatedDir
            ));

            return new Response('❌ Failed to create output directory', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $originalContent = (string) file_get_contents($sourceFile);

        $encodedOriginalExtends = (string) $request->request->get('original__template_extends', '');
        $encodedTranslatedExtends = (string) $request->request->get('field__template_extends', '');

        if ($encodedOriginalExtends !== '' && $encodedTranslatedExtends !== '') {
            $decodedOriginal = base64_decode($encodedOriginalExtends, true);
            $decodedTranslated = base64_decode($encodedTranslatedExtends, true);

            if (is_string($decodedOriginal) && is_string($decodedTranslated)) {
                $originalContent = str_replace($decodedOriginal, $decodedTranslated, $originalContent);
            }
        }

        $tokens = preg_split(
            '/({{.*?}}|{%\s.*?%})/s',
            $originalContent,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if (!is_array($tokens)) {
            return new Response('❌ Failed to parse template tokens', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        foreach ($request->request->all() as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'original__')) {
                continue;
            }

            if ($key === 'original__template_extends') {
                continue;
            }

            $suffix = substr($key, strlen('original__'));
            $originalText = (string) $value;
            $translatedText = (string) $request->request->get('field__' . $suffix, '');

            if ($translatedText === '' || $translatedText === $originalText) {
                continue;
            }

            $escapedOriginal = preg_quote($originalText, '/');
            $escapedOriginal = preg_replace('/\\\\\{\\\\\{.*?\\\\\}\\\\\}/', '.*?', (string) $escapedOriginal);
            $pattern = '/' . $escapedOriginal . '/s';

            foreach ($tokens as $i => $tokenPart) {
                $trimmed = trim((string) $tokenPart);

                if (str_starts_with($trimmed, '{%') || str_starts_with($trimmed, '{{')) {
                    continue;
                }

                $tokens[$i] = preg_replace($pattern, $translatedText, (string) $tokenPart, 1);
            }
        }

        $translatedContent = implode('', $tokens);
        file_put_contents($translatedFile, $translatedContent);

        return new Response(sprintf(
            '✅ Translation saved to: <code>eshop_frontweb/templates/locale/%s/%s</code>',
            htmlspecialchars($language, ENT_QUOTES),
            htmlspecialchars($originalPath, ENT_QUOTES)
        ));
    }

    private function normalizeRelativeTwigPath(string $path): string
    {
        $path = trim($path);

        $path = str_replace('\\', '/', $path);

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