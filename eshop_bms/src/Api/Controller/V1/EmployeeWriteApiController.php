<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Employee;
use App\Repository\EmployeeRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EMPLOYEES_WRITE')]
#[Route('/api/v1/employees', name: 'api_v1_employees_write_')]
class EmployeeWriteApiController extends AbstractController
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Creates a new employee (BMS only).
     *
     * Expected body:
     * - name: string (required)
     * - surname: string (required)
     * - email: string (required, valid email)
     * - phone: string|null (optional)
     * - password: string (required)
     * - roles: string[] (optional; defaults to ["ROLE_EMPLOYEE"])
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $payload = $this->getJson($request);

        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        $surname = isset($payload['surname']) ? trim((string) $payload['surname']) : '';
        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';
        $phone = array_key_exists('phone', $payload) ? $payload['phone'] : null;

        if ($name === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'Field "name" is required'],
                422
            );
        }

        if ($surname === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'Field "surname" is required'],
                422
            );
        }

        if ($email === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'Field "email" is required'],
                422
            );
        }

        if ($password === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'Field "password" is required'],
                422
            );
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->json(
                ['status' => 'error', 'message' => 'Invalid email format'],
                400
            );
        }

        $roles = ['ROLE_EMPLOYEE'];
        if (isset($payload['roles']) && is_array($payload['roles'])) {
            $roles = $this->normalizeRoles($payload['roles']);
            if ($roles === []) {
                $roles = ['ROLE_EMPLOYEE'];
            }
        }

        $employee = new Employee();
        $employee->setName($name);
        $employee->setSurname($surname);
        $employee->setEmail($email);
        $employee->setRoles($roles);

        if ($phone !== null) {
            $phoneStr = trim((string) $phone);
            if ($phoneStr !== '') {
                $employee->setPhoneNumber($phoneStr);
            }
        }

        $employee->setPassword($hasher->hashPassword($employee, $password));

        try {
            $this->em->persist($employee);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(
                ['status' => 'error', 'message' => 'Email already exists'],
                409
            );
        }

        return $this->json(
            [
                'status' => 'success',
                'data' => [
                    'id' => $employee->getId(),
                    'email' => $employee->getEmail(),
                    'roles' => $employee->getRoles(),
                    'created_at' => $employee->getCreatedAt()?->format('Y-m-d H:i:s'),
                ],
            ],
            201
        );
    }

    /**
     * Updates an existing employee (BMS only).
     *
     * Allowed partial body:
     * - name: string (non-empty)
     * - surname: string (non-empty)
     * - email: string (non-empty, valid email)
     * - phone: string|null (empty or null clears phone)
     * - roles: string[] (non-empty)
     * - password: string (non-empty)
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $employee = $this->employeeRepository->find($id);
        if ($employee === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Employee not found'],
                404
            );
        }

        $payload = $this->getJson($request);

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return $this->json(
                    ['status' => 'error', 'message' => 'name cannot be empty'],
                    400
                );
            }

            $employee->setName($name);
        }

        if (array_key_exists('surname', $payload)) {
            $surname = trim((string) $payload['surname']);
            if ($surname === '') {
                return $this->json(
                    ['status' => 'error', 'message' => 'surname cannot be empty'],
                    400
                );
            }

            $employee->setSurname($surname);
        }

        if (array_key_exists('email', $payload)) {
            $email = trim((string) $payload['email']);
            if ($email === '') {
                return $this->json(
                    ['status' => 'error', 'message' => 'email cannot be empty'],
                    400
                );
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return $this->json(
                    ['status' => 'error', 'message' => 'Invalid email format'],
                    400
                );
            }

            $employee->setEmail($email);
        }

        if (array_key_exists('phone', $payload)) {
            $phone = trim((string) ($payload['phone'] ?? ''));
            $employee->setPhoneNumber($phone === '' ? null : $phone);
        }

        if (array_key_exists('roles', $payload)) {
            if (!is_array($payload['roles'])) {
                return $this->json(
                    ['status' => 'error', 'message' => 'roles must be an array'],
                    400
                );
            }

            $roles = $this->normalizeRoles($payload['roles']);
            if ($roles === []) {
                return $this->json(
                    ['status' => 'error', 'message' => 'roles cannot be empty'],
                    400
                );
            }

            $employee->setRoles($roles);
        }

        if (array_key_exists('password', $payload)) {
            $password = (string) ($payload['password'] ?? '');
            if ($password === '') {
                return $this->json(
                    ['status' => 'error', 'message' => 'password cannot be empty'],
                    400
                );
            }

            $employee->setPassword($hasher->hashPassword($employee, $password));
        }

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(
                ['status' => 'error', 'message' => 'Email already exists'],
                409
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $employee->getId(),
                'email' => $employee->getEmail(),
                'roles' => $employee->getRoles(),
            ],
        ]);
    }

    /**
     * Deletes an employee by identifier (BMS only).
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $employee = $this->employeeRepository->find($id);
        if ($employee === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Employee not found'],
                404
            );
        }

        $this->em->remove($employee);
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['id' => $id],
        ]);
    }

    /**
     * Changes an employee password by identifier (BMS only).
     *
     * Expected body:
     * - password: string (required)
     */
    #[Route('/{id}/password', name: 'password', methods: ['POST'])]
    public function changePassword(int $id, Request $request, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $employee = $this->employeeRepository->find($id);
        if ($employee === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Employee not found'],
                404
            );
        }

        $payload = $this->getJson($request);

        $password = isset($payload['password']) ? (string) $payload['password'] : '';
        if ($password === '') {
            return $this->json(
                ['status' => 'error', 'message' => 'Password is required'],
                422
            );
        }

        $employee->setPassword($hasher->hashPassword($employee, $password));
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['id' => $employee->getId()],
        ]);
    }

    /**
     * Decodes JSON request body into an associative array.
     *
     * @return array<string, mixed>
     */
    private function getJson(Request $request): array
    {
        $raw = (string) $request->getContent();
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Normalizes roles to unique uppercase ROLE_* strings.
     *
     * @param array<int, mixed> $roles
     *
     * @return list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        $out = [];

        foreach ($roles as $role) {
            $role = strtoupper(trim((string) $role));
            if ($role !== '' && str_starts_with($role, 'ROLE_')) {
                $out[] = $role;
            }
        }

        $out = array_values(array_unique($out));

        return $out;
    }
}