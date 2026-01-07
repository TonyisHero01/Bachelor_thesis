<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Currency;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CurrencyManager
{
    private ManagerRegistry $doctrine;
    private RequestStack $requestStack;
    private ?LoggerInterface $logger;

    public function __construct(
        ManagerRegistry $doctrine,
        RequestStack $requestStack,
        ?LoggerInterface $logger = null
    ) {
        $this->doctrine     = $doctrine;
        $this->requestStack = $requestStack;
        $this->logger       = $logger;
    }

    /** @return Currency[] */
    public function getAll(): array
    {
        // 两种写法都可：$this->doctrine->getRepository(Currency::class) 或 getManager()->getRepository()
        return $this->doctrine->getRepository(Currency::class)->findAll() ?: [];
    }

    /** 兼容别名：MoneyExtension 里用到 */
    public function getActiveCurrency(): string
    {
        return $this->getActiveCode();
    }

    /** 兼容别名：控制器/JS 同步时可用 */
    public function setActiveCurrency(string $code): void
    {
        $this->setActiveCode($code);
    }

    public function getActiveCode(): string
    {
        $session = $this->requestStack->getSession();
        $code = strtoupper((string)($session?->get('active_currency') ?? ''));
        if ($code !== '') return $code;

        // 0) 仓库默认
        $repo = $this->doctrine->getRepository(Currency::class);
        if (method_exists($repo, 'findDefaultCurrency')) {
            $def = $repo->findDefaultCurrency();
            if ($def) {
                $code = $this->codeFromEntity($def);
                if ($code !== '') {
                    $session?->set('active_currency', $code);
                    $this->logger?->info("💱 active_currency <- repository default: {$code}");
                    return $code;
                }
            }
        }

        // 1) 列表优先 CZK -> 实体默认 -> 第一项 -> EUR
        $all = $this->getAll();
        foreach ($all as $c) {
            if ($this->codeFromEntity($c) === 'CZK') {
                $session?->set('active_currency', 'CZK');
                return 'CZK';
            }
        }
        foreach ($all as $c) {
            if ($this->isDefaultEntity($c)) {
                $code = $this->codeFromEntity($c) ?: 'EUR';
                $session?->set('active_currency', $code);
                return $code;
            }
        }
        if (!empty($all)) {
            $code = $this->codeFromEntity($all[0]) ?: 'EUR';
            $session?->set('active_currency', $code);
            return $code;
        }

        $session?->set('active_currency', 'EUR');
        $this->logger?->warning("💱 No currencies in DB, fallback to EUR");
        return 'EUR';
    }

    public function setActiveCode(string $code): void
    {
        $code = strtoupper(trim($code));
        $this->requestStack->getSession()?->set('active_currency', $code);
        $this->logger?->info("💱 active_currency set to: {$code}");
    }

    public function getRate(string $code): float
    {
        $code = strtoupper($code);
        foreach ($this->getAll() as $c) {
            if ($this->codeFromEntity($c) === $code) {
                return method_exists($c, 'getValue') ? (float)$c->getValue() : 1.0;
            }
        }
        return 1.0;
    }

    public function convert(float|int|string $baseAmount, ?string $toCode = null): float
    {
        $num  = (float)$baseAmount;
        $code = $toCode ? strtoupper($toCode) : $this->getActiveCode();
        $rate = $this->getRate($code);
        return round($num * $rate, 2);
    }

    private function codeFromEntity(object $c): string
    {
        foreach (['getName', 'getCode', 'getSymbol'] as $m) {
            if (method_exists($c, $m)) {
                $v = strtoupper((string)$c->$m());
                if ($v !== '') return $v;
            }
        }
        return '';
    }

    private function isDefaultEntity(object $c): bool
    {
        foreach (['isDefault', 'getIsDefault', 'isIsDefault'] as $m) {
            if (method_exists($c, $m)) return (bool)$c->$m();
        }
        return false;
    }
}