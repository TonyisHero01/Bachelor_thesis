<?php
namespace App\EventSubscriber;

use App\Repository\ShopInfoRepository;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class GlobalVariableSubscriber implements EventSubscriberInterface
{
    private $twig;
    private $shopInfoRepository;

    public function __construct(Environment $twig, ShopInfoRepository $shopInfoRepository)
    {
        $this->twig = $twig;
        $this->shopInfoRepository = $shopInfoRepository;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $shopInfo = $this->shopInfoRepository->findOneBy([]); // 只取一条记录
        $this->twig->addGlobal('shopName', $shopInfo ? $shopInfo->getEshopName() : 'My Shop');

        // 获取语言列表
        $localeDir = __DIR__ . '/../../templates/locale';
        $languages = is_dir($localeDir) ? array_filter(scandir($localeDir), fn($f) => is_dir($localeDir . '/' . $f) && !in_array($f, ['.', '..'])) : [];

        $this->twig->addGlobal('availableLanguages', $languages);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}