<?php

namespace App\Controller;

use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AdminController extends BaseController
{
    private const ROLE_ADMIN = 'ROLE_ADMIN';
    private const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    private const ROLE_WHITELIST = [
        'ROLE_SUPER_ADMIN',
        'ROLE_ADMIN',
        'ROLE_WAREHOUSEMAN',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_TRANSLATOR',
        'ROLE_EVENT_MANAGER',
        'ROLE_ACCOUNTING',
    ];

    private ParameterBagInterface $params;

    public function __construct(
        ParameterBagInterface $params,
        \Twig\Environment $twig,
        \Psr\Log\LoggerInterface $logger,
        \Doctrine\Persistence\ManagerRegistry $doctrine
    ) {
        parent::__construct($twig, $logger, $doctrine);

        $this->params = $params;
    }

    /**
     * Displays the admin dashboard homepage.
     */
    #[Route('/admin', name: 'app_admin')]
    public function index(
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted(self::ROLE_ADMIN)) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        return $this->renderLocalized(
            'admin/index.html.twig',
            [
                'controller_name' => 'AdminController',
            ],
            $request
        );
    }

    /**
     * Displays a list of all employees for admin users.
     */
    #[Route('/employee_list', name: 'show_All_employees')]
    public function showAllEmployees(
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $authorizationChecker,
        Request $request
    ): Response {
        if (!$authorizationChecker->isGranted(self::ROLE_ADMIN)) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $employees = $entityManager->getRepository(Employee::class)->findAllEmployees();

        return $this->renderLocalized(
            'admin/employee_list.html.twig',
            [
                'employees' => $employees,
                'MAX_ARTICLES_COUNT_PER_PAGE' => $this->params->get('MAX_ARTICLES_COUNT_PER_PAGE'),
                'NAME_MAX_LENGTH' => $this->params->get('NAME_MAX_LENGTH'),
                'CONTENT_MAX_LENGTH' => $this->params->get('CONTENT_MAX_LENGTH'),
            ],
            $request
        );
    }

    /**
     * Deletes the employee with the given ID.
     */
    #[Route('/employee_delete/{id}', name: 'employee_delete', methods: ['POST'])]
    public function delete(
        int $id,
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted(self::ROLE_ADMIN)) {
            return new JsonResponse(['status' => 'Error', 'message' => 'Forbidden'], 403);
        }

        $employeeRepository = $entityManager->getRepository(Employee::class);
        $employee = $employeeRepository->find($id);

        if ($employee === null) {
            return new JsonResponse(['status' => 'Error', 'message' => 'Employee not found'], 404);
        }

        $employeeRepository->deleteEmployee($employee);

        return new JsonResponse(['status' => 'Success']);
    }

    /**
     * Displays the edit form for the employee with the given ID.
     */
    #[Route('/employee_edit/{id}', name: 'employee_edit')]
    public function edit(
        EntityManagerInterface $entityManager,
        int $id,
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted(self::ROLE_ADMIN)) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $employeeRepository = $entityManager->getRepository(Employee::class);
        $employee = $employeeRepository->find($id);

        if ($employee === null) {
            throw $this->createNotFoundException('Employee not found.');
        }

        return $this->renderLocalized(
            'admin/employee_edit.html.twig',
            [
                'employee' => $employee,
                'MAX_ARTICLES_COUNT_PER_PAGE' => $this->params->get('MAX_ARTICLES_COUNT_PER_PAGE'),
                'NAME_MAX_LENGTH' => $this->params->get('NAME_MAX_LENGTH'),
                'CONTENT_MAX_LENGTH' => $this->params->get('CONTENT_MAX_LENGTH'),
            ],
            $request
        );
    }

    /**
     * Persists updated employee details from a JSON request body.
     */
    #[Route('/employee_save/{id}', name: 'save_employee', methods: ['POST'])]
    public function saveEmployee(
        Request $request,
        EntityManagerInterface $entityManager,
        int $id,
        AuthorizationCheckerInterface $authorizationChecker
    ): Response {
        if (!$authorizationChecker->isGranted(self::ROLE_ADMIN)) {
            return new JsonResponse(['status' => 'Error', 'message' => 'Forbidden'], 403);
        }

        try {
            $employeeRepository = $entityManager->getRepository(Employee::class);
            $employee = $employeeRepository->find($id);

            if ($employee === null) {
                return new JsonResponse(['status' => 'Error', 'message' => 'Employee not found'], 404);
            }

            $input = $request->toArray();

            $employee->setSurname((string) ($input['surname'] ?? ''));
            $employee->setName((string) ($input['name'] ?? ''));
            $employee->setPhoneNumber((string) ($input['phoneNumber'] ?? ''));
            $employee->setEmail((string) ($input['email'] ?? ''));

            if (\array_key_exists('roles', $input)) {
                $requestedRoles = (array) $input['roles'];

                $filteredRoles = $this->sanitizeRoles(
                    $requestedRoles,
                    $authorizationChecker->isGranted(self::ROLE_SUPER_ADMIN)
                );

                if ($filteredRoles !== []) {
                    $employee->setRoles($filteredRoles);
                }
            }

            $entityManager->persist($employee);
            $entityManager->flush();

            return new JsonResponse(['status' => 'Success']);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['status' => 'Error', 'message' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Filters roles by whitelist and applies assignment restrictions.
     *
     * @param array<int, mixed> $roles
     *
     * @return string[]
     */
    private function sanitizeRoles(array $roles, bool $isSuperAdmin): array
    {
        $roles = \array_values(\array_unique(\array_map('strval', $roles)));
        $roles = \array_values(\array_intersect($roles, self::ROLE_WHITELIST));

        if ($isSuperAdmin) {
            return $roles;
        }

        return \array_values(\array_diff($roles, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN]));
    }
}