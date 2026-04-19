<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

final class RegistrationController extends BaseController
{
    /**
     * Displays and processes the employee registration form.
     */
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        $this->denyUnlessAdminOrSuperAdmin();
        $employee = new Employee();
        $form = $this->createForm(RegistrationFormType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();

            $employee->setPassword(
                $passwordHasher->hashPassword($employee, $plainPassword)
            );

            $em->persist($employee);
            $em->flush();

            return $this->redirectToRoute('register_succesfull');
        }

        return $this->renderLocalized('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * Displays a registration success notification page.
     */
    #[Route('/register_succesfull', name: 'register_succesfull', methods: ['GET'])]
    public function registerSuccessfullNotificate(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();
        return $this->renderLocalized('registration/register_successfull_notification.html.twig', []);
    }

    private function denyUnlessAdminOrSuperAdmin(): void
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
    }
}