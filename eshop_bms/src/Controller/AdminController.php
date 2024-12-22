<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use App\Entity\Employee;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AdminController extends AbstractController
{
    private $params;
    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }
    #[Route('/employee_list', name: 'show_All_employees')]
    public function showAllEmployees(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }

        $employees = $entityManager->getRepository(Employee::class)->findAllEmployees();

        $employee_list = '';

        foreach ($employees as $employee) 
        {
            $employee_list .= '<div>' . $employee->getName() . ' ' . $employee->getSurname() . '</div>' . '<br>';
        }

        return $this->render('admin/employee_list.html.twig', [
            'employees' => $employees,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH')
        ]);
    }
    
    #[Route('/employee_delete/{id}', name: 'employee_delete')]
    public function delete($id,  EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
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
            return $this->render('employee/employee_not_logged.html.twig', []);
        }
        $employeeRepository = $entityManager->getRepository(Employee::class);
        $employee = $employeeRepository->find($id);

        return $this->render('admin/employee_edit.html.twig', [
            'employee' => $employee,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->params->get('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->params->get('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->params->get('CONTENT_MAX_LENGTH')
        ]);
    }
    #[Route('/employee_save/{id}', name: 'save_employee', methods: ['POST'])]
    public function saveEmployee(Request $request, EntityManagerInterface $entityManager, $id, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee/employee_not_logged.html.twig', []);
        }
        try {
            $adminRepository = $entityManager->getRepository(Employee::class);
            $admin = $adminRepository->find($id);

            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, TRUE);
            $surname = $input["surname"];
            $name = $input["name"];
            $username = $input["username"];
            $phoneNumber = $input["phoneNumber"];
            $email = $input["email"];
            $roles = $input["roles"];


            $admin->setSurname($surname);
            $admin->setName($name);
            $admin->setUsername($username);
            $admin->setEmail($email);
            $admin->setRoles($roles);
            $entityManager->persist($admin);

            $entityManager->flush();

            return new JsonResponse(["status" => "Success"]);
        } catch (Exception $e) {
            $logger->error('An error occurred: ' . $e->getMessage());
            $logger->error('Stack trace: ' . $e->getTraceAsString());
        }
        
    }
}
