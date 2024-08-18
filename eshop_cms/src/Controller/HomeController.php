<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'home')]
    public function home(TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('employee_not_logged.html.twig', []);
        }
        $user = $tokenStorage->getToken()->getUser();
        $roles = $user->getRoles();

        return $this->render('home.html.twig', [
            'roles' => $roles,
        ]);
    }
}
