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
            // 用法：{{ money(123.45) }} 或 {{ money(product.price) }}
            // 可选第二参小数位：{{ money(123.45, 2) }}
            new TwigFunction('money', [$this, 'money'], ['is_safe' => ['html']]),

            // 当前激活货币代码：{{ active_currency() }} -> "CZK" / "EUR" / ...
            new TwigFunction('active_currency', [$this, 'activeCurrency']),

            // 返回数组映射：{"CZK":1,"EUR":0.041,...} —— 基准与后端保持一致
            // Twig：{% set map = currencies_map() %}
            new TwigFunction('currencies_map', [$this, 'currenciesMap']),

            // 同上，但直接给前端 JSON 字符串：{{ currencies_map_json()|raw }}
            new TwigFunction('currencies_map_json', [$this, 'currenciesMapJson'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * 输出一个 <span class="money" data-raw="...">已按当前货币换算后的文本</span>
     *
     * 约定：
     * - data-raw 永远存“基准币”的原始数值（与你的 Currency.value 定义一致）
     * - 显示文本会根据当前 active currency 的 rate 进行 amount = base * rate 的换算
     */
    public function money(float|int|string|null $price, int $decimals = 2): string
    {
        $base = $this->toFloat($price);
        $active = $this->cm->getActiveCurrency() ?: 'EUR';
        $rate   = $this->cm->getRate($active);
        if (!\is_finite($rate) || $rate <= 0) {
            $rate = 1.0; // 兜底
        }

        $amount = $base * $rate;

        // 文本展示：1234.50 CZK（小数点固定 .，千分位用空格，避免本地化逗号导致 JS 解析问题）
        $text = \number_format($amount, $decimals, '.', ' ') . ' ' . \strtoupper($active);

        // 注意 data-raw 用“基准金额”便于前端再次换算
        return \sprintf(
            '<span class="money" data-raw="%s">%s</span>',
            $this->escapeAttr(\number_format($base, $decimals, '.', '')),
            $this->escapeHtml($text)
        );
    }

    /** 当前激活货币代码（大写） */
    public function activeCurrency(): string
    {
        $code = $this->cm->getActiveCurrency() ?: 'EUR';
        return \strtoupper($code);
    }

    /**
     * 返回 {"CODE": rate, ...}
     * 其中 rate == Currency.value（与你在前端 rates 的含义一致：显示 = base * rate）
     */
    public function currenciesMap(): array
    {
        $em = $this->doctrine->getManager();
        $repo = $em->getRepository(Currency::class);
        $all  = $repo->findAll();

        $map = [];
        foreach ($all as $c) {
            // 兼容 name 为空或 value 为空的情况
            $name = \strtoupper(\trim((string)$c->getName()));
            if ($name === '') {
                continue;
            }
            $val = (float)($c->getValue() ?? 1.0);
            $map[$name] = \is_finite($val) && $val > 0 ? $val : 1.0;
        }

        // 没有任何记录时兜底，至少返回一个
        if ($map === []) {
            $map['EUR'] = 1.0;
        }
        return $map;
    }

    /** 直接输出 JSON，便于在 <script> 中内联：const rates = {{ currencies_map_json()|raw }}; */
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

        // 容忍 "1 234,56" / "1,234.56" 等格式，统一转成可解析的
        $s = \str_replace([' ', "\u{00A0}"], '', (string)$v); // 去空格/不间断空格
        // 简单启发式：如果包含逗号但不包含点，认为逗号是小数点
        if (\str_contains($s, ',') && !\str_contains($s, '.')) {
            $s = \str_replace(',', '.', $s);
        } else {
            // 同时包含逗号和点，去掉千分位逗号
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