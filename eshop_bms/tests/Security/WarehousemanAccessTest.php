<?php

namespace App\Tests\Security;

use App\Entity\Employee;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class WarehousemanAccessTest extends WebTestCase
{
    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWarehousemanAccessRules(): void
    {
        $client = static::createClient();

        // 关键：不落库，直接构造一个带 ROLE_WAREHOUSEMAN 的用户并 login
        $user = $this->makeWarehousemanUser();
        $client->loginUser($user);

        // ✅ 这些页面：仓库员应该能进（至少不是 403/404）
        $allowedPaths = [
            '/warehouse',
            '/warehouse/order_management',
            '/warehouse/low-stock',
            '/warehouse/return-requests',
        ];

        foreach ($allowedPaths as $path) {
            $client->request('GET', $path);

            $status = $client->getResponse()->getStatusCode();

            $this->assertNotSame(Response::HTTP_FORBIDDEN, $status, "Warehouseman should NOT be forbidden for $path");
            $this->assertNotSame(Response::HTTP_NOT_FOUND, $status, "Route not found: $path");
        }

        // ❌ 员工管理页：仓库员不应有权限
        $client->request('GET', '/employee_list');
        $status = $client->getResponse()->getStatusCode();

        // 注意：你这里 assertContains 参数顺序写反了（needle, haystack）
        // 下面是正确用法：assertContains(needle, haystack)
        $this->assertContains(
            $status,
            [Response::HTTP_FORBIDDEN, Response::HTTP_FOUND, Response::HTTP_SEE_OTHER],
            'Warehouseman must not access /employee_list'
        );

        // 如果是跳转，进一步确认不是“正常页面跳转”，而是去登录/无权限页
        if (in_array($status, [Response::HTTP_FOUND, Response::HTTP_SEE_OTHER], true)) {
            $location = $client->getResponse()->headers->get('Location') ?? '';
            $this->assertTrue(
                str_contains($location, '/login') || str_contains($location, '/not-logged'),
                "Expected redirect to /login or /not-logged, got: $location"
            );
        }
    }

    private function makeWarehousemanUser(): Employee
    {
        $u = new Employee();

        if (method_exists($u, 'setEmail')) {
            $u->setEmail('warehouseman@test.local');
        }
        if (method_exists($u, 'setRoles')) {
            $u->setRoles(['ROLE_WAREHOUSEMAN']);
        }
        if (method_exists($u, 'setUserIdentifier')) {
            $u->setUserIdentifier('warehouseman@test.local');
        }

        return $u;
    }
}