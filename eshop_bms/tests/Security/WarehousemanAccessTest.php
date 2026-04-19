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

        $user = $this->makeWarehousemanUser();
        $client->loginUser($user);

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
        $client->request('GET', '/employee_list');
        $status = $client->getResponse()->getStatusCode();

        $this->assertContains(
            $status,
            [Response::HTTP_FORBIDDEN, Response::HTTP_FOUND, Response::HTTP_SEE_OTHER],
            'Warehouseman must not access /employee_list'
        );

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