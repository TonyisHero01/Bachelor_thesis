<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\CategoryTranslation;
use App\Entity\Color;
use App\Entity\ColorTranslation;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\ShopInfo;
use App\Entity\ShopInfoTranslation;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[IsGranted('ROLE_TRANSLATOR')]
class TranslatorController extends AbstractController
{
    private const LANG_PATTERN = '/^[a-zA-Z]{2,10}$/';
    private const CSRF_LANG_MGMT = 'translator_language_mgmt';
    private const CSRF_TRANSLATION_SUBMIT = 'translation_submit';
    private const CSRF_SHOPINFO_SUBMIT = 'translation_shop_info_submit';
    private const CSRF_COLOR_SUBMIT = 'color_translation_submit';
    private const CSRF_CATEGORY_SUBMIT = 'category_translation_submit';
    private const CSRF_PRODUCT_DETAIL_SUBMIT = 'translation_product_detail_submit';

    /**
     * Lists available languages based on templates/locale/<lang> folders.
     */
    #[Route('/translation', name: 'translator_languages', methods: ['GET'])]
    public function showLanguages(KernelInterface $kernel, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $localeRoot = $kernel->getProjectDir() . '/templates/locale';
        $languages = [];

        if (is_dir($localeRoot)) {
            foreach (scandir($localeRoot) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                if (is_dir($localeRoot . '/' . $item)) {
                    $languages[] = strtolower($item);
                }
            }
        }

        sort($languages);

        return $this->render('translation/languages.html.twig', [
            'languages' => $languages,
            'csrf_lang_mgmt' => $csrfTokenManager->getToken(self::CSRF_LANG_MGMT)->getValue(),
        ]);
    }

    /**
     * Creates a new language folder under templates/locale/<lang>.
     */
    #[Route('/translation/add-language', name: 'translator_add_language', methods: ['POST'])]
    public function addLanguage(Request $request, KernelInterface $kernel, LoggerInterface $logger): Response
    {
        $lang = strtolower((string) $request->request->get('language', ''));
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid(self::CSRF_LANG_MGMT, $token)) {
            return new Response('❌ Invalid CSRF token', Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match(self::LANG_PATTERN, $lang)) {
            return new Response('❌ Invalid language code', Response::HTTP_BAD_REQUEST);
        }

        $dir = $kernel->getProjectDir() . '/templates/locale/' . $lang;

        try {
            (new Filesystem())->mkdir($dir, 0755);
        } catch (\Throwable $e) {
            $logger->error('[Translator] addLanguage mkdir failed: ' . $e->getMessage());
            return new Response('❌ Failed to create language directory', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->redirectToRoute('translator_languages');
    }

    /**
     * Deletes templates/locale/<lang> directory.
     */
    #[Route('/translation/delete-language/{lang}', name: 'translator_delete_language', methods: ['POST'])]
    public function deleteLanguage(string $lang, Request $request, KernelInterface $kernel, LoggerInterface $logger): Response
    {
        $lang = strtolower($lang);
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid(self::CSRF_LANG_MGMT, $token)) {
            return new Response('❌ Invalid CSRF token', Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match(self::LANG_PATTERN, $lang)) {
            return new Response('❌ Invalid language code', Response::HTTP_BAD_REQUEST);
        }

        if ($lang === 'en') {
            return new Response('❌ Cannot delete default language "en"', Response::HTTP_BAD_REQUEST);
        }

        $dir = $kernel->getProjectDir() . '/templates/locale/' . $lang;

        try {
            if (is_dir($dir)) {
                (new Filesystem())->remove($dir);
            }
        } catch (\Throwable $e) {
            $logger->error('[Translator] deleteLanguage failed: ' . $e->getMessage());
            return new Response('❌ Failed to delete language directory', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->redirectToRoute('translator_languages');
    }

    /**
     * Shows the translation center dashboard for a selected language.
     */
    #[Route('/translation/{lang}/center', name: 'translator_center', requirements: ['lang' => '^(?!submit$)[a-zA-Z]+'], methods: ['GET'])]
    public function showTranslationCenter(string $lang): Response
    {
        $lang = strtolower($lang);

        return $this->render('translation/translation_center.html.twig', [
            'lang' => $lang,
        ]);
    }

    /**
     * Shows the list of available translation forms (BMS + frontweb) for a selected language.
     */
    #[Route('/translation/{lang}', name: 'translator_page_list', requirements: ['lang' => '^(?!submit$)[a-zA-Z]+'], methods: ['GET'])]
    public function showPageListForLanguage(string $lang, KernelInterface $kernel): Response
    {
        $lang = strtolower($lang);

        $translatorDir = $kernel->getProjectDir() . '/templates/translator';
        $finder = new Finder();

        $bmsPages = [];
        $frontwebPages = [];

        if (is_dir($translatorDir)) {
            $finder->files()->in($translatorDir)->name('/^translation_.*\.html\.twig$/');

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
        }

        ksort($bmsPages);
        ksort($frontwebPages);

        return $this->render('translation/page_list.html.twig', [
            'lang' => $lang,
            'bmsPages' => $bmsPages,
            'frontwebPages' => $frontwebPages,
        ]);
    }

    /**
     * Lists latest-version products and shows existing translations for the given language.
     */
    #[Route('/translation/product/{lang}', name: 'translation_product_form_list', methods: ['GET'])]
    public function showProductTranslationForm(
        string $lang,
        ProductRepository $productRepository,
        EntityManagerInterface $em
    ): Response {
        $lang = strtolower($lang);

        $products = $productRepository->findLatestVersionProducts();
        $translations = $em->getRepository(ProductTranslation::class)->findBy(['locale' => $lang]);

        $translatedMap = [];
        foreach ($translations as $t) {
            $translatedMap[$t->getProduct()->getId()] = [
                'name' => $t->getName(),
                'description' => $t->getDescription(),
                'material' => $t->getMaterial(),
            ];
        }

        return $this->render('translation/translation_product_form_list.html.twig', [
            'lang' => $lang,
            'products' => $products,
            'translatedMap' => $translatedMap,
        ]);
    }

    /**
     * Displays the translation form for a specific product in a given language.
     */
    #[Route('/translation/product/{lang}/{id}', name: 'translation_product_detail_form', methods: ['GET'])]
    public function showProductDetailForm(
        EntityManagerInterface $em,
        string $lang,
        int $id
    ): Response {
        $lang = strtolower($lang);

        $product = $em->getRepository(Product::class)->find($id);
        if ($product === null) {
            throw $this->createNotFoundException('Product not found');
        }

        $translation = $em->getRepository(ProductTranslation::class)->findOneBy([
            'product' => $product,
            'locale' => $lang,
        ]);

        $map = [
            'name' => $translation?->getName(),
            'description' => $translation?->getDescription(),
            'material' => $translation?->getMaterial(),
        ];

        return $this->render('translation/translation_product_detail_form.html.twig', [
            'lang' => $lang,
            'product' => $product,
            'translation' => $map,
            'csrf_token' => $this->generateCsrfToken(self::CSRF_PRODUCT_DETAIL_SUBMIT),
        ]);
    }

    /**
     * Saves the translation (name/description/material) for a specific product and language.
     */
    #[Route('/translation/product/{id}/{lang}/submit', name: 'translation_product_detail_submit', methods: ['POST'])]
    public function submitProductDetailTranslation(
        int $id,
        string $lang,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $lang = strtolower($lang);
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid(self::CSRF_PRODUCT_DETAIL_SUBMIT, $token)) {
            return new Response('❌ Invalid CSRF token', Response::HTTP_BAD_REQUEST);
        }

        $product = $em->getRepository(Product::class)->find($id);
        if ($product === null) {
            throw $this->createNotFoundException('Product not found');
        }

        $translation = $em->getRepository(ProductTranslation::class)->findOneBy([
            'product' => $product,
            'locale' => $lang,
        ]) ?? (new ProductTranslation())
            ->setProduct($product)
            ->setLocale($lang);

        $translation
            ->setName((string) $request->request->get('translated_name', ''))
            ->setDescription((string) $request->request->get('translated_description', ''))
            ->setMaterial((string) $request->request->get('translated_material', ''));

        $em->persist($translation);
        $em->flush();

        $this->addFlash('success', 'Translation saved.');
        return $this->redirectToRoute('translation_product_form_list', ['lang' => $lang]);
    }

    /**
     * Displays the form for translating color names into the specified language.
     */
    #[Route('/translation/color/{lang}', name: 'color_translation_form', methods: ['GET'])]
    public function showColorTranslationForm(EntityManagerInterface $em, string $lang): Response
    {
        $lang = strtolower($lang);

        $colors = $em->getRepository(Color::class)->findAll();
        $translations = $em->getRepository(ColorTranslation::class)->findBy(['locale' => $lang]);

        $translatedNames = [];
        foreach ($translations as $t) {
            $translatedNames[$t->getColor()->getId()] = $t->getName();
        }

        return $this->render('translation/color_translation_form.html.twig', [
            'lang' => $lang,
            'colors' => $colors,
            'translatedNames' => $translatedNames,
            'csrf_token' => $this->generateCsrfToken(self::CSRF_COLOR_SUBMIT),
        ]);
    }

    /**
     * Saves translated color names for the specified language.
     */
    #[Route('/translation/color/{lang}/submit', name: 'color_translation_submit', methods: ['POST'])]
    public function submitColorTranslations(Request $request, EntityManagerInterface $em, string $lang): Response
    {
        $lang = strtolower($lang);
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid(self::CSRF_COLOR_SUBMIT, $token)) {
            return new Response('❌ Invalid CSRF token', Response::HTTP_BAD_REQUEST);
        }

        $ids = $request->request->all('color_ids');
        $names = $request->request->all('translated_names');

        foreach ($ids as $i => $id) {
            $color = $em->getRepository(Color::class)->find($id);
            if ($color === null) {
                continue;
            }

            $name = (string) ($names[$i] ?? '');
            if (trim($name) === '') {
                continue;
            }

            $translation = $em->getRepository(ColorTranslation::class)->findOneBy([
                'color' => $color,
                'locale' => $lang,
            ]) ?? new ColorTranslation();

            $translation
                ->setColor($color)
                ->setLocale($lang)
                ->setName($name);

            $em->persist($translation);
        }

        $em->flush();
        return $this->redirectToRoute('translator_center', ['lang' => $lang]);
    }

    /**
     * Shows the translation form for product categories.
     */
    #[Route('/translation/category/{lang}', name: 'category_translation_form', methods: ['GET'])]
    public function categoryTranslationForm(string $lang, EntityManagerInterface $em): Response
    {
        $lang = strtolower($lang);
        $categories = $em->getRepository(Category::class)->findAll();

        $translations = $em->getRepository(CategoryTranslation::class)->findBy(['locale' => $lang]);
        $translatedNames = [];
        foreach ($translations as $t) {
            $translatedNames[$t->getCategory()->getId()] = $t->getName();
        }

        return $this->render('translation/category_translation_form.html.twig', [
            'categories' => $categories,
            'lang' => $lang,
            'translatedNames' => $translatedNames,
            'csrf_token' => $this->generateCsrfToken(self::CSRF_CATEGORY_SUBMIT),
        ]);
    }

    /**
     * Saves translated category names for the specified language.
     */
    #[Route('/translation/category/{lang}/submit', name: 'category_translation_submit', methods: ['POST'])]
    public function submitCategoryTranslations(Request $request, EntityManagerInterface $em, string $lang): Response
    {
        $lang = strtolower($lang);
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid(self::CSRF_CATEGORY_SUBMIT, $token)) {
            return new Response('❌ Invalid CSRF token', Response::HTTP_BAD_REQUEST);
        }

        $ids = $request->request->all('category_ids');
        $names = $request->request->all('translated_names');

        foreach ($ids as $i => $id) {
            $category = $em->getRepository(Category::class)->find($id);
            if ($category === null) {
                continue;
            }

            $translatedName = (string) ($names[$i] ?? '');
            if (trim($translatedName) === '') {
                continue;
            }

            $translation = $em->getRepository(CategoryTranslation::class)->findOneBy([
                'category' => $category,
                'locale' => $lang,
            ]) ?? new CategoryTranslation();

            $translation
                ->setCategory($category)
                ->setLocale($lang)
                ->setName($translatedName);

            $em->persist($translation);
        }

        $em->flush();
        return $this->redirectToRoute('translator_center', ['lang' => $lang]);
    }

    /**
     * Saves ShopInfoTranslation for the selected language.
     */
    #[Route('/translator/shop-info/submit', name: 'translation_shop_info_submit', methods: ['POST'])]
    public function submitShopInfoTranslation(Request $request, EntityManagerInterface $em): Response
    {
        $targetLang = strtolower((string) $request->request->get('target_language', ''));
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid(self::CSRF_SHOPINFO_SUBMIT, $token)) {
            return new Response('❌ Invalid CSRF token', Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match(self::LANG_PATTERN, $targetLang)) {
            return new Response('❌ Invalid target_language', Response::HTTP_BAD_REQUEST);
        }

        $shopInfo = $em->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
        if ($shopInfo === null) {
            return new Response('❌ No ShopInfo found', Response::HTTP_NOT_FOUND);
        }

        $translation = $em->getRepository(ShopInfoTranslation::class)->findOneBy([
            'shopInfo' => $shopInfo,
            'locale' => $targetLang,
        ]) ?? new ShopInfoTranslation();

        $translation->setShopInfo($shopInfo);
        $translation->setLocale($targetLang);

        $allowed = [
            'aboutUs' => 'setAboutUs',
            'howToOrder' => 'setHowToOrder',
            'businessConditions' => 'setBusinessConditions',
            'privacyPolicy' => 'setPrivacyPolicy',
            'shippingInfo' => 'setShippingInfo',
            'payment' => 'setPayment',
            'refund' => 'setRefund',
            'eshopName' => 'setEshopName',
            'companyName' => 'setCompanyName',
            'cin' => 'setCin',
            'address' => 'setAddress',
            'telephone' => 'setTelephone',
            'email' => 'setEmail',
        ];

        foreach ($allowed as $field => $setter) {
            $value = (string) $request->request->get('field__' . $field, '');
            if (method_exists($translation, $setter)) {
                $translation->$setter($value);
            }
        }

        $em->persist($translation);
        $em->flush();

        $this->addFlash('success', 'Shop info translation saved.');
        return $this->redirectToRoute('translator_page_list', ['lang' => $targetLang]);
    }

    /**
     * Writes a localized Twig template file under templates/locale/<lang>/.
     */
    #[Route('/translation/submit', name: 'translation_submit', methods: ['POST'])]
    public function handleTranslationSubmit(Request $request, KernelInterface $kernel, LoggerInterface $logger): Response
    {
        $language = strtolower((string) $request->request->get('target_language', ''));
        $originalPath = (string) $request->request->get('original_path', '');
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid(self::CSRF_TRANSLATION_SUBMIT, $token)) {
            return new Response('❌ Invalid CSRF token', Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match(self::LANG_PATTERN, $language)) {
            return new Response('❌ Invalid target_language', Response::HTTP_BAD_REQUEST);
        }

        if ($originalPath === '') {
            return new Response('❌ Missing original_path', Response::HTTP_BAD_REQUEST);
        }

        $projectDir = $kernel->getProjectDir();

        $isFrontweb = $request->request->get('is_frontweb') === '1'
            || str_starts_with($originalPath, 'eshop/')
            || str_starts_with($originalPath, 'customer/');

        $originalPath = $this->normalizeRelativeTwigPath($originalPath);

        $sourceBase = $isFrontweb
            ? realpath($projectDir . '/../eshop_frontweb/templates')
            : realpath($projectDir . '/templates');

        if ($sourceBase === false) {
            return new Response('❌ Source templates directory not found', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $sourceFile = realpath($sourceBase . '/' . $originalPath);
        if ($sourceFile === false || !str_starts_with($sourceFile, $sourceBase . DIRECTORY_SEPARATOR)) {
            $logger->error('[Translator] Invalid source file path: ' . $originalPath);
            return new Response('❌ Invalid template path', Response::HTTP_BAD_REQUEST);
        }

        if (!is_file($sourceFile)) {
            return new Response('❌ Original file not found', Response::HTTP_NOT_FOUND);
        }

        $outputBase = $isFrontweb
            ? realpath($projectDir . '/../eshop_frontweb/templates') . '/locale/' . $language
            : $projectDir . '/templates/locale/' . $language;

        $translatedFile = $outputBase . '/' . $originalPath;
        $translatedDir = dirname($translatedFile);

        try {
            (new Filesystem())->mkdir($translatedDir, 0755);
        } catch (\Throwable $e) {
            $logger->error('[Translator] mkdir output failed: ' . $e->getMessage());
            return new Response('❌ Failed to create output directory', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $originalContent = (string) file_get_contents($sourceFile);

        $twigPlaceholders = [];
        $originalContent = preg_replace_callback('/{{.*?}}/s', function ($m) use (&$twigPlaceholders) {
            $key = '__TWIG_EXPR__' . count($twigPlaceholders) . '__';
            $twigPlaceholders[$key] = $m[0];
            return $key;
        }, $originalContent) ?? $originalContent;

        $originalContent = preg_replace_callback('/{%\s*(.*?)\s*%}/s', function ($m) use (&$twigPlaceholders) {
            $content = trim((string) ($m[1] ?? ''));
            if (str_starts_with($content, 'extends')) {
                return $m[0];
            }
            $key = '__TWIG_BLOCK__' . count($twigPlaceholders) . '__';
            $twigPlaceholders[$key] = $m[0];
            return $key;
        }, $originalContent) ?? $originalContent;

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

            $pattern = '/' . preg_quote($originalText, '/') . '/s';
            $originalContent = preg_replace($pattern, $translatedText, $originalContent, 1) ?? $originalContent;
        }

        $encodedOriginalExtends = (string) $request->request->get('original__template_extends', '');
        $encodedTranslatedExtends = (string) $request->request->get('field__template_extends', '');

        if ($encodedOriginalExtends !== '' && $encodedTranslatedExtends !== '') {
            $decodedOriginal = base64_decode($encodedOriginalExtends, true);
            $decodedTranslated = base64_decode($encodedTranslatedExtends, true);

            if (is_string($decodedOriginal) && is_string($decodedTranslated)) {
                $originalContent = str_replace($decodedOriginal, $decodedTranslated, $originalContent);
            }
        }

        $originalContent = strtr($originalContent, $twigPlaceholders);

        file_put_contents($translatedFile, $originalContent);

        return new Response(sprintf(
            '✅ Translation saved to: <code>%s</code>',
            htmlspecialchars($translatedFile, ENT_QUOTES)
        ));
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

    /**
     * Generates a CSRF token value for the given token id.
     */
    private function generateCsrfToken(string $id): string
    {
        return $this->container->get('security.csrf.token_manager')->getToken($id)->getValue();
    }

        /**
     * Renders an auto-generated BMS translation form stored under templates/translator/.
     */
    #[Route('/translator/form/{path}/{lang}', name: 'translator_form', requirements: ['path' => '.+'], methods: ['GET'])]
    public function showTranslationForm(string $path, string $lang): Response
    {
        $template = $this->resolveTranslatorTemplate($path);

        return $this->render($template, [
            'lang' => strtolower($lang),
        ]);
    }

    /**
     * Renders an auto-generated frontweb translation form stored under templates/translator/.
     */
    #[Route('/frontweb/translator/form/{path}/{lang}', name: 'frontweb_translator_form', requirements: ['path' => '.+'], methods: ['GET'])]
    public function showFrontwebTranslationForm(string $path, string $lang): Response
    {
        $template = $this->resolveTranslatorTemplate($path);

        return $this->render($template, [
            'lang' => strtolower($lang),
        ]);
    }

    /**
     * Resolves a safe template name under templates/translator/ and blocks traversal attempts.
     */
    private function resolveTranslatorTemplate(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..') || str_starts_with($path, '/')) {
            throw $this->createNotFoundException('Invalid template path');
        }

        if (!str_ends_with($path, '.html.twig')) {
            $path .= '.html.twig';
        }

        return 'translator/' . $path;
    }
}