<?php

namespace App\Controller;

use App\Entity\Currency;
use App\Entity\ShopInfo;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class BaseController extends AbstractController
{
    protected ?Environment $twig;
    protected ?LoggerInterface $logger;
    protected ?ManagerRegistry $doctrine;

    public function __construct(
        ?Environment $twig = null,
        ?LoggerInterface $logger = null,
        ?ManagerRegistry $doctrine = null
    ) {
        $this->twig = $twig;
        $this->logger = $logger;
        $this->doctrine = $doctrine;
    }

    /**
     * Renders a localized template when available, otherwise falls back to the default template.
     */
    protected function renderLocalized(string $template, array $parameters = [], ?Request $request = null): Response
    {
        $request ??= Request::createFromGlobals();
        $locale = $this->resolveLocale($request);
        $localizedPath = 'locale/' . $locale . '/' . $template;

        $parameters = $this->withGlobalParameters($parameters, $request);

        if ($this->twig !== null && $this->twig->getLoader()->exists($localizedPath)) {
            $this->logger?->info('Using localized template: ' . $localizedPath);

            return parent::render($localizedPath, $parameters);
        }

        $this->logger?->warning('Localized template not found, fallback to: ' . $template);

        return parent::render($template, $parameters);
    }

    /**
     * Renders a localized template into a string when available, otherwise falls back to the default template.
     */
    protected function renderViewLocalized(string $template, array $parameters = [], ?Request $request = null): string
    {
        $request ??= Request::createFromGlobals();
        $locale = $this->resolveLocale($request);
        $localizedPath = 'locale/' . $locale . '/' . $template;

        $parameters = $this->withGlobalParameters($parameters, $request);

        if ($this->twig !== null && $this->twig->getLoader()->exists($localizedPath)) {
            return $this->renderView($localizedPath, $parameters);
        }

        $this->logger?->warning('Localized view not found, fallback to: ' . $template);

        return $this->renderView($template, $parameters);
    }

    /**
     * Redirects to a route while preserving the current locale.
     */
    protected function redirectToRouteLocalized(
        string $route,
        array $parameters = [],
        int $status = Response::HTTP_FOUND,
        ?Request $request = null
    ): RedirectResponse {
        $request ??= Request::createFromGlobals();
        $parameters['_locale'] ??= $this->resolveLocale($request);

        return $this->redirectToRoute($route, $parameters, $status);
    }

    /**
     * Loads translations for the current locale from the generated translation template.
     */
    protected function getTranslations(Request $request): array
    {
        try {
            $locale = $this->resolveLocale($request);
            $file = $this->getParameter('kernel.project_dir') . '/templates/locale/' . $locale . '/bms_home/home.html.twig';

            if (!\is_file($file)) {
                return [];
            }

            $contents = (string) @\file_get_contents($file);
            if ($contents === '') {
                return [];
            }

            \preg_match_all('/name="original__([a-zA-Z0-9_]+)".*?value="(.*?)"/', $contents, $m1);
            \preg_match_all('/name="field__([a-zA-Z0-9_]+)".*?value="(.*?)"/', $contents, $m2);

            $originalMap = (isset($m1[1], $m1[2]) && \count($m1[1]) === \count($m1[2]))
                ? \array_combine($m1[1], $m1[2])
                : [];

            $fieldMap = (isset($m2[1], $m2[2]) && \count($m2[1]) === \count($m2[2]))
                ? \array_combine($m2[1], $m2[2])
                : [];

            return \array_merge($originalMap ?: [], $fieldMap ?: []);
        } catch (\Throwable $e) {
            $this->logger?->error('getTranslations error: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Scans templates/locale for available languages and ensures "en" comes first.
     */
    protected function scanAvailableLanguages(): array
    {
        try {
            $root = $this->getParameter('kernel.project_dir') . '/templates/locale';
            if (!\is_dir($root)) {
                return ['en'];
            }

            $finder = new Finder();
            $finder->in($root)->depth('== 0')->directories();

            $langs = ['en'];

            foreach ($finder as $dir) {
                $langs[] = \strtolower($dir->getRelativePathname());
            }

            $langs = \array_values(\array_unique($langs));
            \usort(
                $langs,
                static fn (string $a, string $b): int => $a === 'en' ? -1 : ($b === 'en' ? 1 : \strcmp($a, $b))
            );

            return $langs;
        } catch (\Throwable $e) {
            $this->logger?->error('scanAvailableLanguages error: ' . $e->getMessage());

            return ['en'];
        }
    }

    /**
     * Returns global template parameters without merging them into a provided array.
     */
    protected function getGlobalTemplateParameters(Request $request): array
    {
        return $this->buildGlobalParameters($request);
    }

    /**
     * Resolves the active locale from the request.
     */
    private function resolveLocale(Request $request): string
    {
        return (string) (
            $request->get('_locale')
            ?? $request->query->get('_locale')
            ?? $request->getLocale()
            ?? 'en'
        );
    }

    /**
     * Merges global parameters into the provided template parameters array.
     */
    private function withGlobalParameters(array $parameters, Request $request): array
    {
        $parameters['translations'] ??= $this->getTranslations($request);
        $parameters['availableLanguages'] ??= $this->scanAvailableLanguages();

        if (!\array_key_exists('shopInfo', $parameters)) {
            $parameters['shopInfo'] = $this->loadShopInfo();
        }

        if (!\array_key_exists('currencies', $parameters)) {
            $parameters['currencies'] = $this->loadCurrencies();
        }

        return $parameters;
    }

    /**
     * Builds a fresh array of global parameters for templates.
     */
    private function buildGlobalParameters(Request $request): array
    {
        return [
            'translations' => $this->getTranslations($request),
            'availableLanguages' => $this->scanAvailableLanguages(),
            'shopInfo' => $this->loadShopInfo(),
            'currencies' => $this->loadCurrencies(),
        ];
    }

    /**
     * Loads the latest ShopInfo record when Doctrine is available.
     */
    private function loadShopInfo(): ?ShopInfo
    {
        if ($this->doctrine === null) {
            return null;
        }

        try {
            $em = $this->doctrine->getManager();

            return $em->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
        } catch (\Throwable $e) {
            $this->logger?->error('load shopInfo error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Loads all Currency records when Doctrine is available.
     *
     * @return Currency[]
     */
    private function loadCurrencies(): array
    {
        if ($this->doctrine === null) {
            return [];
        }

        try {
            $em = $this->doctrine->getManager();

            return $em->getRepository(Currency::class)->findAll();
        } catch (\Throwable $e) {
            $this->logger?->error('load currencies error: ' . $e->getMessage());

            return [];
        }
    }
}