<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ShopInfo;
use App\Entity\ShopInfoTranslation;
use App\Entity\Category;
use App\Entity\CategoryTranslation;
use App\Entity\Color;
use App\Entity\ColorTranslation;
use App\Entity\Product;
use App\Entity\ProductTranslation;

class TranslatorController extends AbstractController
{
    #[Route('/translation/product/{lang}/{id}', name: 'translation_product_detail_form', methods: ['GET'])]
    public function showProductDetailForm(EntityManagerInterface $em, string $lang, int $id): Response
    {
        $product = $em->getRepository(Product::class)->find($id);
        if (!$product) {
            throw $this->createNotFoundException("Product not found");
        }

        $translation = $em->getRepository(ProductTranslation::class)->findOneBy([
            'product' => $product,
            'locale' => $lang,
        ]);
        
        $map = [];
        if ($translation) {
            $map = [
                'name' => $translation->getName(),
                'description' => $translation->getDescription(),
                'material' => $translation->getMaterial(),
            ];
        }

        return $this->render('translation/translation_product_detail_form.html.twig', [
            'lang' => $lang,
            'product' => $product,
            'translation' => $map,
        ]);
    }

    #[Route('/translation/product/{id}/{lang}/submit', name: 'translation_product_detail_submit', methods: ['POST'])]
    public function submitProductDetailTranslation(
        int $id,
        string $lang,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $product = $em->getRepository(Product::class)->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found.');
        }

        $translation = $em->getRepository(ProductTranslation::class)->findOneBy([
            'product' => $product,
            'locale' => $lang,
        ]) ?? (new ProductTranslation())
            ->setProduct($product)
            ->setLocale($lang);

        $translation
            ->setName($request->request->get('translated_name'))
            ->setDescription($request->request->get('translated_description'))
            ->setMaterial($request->request->get('translated_material'));

        $em->persist($translation);
        $em->flush();

        $this->addFlash('success', 'Translation saved.');
        return $this->redirectToRoute('translation_product_form_list', ['lang' => $lang]);
    }
    #[Route('/translation/product/{lang}', name: 'translation_product_form_list', methods: ['GET'])]
    public function showProductTranslationForm(string $lang, EntityManagerInterface $em): Response
    {
        $products = $em->getRepository(Product::class)->findLatestVersionProducts();

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

    #[Route('/translation/product/submit', name: 'translation_product_submit', methods: ['POST'])]
    public function submitProductTranslations(Request $request, EntityManagerInterface $em): Response
    {
        $lang = $request->request->get('target_language', 'en');
        $data = $request->request->all();

        foreach ($data as $key => $value) {
            if (!str_starts_with($key, 'field__')) continue;

            // 解析字段名和 product ID
            // e.g., field__name__123 => ['name', '123']
            if (!preg_match('/^field__(\w+)__(\d+)$/', $key, $matches)) continue;

            $field = $matches[1];
            $productId = (int) $matches[2];
            $originalKey = "original__{$field}__{$productId}";
            $originalText = $data[$originalKey] ?? '';

            if (!trim($value)) continue;

            // 查找产品
            $product = $em->getRepository(Product::class)->find($productId);
            if (!$product) continue;

            // 查找或创建翻译记录
            $translationRepo = $em->getRepository(ProductTranslation::class);
            $translation = $translationRepo->findOneBy([
                'product' => $product,
                'language' => $lang,
                'field' => $field,
            ]);

            if (!$translation) {
                $translation = new ProductTranslation();
                $translation->setProduct($product);
                $translation->setLanguage($lang);
                $translation->setField($field);
            }

            $translation->setOriginal($originalText);
            $translation->setTranslation($value);
            $em->persist($translation);
        }

        $em->flush();

        $this->addFlash('success', 'Translations saved successfully.');
        return $this->redirectToRoute('translation_product_form', ['lang' => $lang]);
    }

    #[Route('/translation/color/{lang}', name: 'color_translation_form')]
    public function showColorTranslationForm(EntityManagerInterface $em, string $lang): Response
    {
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
        ]);
    }

    #[Route('/translation/color/{lang}/submit', name: 'color_translation_submit', methods: ['POST'])]
    public function submitColorTranslations(Request $request, EntityManagerInterface $em, string $lang): Response
    {
        $ids = $request->request->all('color_ids');
        $names = $request->request->all('translated_names');

        foreach ($ids as $i => $id) {
            $color = $em->getRepository(Color::class)->find($id);
            if (!$color) continue;

            $translation = $em->getRepository(ColorTranslation::class)->findOneBy([
                'color' => $color,
                'locale' => $lang,
            ]) ?? new ColorTranslation();

            $translation->setColor($color);
            $translation->setLocale($lang);
            $translation->setName($names[$i]);

            $em->persist($translation);
        }

        $em->flush();

        return $this->redirectToRoute('translator_center', ['lang' => $lang]);
    }
    #[Route('/translator/shop-info/submit', name: 'translation_shop_info_submit', methods: ['POST'])]
    public function submitShopInfoTranslation(Request $request, EntityManagerInterface $em): Response
    {
        $targetLang = $request->request->get('target_language');
        if (!$targetLang) {
            return new Response("Missing target_language", 400);
        }

        $shopInfo = $em->getRepository(ShopInfo::class)->find(1);
        if (!$shopInfo) {
            return new Response("No ShopInfo found", 404);
        }

        $translation = $em->getRepository(ShopInfoTranslation::class)
            ->findOneBy(['shopInfo' => $shopInfo, 'locale' => $targetLang]) ?? new ShopInfoTranslation();

        $translation->setShopInfo($shopInfo);
        $translation->setLocale($targetLang);

        foreach ($request->request->all() as $key => $value) {
            if (str_starts_with($key, 'field__')) {
                $field = substr($key, 7);
                if (property_exists(ShopInfoTranslation::class, $field)) {
                    $setter = 'set' . ucfirst($field);
                    if (method_exists($translation, $setter)) {
                        $translation->$setter($value);
                    }
                }
            }
        }

        $em->persist($translation);
        $em->flush();

        return $this->redirectToRoute('translator_page_list', [
            'lang' => $targetLang,
        ]);
    }
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

    #[Route('/translation/{lang}/center', name: 'translator_center', requirements: ['lang' => '^(?!submit$)[a-zA-Z]+'])]
    public function showTranslationCenter(string $lang): Response
    {
        return $this->render('translation/translation_center.html.twig', [
            'lang' => $lang,
        ]);
    }

    #[Route('/translation/category/{lang}', name: 'category_translation_form')]
    public function categoryTranslationForm(string $lang, EntityManagerInterface $em): Response
    {
        $categories = $em->getRepository(Category::class)->findAll();

        return $this->render('translation/category_translation_form.html.twig', [
            'categories' => $categories,
            'lang' => $lang,
        ]);
    }

    #[Route('/translation/category/{lang}/submit', name: 'category_translation_submit', methods: ['POST'])]
    public function submitCategoryTranslations(Request $request, EntityManagerInterface $em, string $lang): Response
    {
        $ids = $request->request->all('category_ids');
        $names = $request->request->all('translated_names');

        foreach ($ids as $i => $id) {
            $category = $em->getRepository(Category::class)->find($id);
            if (!$category) continue;

            $translatedName = $names[$i] ?? '';
            if (!$translatedName) continue;

            $translation = $em->getRepository(CategoryTranslation::class)->findOneBy([
                'category' => $category,
                'locale' => $lang
            ]) ?? new CategoryTranslation();

            $translation->setCategory($category);
            $translation->setLocale($lang);
            $translation->setName($translatedName);
            $em->persist($translation);
        }

        $em->flush();

        return $this->redirectToRoute('translator_center', ['lang' => $lang]);
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

        // 替换 {{ ... }} 表达式
        $originalContent = preg_replace_callback('/{{.*?}}/s', function ($matches) use (&$twigPlaceholders, $logger) {
            $key = '__TWIG_EXPR__' . count($twigPlaceholders) . '__';
            $twigPlaceholders[$key] = $matches[0];
            $logger->debug("🧩 替换 Twig 表达式: {$matches[0]} -> $key");
            return $key;
        }, $originalContent);

        // 替换 {% ... %}，保留 extends
        $originalContent = preg_replace_callback('/{%\s*(.*?)\s*%}/s', function ($matches) use (&$twigPlaceholders, $logger) {
            $content = trim($matches[1]);
            if (str_starts_with($content, 'extends')) {
                return $matches[0];
            }
            $key = '__TWIG_BLOCK__' . count($twigPlaceholders) . '__';
            $twigPlaceholders[$key] = $matches[0];
            $logger->debug("🧩 替换 Twig block: {$matches[0]} -> $key");
            return $key;
        }, $originalContent);

        // 替换所有翻译内容
        foreach ($request->request->all() as $key => $value) {
            if (str_starts_with($key, 'original__')) {
                $suffix = substr($key, strlen('original__'));
                $originalText = $value;
                $translatedText = $request->request->get('field__' . $suffix);
        
                if ($translatedText && $translatedText !== $originalText) {
                    $logger->info("🔄 尝试替换: '$originalText' → '$translatedText'");
        
                    // 尝试构建匹配包含占位符的版本
                    $escapedOriginal = preg_quote($originalText, '/');
                    $escapedOriginal = str_replace('\{\{.*?\}\}', '\{\{.*?\}\}', $escapedOriginal);  // 通配 Twig 表达式
                    $pattern = '/' . $escapedOriginal . '/s';
        
                    // logger 输出正则
                    $logger->debug("🔍 正则匹配模式: {$pattern}");
        
                    // 执行替换
                    $originalContentBefore = $originalContent;
                    $originalContent = preg_replace_callback($pattern, function ($matches) use ($translatedText, $logger) {
                        $logger->info("✅ 正则替换命中：{$matches[0]}");
                        return $translatedText;
                    }, $originalContent, 1);
        
                    // 如果没有发生替换
                    if ($originalContent === $originalContentBefore) {
                        $logger->warning("❌ 替换失败：{$originalText} 没有找到匹配项");
                    }
                }
            }
        }

        // 替换 extends 模板指令
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

        // 还原 Twig 表达式
        $originalContent = strtr($originalContent, $twigPlaceholders);
        $logger->info("💾 最终翻译文件内容:\n" . $originalContent);

        // 检查 shopInfo 方法
        if (preg_match_all('/shopInfo\.get([a-zA-Z0-9_]+)\(\)/', $originalContent, $matches)) {
            foreach ($matches[1] as $methodSuffix) {
                $fullMethod = 'get' . $methodSuffix;
                if (!method_exists(\App\Entity\ShopInfo::class, $fullMethod)) {
                    $logger->warning("⚠️ 翻译文件中调用了不存在的 shopInfo 方法: $fullMethod()");
                }
            }
        }

        file_put_contents($translatedFile, $originalContent);
        $logger->info("✅ 已保存翻译至: $translatedFile");

        return new Response("✅ Translation saved to: $translatedFile");
    }
}
