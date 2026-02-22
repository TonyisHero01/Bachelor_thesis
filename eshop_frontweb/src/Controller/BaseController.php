<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Currency;
use App\Entity\ShopInfo;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
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
     * Renders a localized Twig template if it exists; otherwise falls back to the default template.
     * Injects global parameters: locale, languages, availableLanguages, translations, shopInfo, currencies, activeCurrency.
     */
    protected function renderLocalized(string $template, array $parameters = [], ?Request $request = null)
    {
        $t0 = microtime(true);
        $request ??= Request::createFromGlobals();

        $locale = $this->resolveLocale($request);
        $localizedPath = sprintf('locale/%s/%s', $locale, $template);

        $this->logger?->info('[BASE] renderLocalized() enter', [
            'template' => $template,
            'localizedPath' => $localizedPath,
            'query__locale' => $request->query->get('_locale'),
            'attr__locale' => $request->attributes->get('_locale'),
            'reqLocale()' => $request->getLocale(),
        ]);

        $parameters = $this->withGlobalParameters($parameters, $request, $locale);

        $this->logger?->info('[BASE] renderLocalized() globals', [
            'locale' => $parameters['locale'] ?? null,
            'languages.count' => isset($parameters['languages']) ? count($parameters['languages']) : null,
            'currencies.count' => isset($parameters['currencies']) ? count($parameters['currencies']) : null,
            'activeCurrency' => $parameters['activeCurrency'] ?? null,
            'shopInfo?' => !empty($parameters['shopInfo']),
        ]);

        if ($this->twig && $this->twig->getLoader()->exists($localizedPath)) {
            $this->logger?->info('[BASE] Using localized template', ['path' => $localizedPath]);
            $resp = $this->render($localizedPath, $parameters);
        } else {
            $this->logger?->warning('[BASE] Localized template not found, fallback', [
                'requested' => $localizedPath,
                'fallback' => $template,
            ]);
            $resp = $this->render($template, $parameters);
        }

        $this->logger?->info('[BASE] renderLocalized() leave', [
            'elapsed_ms' => round((microtime(true) - $t0) * 1000, 2),
        ]);

        return $resp;
    }

    /**
     * Renders a localized Twig template to a string (for PDF/email use); otherwise falls back to the default template.
     */
    protected function renderViewLocalized(string $template, array $parameters = [], ?Request $request = null): string
    {
        $t0 = microtime(true);
        $request ??= Request::createFromGlobals();

        $locale = $this->resolveLocale($request);
        $localizedPath = sprintf('locale/%s/%s', $locale, $template);

        $this->logger?->info('[BASE] renderViewLocalized() enter', [
            'template' => $template,
            'localizedPath' => $localizedPath,
            'locale' => $locale,
        ]);

        $parameters = $this->withGlobalParameters($parameters, $request, $locale);

        if ($this->twig && $this->twig->getLoader()->exists($localizedPath)) {
            $out = $this->renderView($localizedPath, $parameters);
        } else {
            $this->logger?->warning('[BASE] Localized view not found, fallback', [
                'requested' => $localizedPath,
                'fallback' => $template,
            ]);
            $out = $this->renderView($template, $parameters);
        }

        $this->logger?->info('[BASE] renderViewLocalized() leave', [
            'elapsed_ms' => round((microtime(true) - $t0) * 1000, 2),
        ]);

        return $out;
    }

    /**
     * Redirects to a route while ensuring the _locale parameter is present.
     */
    protected function redirectToRouteLocalized(
        string $route,
        array $parameters = [],
        int $status = 302,
        ?Request $request = null
    ): RedirectResponse {
        $request ??= Request::createFromGlobals();
        $parameters['_locale'] ??= $this->resolveLocale($request);

        $this->logger?->info('[LOCALE] redirectToRouteLocalized()', [
            'route' => $route,
            'params' => $parameters,
            'status' => $status,
        ]);

        return $this->redirectToRoute($route, $parameters, $status);
    }

    /**
     * Resolves the locale using request attributes/query first, then falls back to Request::getLocale(), then 'en'.
     */
    private function resolveLocale(Request $request): string
    {
        $val = (string) (
            $request->get('_locale')
            ?? $request->query->get('_locale')
            ?? $request->getLocale()
            ?? 'en'
        );

        $this->logger?->debug('[LOCALE] resolveLocale()', ['resolved' => $val]);

        return $val;
    }

    /**
     * Adds global parameters required by shared layouts (e.g., header) to avoid missing variables.
     */
    private function withGlobalParameters(array $parameters, Request $request, string $locale): array
    {
        $this->logger?->debug('[BASE] withGlobalParameters() enter', [
            'incoming.keys' => array_keys($parameters),
            'incoming.locale' => $locale,
        ]);

        $parameters['locale'] ??= $locale;

        $langs = $parameters['languages']
            ?? $parameters['availableLanguages']
            ?? $this->scanAvailableLanguages();

        $parameters['languages'] = $langs;
        $parameters['availableLanguages'] ??= $langs;

        $this->logger?->debug('[LOCALE] available languages', [
            'count' => count($langs),
            'list' => $langs,
        ]);

        $parameters['translations'] ??= $this->getTranslations($request);

        if (!array_key_exists('shopInfo', $parameters)) {
            $parameters['shopInfo'] = $this->loadLatestShopInfo();
        }

        if (!array_key_exists('currencies', $parameters)) {
            $parameters['currencies'] = $this->loadCurrencies();
        } else {
            $this->logger?->debug('[CURRENCY] currencies provided by caller', [
                'count' => count($parameters['currencies']),
            ]);
        }

        $session = $request->getSession();
        $active = $session?->get('active_currency');

        $this->logger?->debug('[CURRENCY] session before resolve', [
            'active_currency' => $active,
            'session_id' => $session?->getId(),
        ]);

        if (!$active) {
            $active = $this->resolveDefaultCurrencyCode((array) $parameters['currencies']);
            $session?->set('active_currency', $active);
            $this->logger?->info('[CURRENCY] Set default active_currency', ['active' => $active]);
        }

        $parameters['activeCurrency'] = $active;

        $this->logger?->debug('[BASE] withGlobalParameters() leave', [
            'activeCurrency' => $parameters['activeCurrency'],
            'currencies.count' => count((array) $parameters['currencies']),
            'languages.count' => count((array) $parameters['languages']),
        ]);

        return $parameters;
    }

    /**
     * Loads the latest ShopInfo record (by id desc). Returns null if doctrine is unavailable or no record exists.
     */
    private function loadLatestShopInfo(): ?ShopInfo
    {
        if (!$this->doctrine) {
            $this->logger?->warning('[BASE] doctrine is null, shopInfo skipped');
            return null;
        }

        try {
            $em = $this->doctrine->getManager();
            /** @var ShopInfo|null $shop */
            $shop = $em->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);

            $this->logger?->debug('[BASE] shopInfo load', [
                'found' => $shop !== null,
                'id' => $shop?->getId(),
                'name' => $shop?->getEshopName(),
            ]);

            return $shop;
        } catch (\Throwable $e) {
            $this->logger?->error('[BASE] shopInfo load error', ['ex' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Loads all currencies from the database. Returns an empty array if doctrine is unavailable or load fails.
     *
     * @return list<Currency>
     */
    private function loadCurrencies(): array
    {
        if (!$this->doctrine) {
            $this->logger?->warning('[CURRENCY] doctrine is null, currencies empty');
            return [];
        }

        try {
            $em = $this->doctrine->getManager();
            /** @var list<Currency> $list */
            $list = $em->getRepository(Currency::class)->findAll() ?: [];

            $this->logger?->info('[CURRENCY] Loaded from DB', [
                'count' => count($list),
                'rows' => array_map(
                    static fn ($c) => [
                        'id' => method_exists($c, 'getId') ? $c->getId() : null,
                        'name' => method_exists($c, 'getName') ? $c->getName() : null,
                        'value' => method_exists($c, 'getValue') ? $c->getValue() : null,
                        'default' => method_exists($c, 'isIsDefault') ? $c->isIsDefault() : null,
                    ],
                    $list
                ),
            ]);

            return $list;
        } catch (\Throwable $e) {
            $this->logger?->error('[CURRENCY] Load error', ['ex' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Resolves the default currency code using several fallbacks:
     * - repository default (findDefaultCurrency) if available
     * - prefer CZK if present
     * - entity default flag (isDefault/getIsDefault/isIsDefault)
     * - first currency entry
     * - fallback to EUR
     *
     * @param array<int, mixed> $currencies
     */
    private function resolveDefaultCurrencyCode(array $currencies): string
    {
        $this->logger?->debug('[CURRENCY] resolveDefaultCurrencyCode() enter', [
            'count' => count($currencies),
        ]);

        $fromRepo = $this->resolveDefaultFromRepository();
        if ($fromRepo !== null && $fromRepo !== '') {
            return $fromRepo;
        }

        foreach ($currencies as $c) {
            if (is_object($c) && $this->currencyCodeFromEntity($c) === 'CZK') {
                $this->logger?->info('[CURRENCY] prefer CZK as default');
                return 'CZK';
            }
        }

        foreach ($currencies as $c) {
            if (is_object($c) && $this->isDefaultCurrency($c)) {
                $code = $this->currencyCodeFromEntity($c);
                $this->logger?->info('[CURRENCY] default by isDefault flag', ['code' => $code]);
                return $code;
            }
        }

        if (!empty($currencies) && is_object($currencies[0])) {
            $code = $this->currencyCodeFromEntity($currencies[0]);
            $this->logger?->info('[CURRENCY] default by first row', ['code' => $code]);
            return $code;
        }

        $this->logger?->warning('[CURRENCY] fallback to EUR');
        return 'EUR';
    }

    /**
     * Attempts to resolve the default currency code using a repository method if available.
     */
    private function resolveDefaultFromRepository(): ?string
    {
        if (!$this->doctrine) {
            return null;
        }

        try {
            $em = $this->doctrine->getManager();
            $repo = $em->getRepository(Currency::class);

            if (!method_exists($repo, 'findDefaultCurrency')) {
                $this->logger?->debug('[CURRENCY] repo has no method findDefaultCurrency()');
                return null;
            }

            $def = $repo->findDefaultCurrency();
            if (!$def || !is_object($def)) {
                $this->logger?->debug('[CURRENCY] repo has no default row');
                return null;
            }

            $code = $this->currencyCodeFromEntity($def);
            $this->logger?->info('[CURRENCY] default from repo', ['code' => $code]);

            return $code !== '' ? $code : null;
        } catch (\Throwable $e) {
            $this->logger?->error('[CURRENCY] repo default resolve error', ['ex' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extracts a currency code from an entity by trying common getters.
     */
    private function currencyCodeFromEntity(object $c): string
    {
        foreach (['getName', 'getCode', 'getSymbol'] as $m) {
            if (method_exists($c, $m)) {
                $val = strtoupper((string) $c->$m());
                if ($val !== '') {
                    return $val;
                }
            }
        }

        $this->logger?->debug('[CURRENCY] currencyCodeFromEntity(): empty code', [
            'class' => get_class($c),
        ]);

        return '';
    }

    /**
     * Checks whether the given currency entity is marked as default using common method conventions.
     */
    private function isDefaultCurrency(object $c): bool
    {
        foreach (['isDefault', 'getIsDefault', 'isIsDefault'] as $m) {
            if (!method_exists($c, $m)) {
                continue;
            }

            try {
                return (bool) $c->$m();
            } catch (\Throwable $e) {
                $this->logger?->error('[CURRENCY] isDefaultCurrency() call error', [
                    'method' => $m,
                    'ex' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }

    /**
     * Loads translations for the current locale from a Twig file and returns a flat key/value map.
     *
     * @return array<string, string>
     */
    protected function getTranslations(Request $request): array
    {
        $locale = $this->resolveLocale($request);
        $translationFile = $this->getParameter('kernel.project_dir')
            . sprintf('/templates/locale/%s/bms_home/home.html.twig', $locale);

        try {
            if (is_file($translationFile)) {
                $contents = file_get_contents($translationFile) ?: '';

                preg_match_all(
                    '/name="original__([a-zA-Z0-9_]+)".*?value="(.*?)"/',
                    $contents,
                    $m1
                );
                preg_match_all(
                    '/name="field__([a-zA-Z0-9_]+)".*?value="(.*?)"/',
                    $contents,
                    $m2
                );

                $originalMap = (isset($m1[1], $m1[2]) && count($m1[1]) === count($m1[2]))
                    ? array_combine($m1[1], $m1[2])
                    : [];

                $fieldMap = (isset($m2[1], $m2[2]) && count($m2[1]) === count($m2[2]))
                    ? array_combine($m2[1], $m2[2])
                    : [];

                $map = array_merge($originalMap ?: [], $fieldMap ?: []);

                $this->logger?->debug('[BASE] translations loaded', [
                    'locale' => $locale,
                    'file' => $translationFile,
                    'count' => count($map),
                ]);

                return array_map('strval', $map);
            }
        } catch (\Throwable $e) {
            $this->logger?->error('[BASE] translations read error', [
                'file' => $translationFile,
                'ex' => $e->getMessage(),
            ]);
        }

        $this->logger?->debug('[BASE] translations file not found', [
            'locale' => $locale,
            'file' => $translationFile,
        ]);

        return [];
    }

    /**
     * Scans templates/locale/ and returns available locale directory names (lowercased), with 'en' first.
     *
     * @return list<string>
     */
    protected function scanAvailableLanguages(): array
    {
        $root = $this->getParameter('kernel.project_dir') . '/templates/locale';

        if (!is_dir($root)) {
            $this->logger?->debug('[LOCALE] no locale dir, fallback en', ['root' => $root]);
            return ['en'];
        }

        $items = scandir($root) ?: [];
        $langs = [];

        foreach ($items as $x) {
            if ($x === '.' || $x === '..') {
                continue;
            }

            if (is_dir($root . '/' . $x)) {
                $langs[] = strtolower($x);
            }
        }

        $langs = array_values(array_unique($langs));
        usort(
            $langs,
            static fn (string $a, string $b): int => $a === 'en'
                ? -1
                : ($b === 'en' ? 1 : strcmp($a, $b))
        );

        $this->logger?->debug('[LOCALE] scanAvailableLanguages()', [
            'root' => $root,
            'langs' => $langs,
        ]);

        return $langs ?: ['en'];
    }

    /**
     * Backward-compatible alias for scanAvailableLanguages().
     *
     * @return list<string>
     */
    public function getAvailableLanguages(): array
    {
        return $this->scanAvailableLanguages();
    }
}