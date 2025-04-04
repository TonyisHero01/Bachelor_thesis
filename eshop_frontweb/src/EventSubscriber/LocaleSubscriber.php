<?php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private string $defaultLocale;

    public function __construct(string $defaultLocale = 'en')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // ⚠️ 必须有 session，否则不能保存 locale
        if (!$request->hasSession()) {
            return;
        }

        // 如果 URL 上有明确指定语言，就用它，并写入 session
        if ($locale = $request->query->get('_locale')) {
            $request->setLocale($locale);
            $request->getSession()->set('_locale', $locale);
        }
        // 否则从 session 中读取
        elseif ($request->getSession()->has('_locale')) {
            $request->setLocale($request->getSession()->get('_locale'));
        }
        // 否则设为默认值
        else {
            $request->setLocale($this->defaultLocale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}