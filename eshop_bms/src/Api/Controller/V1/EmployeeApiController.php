<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Employee;
use App\Repository\EmployeeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EMPLOYEES_READ')]
#[Route('/api/v1/employees', name: 'api_v1_employees_')]
class EmployeeApiController extends AbstractController
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Returns a list of employees.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $employees = $this->employeeRepository->findAll();

        $data = array_map(
            static function (Employee $employee): array {
                return [
                    'id' => $employee->getId(),
                    'name' => $employee->getName(),
                    'surname' => $employee->getSurname(),
                    'email' => $employee->getEmail(),
                    'phone' => $employee->getPhoneNumber(),
                    'roles' => $employee->getRoles(),
                    'created_at' => $employee->getCreatedAt()?->format('Y-m-d H:i:s'),
                ];
            },
            $employees
        );

        return $this->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Returns an employee detail by identifier.
     */
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $employee = $this->employeeRepository->find($id);
        if ($employee === null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Employee not found',
                ],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $employee->getId(),
                'name' => $employee->getName(),
                'surname' => $employee->getSurname(),
                'email' => $employee->getEmail(),
                'phone' => $employee->getPhoneNumber(),
                'roles' => $employee->getRoles(),
                'created_at' => $employee->getCreatedAt()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}