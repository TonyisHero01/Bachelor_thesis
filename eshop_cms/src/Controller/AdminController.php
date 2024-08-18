<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use App\Entity\Employee;
use Symfony\Component\HttpFoundation\JsonResponse;

class AdminController extends AbstractController
{
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
            return $this->render('employee_not_logged.html.twig', []);
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
    #[Route('/employee_edit', name: 'employee_edit')]
    public function edit(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }
    #[Route('/employee_delete/{id}', name: 'employee_delete')]
    public function delete($id,  EntityManagerInterface $entityManager): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee_not_logged.html.twig', []);
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
}
