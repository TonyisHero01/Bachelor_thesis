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

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $employee = new Employee();
        $form = $this->createForm(RegistrationFormType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the plain password
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

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
    #[Route('/register_succesfull', name: 'register_succesfull')]
    public function registerSuccessfullNotificate(EntityManagerInterface $entityManager): Response
    {
        return $this->render('register_successfull_notification.html.twig', []);
    }
}