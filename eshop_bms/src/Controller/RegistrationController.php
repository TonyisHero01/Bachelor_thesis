<?php
namespace App\Controller;

use App\Entity\Employee;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RegistrationController extends BaseController
{
    #[Route('/register', name: 'app_register')]
    /**
     * Displays and processes the employee registration form.
     *
     * Only authenticated users can register new employees (e.g., admin creating sub-accounts).
     *
     * @param Request $request The current HTTP request.
     * @param UserPasswordHasherInterface $passwordHasher Password hashing service.
     * @param EntityManagerInterface $entityManager Doctrine EntityManager for persisting employee data.
     * @param AuthorizationCheckerInterface $authorizationChecker Security checker for user authentication.
     *
     * @return Response Rendered registration form or redirect to success page.
     */
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }
        $employee = new Employee();
        $form = $this->createForm(RegistrationFormType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $employee->setPassword(
                $passwordHasher->hashPassword(
                    $employee,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($employee);
            $entityManager->flush();

            return $this->redirectToRoute('register_succesfull');
        }

        return $this->renderLocalized('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
    #[Route('/register_succesfull', name: 'register_succesfull')]
    public function registerSuccessfullNotificate(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->renderLocalized('employee/employee_not_logged.html.twig', []);
        }
        return $this->renderLocalized('registration/register_successfull_notification.html.twig', []);
    }
}