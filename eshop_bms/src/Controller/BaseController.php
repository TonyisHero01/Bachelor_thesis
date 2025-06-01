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
     * Renders a localized template if available, otherwise falls back to the default template.
     * Automatically injects the 'translations' array into the parameters.
     *
     * @param string $template
     * @param array $parameters
     * @param Request|null $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderLocalized(string $template, array $parameters = [], ?Request $request = null)
    {
        $request ??= Request::createFromGlobals();
        $locale = $request->get('_locale') ?? $request->query->get('_locale') ?? $request->getLocale();
        $localizedPath = "locale/{$locale}/{$template}";

        $parameters['translations'] = $parameters['translations'] ?? $this->getTranslations($request);

        if ($this->twig->getLoader()->exists($localizedPath)) {
            $this->logger?->info("✅ Using localized template: {$localizedPath}");
            return $this->render($localizedPath, $parameters);
        }

        $this->logger?->warning("⚠️ Localized template not found, fallback to: {$template}");
        return $this->render($template, $parameters);
    }

    /**
     * Renders a localized template view as a string (used for PDF, emails, etc.).
     * Falls back to the default template if localized version doesn't exist.
     *
     * @param string $template
     * @param array $parameters
     * @param Request|null $request
     * @return string
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
     * Redirects to a localized route by automatically injecting the '_locale' parameter.
     *
     * @param string $route
     * @param array $parameters
     * @param int $status
     * @param Request|null $request
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
     * Extracts translations from a localized HTML template file.
     * Looks for name="original__xxx" and name="field__xxx" fields and builds a translation map.
     *
     * @param Request $request
     * @return array<string, string>
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
}