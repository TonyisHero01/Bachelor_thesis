<?php
// src/Twig/MoneyExtension.php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Currency;
use App\Service\CurrencyManager;
use Doctrine\Persistence\ManagerRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MoneyExtension extends AbstractExtension
{
    public function __construct(
        private readonly CurrencyManager $cm,
        private readonly ManagerRegistry $doctrine
    ) {}

    public function getFunctions(): array
    {
        return [

            new TwigFunction('money', [$this, 'money'], ['is_safe' => ['html']]),

            new TwigFunction('active_currency', [$this, 'activeCurrency']),

            new TwigFunction('currencies_map', [$this, 'currenciesMap']),
            new TwigFunction('currencies_map_json', [$this, 'currenciesMapJson'], ['is_safe' => ['html']]),
        ];
    }

    public function money(float|int|string|null $price, int $decimals = 2): string
    {
        $base = $this->toFloat($price);
        $active = $this->cm->getActiveCurrency() ?: 'CZK';
        $rate   = $this->cm->getRate($active);
        if (!\is_finite($rate) || $rate <= 0) {
            $rate = 1.0; 
        }

        $amount = $base * $rate;

        $text = \number_format($amount, $decimals, '.', ' ') . ' ' . \strtoupper($active);

        return \sprintf(
            '<span class="money" data-raw="%s">%s</span>',
            $this->escapeAttr(\number_format($base, $decimals, '.', '')),
            $this->escapeHtml($text)
        );
    }

    public function activeCurrency(): string
    {
        $code = $this->cm->getActiveCurrency() ?: 'CZK';
        return \strtoupper($code);
    }

    public function currenciesMap(): array
    {
        $em = $this->doctrine->getManager();
        $repo = $em->getRepository(Currency::class);
        $all  = $repo->findAll();

        $map = [];
        foreach ($all as $c) {
            $name = \strtoupper(\trim((string)$c->getName()));
            if ($name === '') {
                continue;
            }
            $val = (float)($c->getValue() ?? 1.0);
            $map[$name] = \is_finite($val) && $val > 0 ? $val : 1.0;
        }

        if ($map === []) {
            $map['CZK'] = 1.0;
        }
        return $map;
    }

    public function currenciesMapJson(): string
    {
        $map = $this->currenciesMap();
        return \json_encode($map, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /* ====================== Helpers ====================== */

    private function toFloat(float|int|string|null $v): float
    {
        if ($v === null || $v === '') return 0.0;
        if (\is_numeric($v)) return (float)$v;

        $s = \str_replace([' ', "\u{00A0}"], '', (string)$v);

        if (\str_contains($s, ',') && !\str_contains($s, '.')) {
            $s = \str_replace(',', '.', $s);
        } else {
            $s = \str_replace(',', '', $s);
        }
        return \is_numeric($s) ? (float)$s : 0.0;
    }

    private function escapeHtml(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeAttr(string $s): string
    {
        return $this->escapeHtml($s);
    }
}