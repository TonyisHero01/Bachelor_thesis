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

class SuperAdminController extends BaseController
{
    private $params;
    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }
    #[Route('/super/admin', name: 'app_super_admin')]
    public function index(): Response
    {
        return $this->renderLocalized('super_admin/index.html.twig', [
            'controller_name' => 'SuperAdminController',
        ]);
    }

    #[Route('/admin_list', name: 'show_All_admins')]
    public function showAllAdmins(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }

        $admins = $entityManager->getRepository(Employee::class)->findAllWithRoleAdmin();

        $admin_list = '';

        foreach ($admins as $admin) 
        {
            $admin_list .= '<div>' . $admin->getName() . ' ' . $admin->getSurname() . '</div>' . '<br>';
        }

        return $this->renderLocalized('super_admin/admin_list.html.twig', [
            'employees' => $admins,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH')
        ]);
    }
    #[Route('/admin_edit/{id}', name: 'admin_edit')]
    public function edit(EntityManagerInterface $entityManager, $id, Request $request, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }
        $employeeRepository = $entityManager->getRepository(Employee::class);
        $admin = $employeeRepository->find($id);

        return $this->renderLocalized('super_admin/admin_edit.html.twig', [
            'admin' => $admin,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->params->get('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->params->get('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->params->get('CONTENT_MAX_LENGTH')
        ]);
    }
    #[Route('/admin_save/{id}', name: 'save_admin', methods: ['POST'])]
    public function saveAdmin(Request $request, EntityManagerInterface $entityManager, $id, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
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
