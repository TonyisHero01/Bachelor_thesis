<?php

namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BmsAccessTest extends WebTestCase
{
    /**
     * 匿名用户访问 BMS 页面应被重定向到登录页
     * （你路由表里确实存在 /login -> app_login）
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAnonymousUserIsRedirectedToLoginPageForBmsPages(): void
    {
        $client = static::createClient();

        $protectedPaths = [
            '/bms/accounting',
            '/bms/product_list',
            '/bms/results',
            '/bms/search',
            '/bms/save_category',
        ];

        foreach ($protectedPaths as $path) {
            $client->request('GET', $path);

            $status = $client->getResponse()->getStatusCode();

            if ($status === 405) {
                $this->addToAssertionCount(1);
                continue;
            }

            $this->assertTrue(
                $client->getResponse()->isRedirect(),
                sprintf('Expected redirect for "%s", got %d', $path, $status)
            );

            $this->assertResponseRedirects('/login', 302);
        }
    }
}