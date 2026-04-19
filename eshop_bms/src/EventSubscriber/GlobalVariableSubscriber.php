<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\CurrencyRepository;
use App\Repository\ShopInfoRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final class GlobalVariableSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ShopInfoRepository $shopInfoRepository,
        private readonly CurrencyRepository $currencyRepository,
        private readonly string $projectDir,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    /**
     * Injects global Twig variables (shop info, currencies, active currency, available languages).
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Stateless API requests must not use session (and usually don't need Twig globals).
        if ($this->isApiRequest($request)) {
            return;
        }

        $session = $this->getSessionOrNull($request);

        $shopInfo = $this->shopInfoRepository->findOneBy([], ['id' => 'DESC']);
        $shopName = $shopInfo?->getEshopName() ?? 'Eshop';

        $currencies = $this->currencyRepository->findAll();
        $activeCurrency = $this->resolveActiveCurrencyName($session, $currencies);

        $availableLanguages = $this->findAvailableLanguages($this->projectDir . '/templates/locale');

        $this->twig->addGlobal('shopInfo', $shopInfo);
        $this->twig->addGlobal('shopName', $shopName);
        $this->twig->addGlobal('currencies', $currencies);
        $this->twig->addGlobal('defaultCurrency', $activeCurrency);
        $this->twig->addGlobal('availableLanguages', $availableLanguages);
    }

    private function isApiRequest(Request $request): bool
    {
        $path = $request->getPathInfo();

        // Your API prefix (adjust if needed)
        return str_starts_with($path, '/api/')
            || str_starts_with($path, '/api/v1');
    }

    /**
     * @return SessionInterface|null Returns session if available for the request, otherwise null.
     */
    private function getSessionOrNull(Request $request): ?SessionInterface
    {
        if (!$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }

    /**
     * @param array<int, object> $currencies
     */
    private function resolveActiveCurrencyName(?SessionInterface $session, array $currencies): ?string
    {
        $active = $session?->get('active_currency');

        if ($active || $currencies === []) {
            return is_string($active) ? $active : null;
        }

        $default = null;
        foreach ($currencies as $currency) {
            if (method_exists($currency, 'isIsDefault') && $currency->isIsDefault()) {
                $default = $currency->getName();
                break;
            }
        }

        $active = $default ?? $currencies[0]->getName();
        $session?->set('active_currency', $active);

        return $active;
    }

    /**
     * @return array<int, string>
     */
    private function findAvailableLanguages(string $localeRoot): array
    {
        $langs = ['en'];

        if (!is_dir($localeRoot)) {
            return $langs;
        }

        $entries = scandir($localeRoot) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $localeRoot . '/' . $entry;
            if (!is_dir($path)) {
                continue;
            }

            $lang = strtolower($entry);

            if (!preg_match('/^[a-z0-9_-]{2,15}$/', $lang)) {
                continue;
            }

            $langs[] = $lang;
        }

        $langs = array_values(array_unique($langs));

        usort(
            $langs,
            static fn (string $a, string $b): int => $a === 'en'
                ? -1
                : ($b === 'en' ? 1 : strcmp($a, $b))
        );

        return $langs;
    }
}