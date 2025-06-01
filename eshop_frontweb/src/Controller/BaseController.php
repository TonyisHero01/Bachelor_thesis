<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class BaseController extends AbstractController
{
    protected ?Environment $twig = null;
    protected ?LoggerInterface $logger = null;

    public function __construct(?Environment $twig = null, ?LoggerInterface $logger = null)
    {
        $this->twig = $twig;
        $this->logger = $logger;
    }
    /**
     * Renders a localized Twig template if it exists, falling back to the default template if not found.
     * Automatically injects `locale`, available `languages`, and `translations` into the template parameters.
     *
     * @param string $template Template path (relative to templates/)
     * @param array $parameters Template parameters
     * @param Request|null $request Optional HTTP request
     * @return Response Rendered response
     */
    protected function renderLocalized(string $template, array $parameters = [], ?Request $request = null)
    {
        $request ??= Request::createFromGlobals();
        $locale = $request->get('_locale') ?? $request->query->get('_locale') ?? $request->getLocale();
        $localizedPath = "locale/{$locale}/{$template}";
        $this->logger?->info("🌐 Current locale: " . $locale);

        $parameters['locale'] = $locale;
        $parameters['languages'] = $parameters['languages'] ?? $this->getAvailableLanguages();
        $parameters['translations'] = $parameters['translations'] ?? $this->getTranslations($request);

        if ($this->twig->getLoader()->exists($localizedPath)) {
            $this->logger?->info("✅ Using localized template: {$localizedPath}");
            return $this->render($localizedPath, $parameters);
        }

        $this->logger?->warning("⚠️ Localized template not found, fallback to: {$template}");
        return $this->render($template, $parameters);
    }

    /**
     * Renders a localized Twig view as a string.
     * Falls back to the default view if the localized version is missing.
     *
     * @param string $template Template path
     * @param array $parameters View parameters
     * @param Request|null $request Optional HTTP request
     * @return string Rendered view as a string
     */
    protected function renderViewLocalized(string $template, array $parameters = [], ?Request $request = null): string
    {
        $request ??= Request::createFromGlobals();
        $locale = $request->get('_locale') ?? $request->query->get('_locale') ?? $request->getLocale();
        $localizedPath = "locale/{$locale}/{$template}";

        $parameters['translations'] = $parameters['translations'] ?? $this->getTranslations($request);

        if ($this->twig && $this->twig->getLoader()->exists($localizedPath)) {
            return $this->renderView($localizedPath, $parameters);
        }

        $this->logger?->warning("⚠️ Localized view not found, fallback to: {$template}");
        return $this->renderView($template, $parameters);
    }

    /**
     * Redirects to a localized route.
     * Ensures `_locale` is present in the route parameters.
     *
     * @param string $route Route name
     * @param array $parameters Route parameters
     * @param int $status HTTP redirect status (default 302)
     * @param Request|null $request Optional HTTP request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectToRouteLocalized(string $route, array $parameters = [], int $status = 302, ?Request $request = null): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $request ??= Request::createFromGlobals();
        if (!isset($parameters['_locale'])) {
            $parameters['_locale'] = $request->get('_locale') ?? $request->query->get('_locale') ?? $request->getLocale();
        }

        return $this->redirectToRoute($route, $parameters, $status);
    }

    /**
     * Extracts translated strings from a localized template file.
     * Returns a map of translation keys and their translated values, combining original and field entries.
     *
     * @param Request $request HTTP request for determining the locale
     * @return array Associative array of translations
     */
    protected function getTranslations(Request $request): array
    {
        $locale = $request->get('_locale') ?? $request->query->get('_locale') ?? $request->getLocale();
        $translationFile = $this->getParameter('kernel.project_dir') . "/templates/locale/{$locale}/bms_home/home.html.twig";

        if (file_exists($translationFile)) {
            $contents = file_get_contents($translationFile);
            preg_match_all('/name="original__([a-zA-Z0-9_]+)".*?value="(.*?)"/', $contents, $matches);
            preg_match_all('/name="field__([a-zA-Z0-9_]+)".*?value="(.*?)"/', $contents, $fieldMatches);

            $originalMap = array_combine($matches[1], $matches[2] ?? []);
            $fieldMap = array_combine($fieldMatches[1], $fieldMatches[2] ?? []);
            return array_merge($originalMap, $fieldMap);
        }

        return [];
    }

    /**
     * Scans the `templates/locale/` directory to find all available language subfolders.
     *
     * @return array List of available locale codes (e.g., ['en', 'cz'])
     */
    protected function getAvailableLanguages(): array
    {
        $localeDir = $this->getParameter('kernel.project_dir') . '/templates/locale';
        $languages = [];

        if (is_dir($localeDir)) {
            foreach (scandir($localeDir) as $lang) {
                if ($lang !== '.' && $lang !== '..' && is_dir($localeDir . '/' . $lang)) {
                    $languages[] = $lang;
                }
            }
        }

        return $languages;
    }

}