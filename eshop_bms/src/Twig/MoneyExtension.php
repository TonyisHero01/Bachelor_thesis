<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Currency;
use App\Service\CurrencyManager;
use Doctrine\Persistence\ManagerRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MoneyExtension extends AbstractExtension
{
    public function __construct(
        private readonly CurrencyManager $cm,
        private readonly ManagerRegistry $doctrine,
    ) {
    }

    /**
     * @return array<int, TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            // {{ money(123.45) }}
            new TwigFunction('money', [$this, 'money'], ['is_safe' => ['html']]),

            // {{ active_currency() }}
            new TwigFunction('active_currency', [$this, 'activeCurrency']),

            // {{ currencies_map() }} → for JS usage
            new TwigFunction('currencies_map', [$this, 'currenciesMap']),
        ];
    }

    /**
     * Formats a price using the currently active currency.
     *
     * The raw (base) price is preserved in a data attribute so the frontend
     * can recompute values without a page reload.
     */
    public function money(float|int $price): string
    {
        $basePrice = (float) $price;
        $currency  = $this->cm->getActiveCurrency();
        $rate      = $this->cm->getRate($currency);

        $amount = $basePrice * $rate;

        return sprintf(
            '<span class="money" data-raw="%s">%s</span>',
            htmlspecialchars((string) $basePrice, ENT_QUOTES),
            htmlspecialchars(
                number_format($amount, 2, '.', ' ') . ' ' . $currency,
                ENT_QUOTES
            )
        );
    }

    /**
     * Returns the currently selected currency code (e.g. EUR, USD).
     */
    public function activeCurrency(): string
    {
        return $this->cm->getActiveCurrency();
    }

    /**
     * Returns a map of currency rates for frontend usage.
     *
     * Example:
     * {
     *   "EUR": 1.0,
     *   "USD": 24.5
     * }
     *
     * @return array<string, float>
     */
    public function currenciesMap(): array
    {
        $repo = $this->doctrine->getManager()->getRepository(Currency::class);
        $all  = $repo->findAll();

        $map = [];
        foreach ($all as $currency) {
            $map[$currency->getName()] = $currency->getValue() ?? 1.0;
        }

        return $map;
    }
}