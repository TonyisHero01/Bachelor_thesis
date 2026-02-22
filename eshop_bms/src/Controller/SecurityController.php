<?php

namespace App\Controller;

use App\Entity\Employee;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends BaseController
{
    /**
     * Displays the login form and shows authentication errors.
     */
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof Employee) {
            return $this->redirectToRoute('home');
        }

        return $this->renderLocalized('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Logs the current user out (handled by Symfony firewall).
     */
    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): void
    {
        throw new \LogicException('This method is intercepted by the logout mechanism.');
    }
}