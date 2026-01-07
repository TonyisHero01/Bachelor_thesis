<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LocalizedPathExtension extends AbstractExtension
{
    private UrlGeneratorInterface $urlGenerator;
    private RequestStack $requestStack;

    public function __construct(UrlGeneratorInterface $urlGenerator, RequestStack $requestStack)
    {
        $this->urlGenerator = $urlGenerator;
        $this->requestStack = $requestStack;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('localized_path', [$this, 'generateLocalizedPath']),
        ];
    }

    public function generateLocalizedPath(string $routeName, array $parameters = []): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request && !isset($parameters['_locale'])) {
            $parameters['_locale'] = $request->get('_locale') ?? $request->getLocale();
        }

        return $this->urlGenerator->generate($routeName, $parameters);
    }
}