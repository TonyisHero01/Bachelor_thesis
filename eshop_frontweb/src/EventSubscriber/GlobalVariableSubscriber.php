<?php
// src/EventSubscriber/GlobalVariableSubscriber.php
namespace App\EventSubscriber;

use App\Repository\ShopInfoRepository;
use App\Repository\CurrencyRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final class GlobalVariableSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
        private ShopInfoRepository $shopInfoRepository,
        private CurrencyRepository $currencyRepository,
        private string $projectDir, // 注入 %kernel.project_dir%
    ) {}

    public static function getSubscribedEvents(): array
    {
        // 用 REQUEST，比 CONTROLLER 更早且有 session，可读/设定默认货币
        return [ KernelEvents::REQUEST => 'onKernelRequest' ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $session = $request->getSession();

        // ---- 1) shopInfo / shopName ----
        $shopInfo = $this->shopInfoRepository->findOneBy([], ['id' => 'DESC']);
        $shopName = $shopInfo?->getEshopName() ?? 'Eshop';

        // ---- 2) currencies / defaultCurrency ----
        $currencies = $this->currencyRepository->findAll();

        // 从 session 取当前货币；若无则取 isDefault，否则取第一个
        $active = $session?->get('active_currency');
        if (!$active && !empty($currencies)) {
            $default = null;
            foreach ($currencies as $c) {
                // 你的实体方法名是 isIsDefault()
                if (method_exists($c, 'isIsDefault') && $c->isIsDefault()) {
                    $default = $c->getName();
                    break;
                }
            }
            $active = $default ?? $currencies[0]->getName();
            $session?->set('active_currency', $active);
        }

        // ---- 3) availableLanguages: 扫描 templates/locale ----
        $localeRoot = $this->projectDir . '/templates/locale';
        $langs = ['en'];
        if (is_dir($localeRoot)) {
            foreach (scandir($localeRoot) ?: [] as $d) {
                if ($d === '.' || $d === '..') continue;
                if (is_dir($localeRoot . '/' . $d)) {
                    $langs[] = strtolower($d);
                }
            }
            $langs = array_values(array_unique($langs));
            usort($langs, fn($a,$b) => $a === 'en' ? -1 : ($b === 'en' ? 1 : strcmp($a,$b)));
        }

        // ---- 4) 注入 Twig 全局变量 ----
        $this->twig->addGlobal('shopInfo', $shopInfo);     // base 里需要对象
        $this->twig->addGlobal('shopName', $shopName);     // 有时只要名字也方便
        $this->twig->addGlobal('currencies', $currencies);
        $this->twig->addGlobal('defaultCurrency', $active);
        $this->twig->addGlobal('availableLanguages', $langs);
    }
}