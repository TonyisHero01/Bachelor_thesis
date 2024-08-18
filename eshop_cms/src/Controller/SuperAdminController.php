<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use App\Entity\Employee;
use Symfony\Component\HttpFoundation\JsonResponse;

class SuperAdminController extends AbstractController
{
    #[Route('/super/admin', name: 'app_super_admin')]
    public function index(): Response
    {
        return $this->render('super_admin/index.html.twig', [
            'controller_name' => 'SuperAdminController',
        ]);
    }

    #[Route('/admin_list', name: 'show_All_admins')]
    public function showAllAdmins(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee_not_logged.html.twig', []);
        }

        $admins = $entityManager->getRepository(Employee::class)->findAllWithRoleAdmin();

        $admin_list = '';

        foreach ($admins as $admin) 
        {
            $admin_list .= '<div>' . $admin->getName() . ' ' . $admin->getSurname() . '</div>' . '<br>';
        }

        return $this->render('super_admin/admin_list.html.twig', [
            'employees' => $admins,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH')
        ]);
    }
}
