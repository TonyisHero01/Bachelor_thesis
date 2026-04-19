<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Currency;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

class CurrencyManager
{
    private const SESSION_KEY = 'active_currency';

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Returns the active currency code.
     *
     * If a currency is stored in session, it is used. Otherwise, the first currency
     * from the database is used. If none exists, "EUR" is returned as a fallback.
     */
    public function getActiveCurrency(): string
    {
        $session = $this->requestStack->getSession();
        $sessionCode = $session->get(self::SESSION_KEY);

        if (is_string($sessionCode) && $sessionCode !== '') {
            return $sessionCode;
        }

        $first = $this->doctrine->getManager()
            ->getRepository(Currency::class)
            ->findOneBy([]);

        return $first?->getName() ?? 'EUR';
    }

    /**
     * Returns the exchange rate for the given currency code.
     *
     * If the currency does not exist, 1.0 is returned.
     */
    public function getRate(string $code): float
    {
        $currency = $this->doctrine->getManager()
            ->getRepository(Currency::class)
            ->findOneBy(['name' => strtoupper($code)]);

        return $currency?->getValue() ?? 1.0;
    }

    /**
     * Stores the active currency code into the session.
     */
    public function setActiveCurrency(string $code): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, strtoupper($code));
    }
}