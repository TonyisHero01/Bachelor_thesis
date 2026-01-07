<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LocalizedPathExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Registers Twig functions provided by this extension.
     *
     * @return array<int, TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('localized_path', [$this, 'generateLocalizedPath']),
        ];
    }

    /**
     * Generates a route URL while preserving the current request locale.
     *
     * If the caller does not pass "_locale", the value is taken from the current request
     * (route parameter "_locale" first, then Request::getLocale()).
     *
     * @param array<string, mixed> $parameters
     */
    public function generateLocalizedPath(string $routeName, array $parameters = []): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request instanceof Request && !array_key_exists('_locale', $parameters)) {
            $parameters['_locale'] = $request->get('_locale') ?? $request->getLocale();
        }

        return $this->urlGenerator->generate($routeName, $parameters);
    }
}