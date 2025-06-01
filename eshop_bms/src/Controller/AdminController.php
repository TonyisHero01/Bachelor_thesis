<?php
namespace App\Controller;

use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class AdminController extends BaseController
{
    private $params;

    public function __construct(ParameterBagInterface $params, Environment $twig, LoggerInterface $logger)
    {
        parent::__construct($twig, $logger);
        $this->params = $params;
    }

    #[Route('/admin', name: 'app_admin')]
    public function index(Request $request): Response
    {
        return $this->renderLocalized('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ], $request);
    }

    #[Route('/employee_list', name: 'show_All_employees')]
    public function showAllEmployees(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker, Request $request): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $employees = $entityManager->getRepository(Employee::class)->findAllEmployees();

        return $this->renderLocalized('admin/employee_list.html.twig', [
            'employees' => $employees,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH')
        ], $request);
    }

    #[Route('/employee_delete/{id}', name: 'employee_delete')]
    public function delete($id, EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker, Request $request): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $employeeRepository = $entityManager->getRepository(Employee::class);
        $employee = $employeeRepository->find($id);

        if ($employee) {
            $employeeRepository->deleteEmployee($employee);
            return new JsonResponse();
        } else {
            return new JsonResponse();
        }
    }

    #[Route('/employee_edit/{id}', name: 'employee_edit')]
    public function edit(EntityManagerInterface $entityManager, $id, Request $request, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        $employeeRepository = $entityManager->getRepository(Employee::class);
        $employee = $employeeRepository->find($id);

        return $this->renderLocalized('admin/employee_edit.html.twig', [
            'employee' => $employee,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->params->get('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->params->get('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->params->get('CONTENT_MAX_LENGTH')
        ], $request);
    }

    #[Route('/employee_save/{id}', name: 'save_employee', methods: ['POST'])]
    public function saveEmployee(Request $request, EntityManagerInterface $entityManager, $id, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', [], $request);
        }

        try {
            $adminRepository = $entityManager->getRepository(Employee::class);
            $admin = $adminRepository->find($id);

            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, true);

            $admin->setSurname($input['surname']);
            $admin->setName($input['name']);
            $admin->setUsername($input['username']);
            $admin->setEmail($input['email']);
            $admin->setRoles($input['roles']);
            $entityManager->persist($admin);
            $entityManager->flush();

            return new JsonResponse(['status' => 'Success']);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
