<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Form\EmployeeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Controller\employeeRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class EmployeeController extends AbstractController
{
    #[Route('/employee', name: 'app_employee')]
    public function index(): Response
    {
        return $this->render('employee/index.html.twig', [
            'controller_name' => 'EmployeeController',
        ]);
    }
    #[Route('/register_employee', name: 'register_employee')]
    public function registerEmployee(EntityManagerInterface $entityManager): Response
    {
        return $this->render('employee_reg.html.twig', []);
    }

    #[Route('/register_succesfull', name: 'register_succesfull')]
    public function registerSuccessfullNotificate(EntityManagerInterface $entityManager): Response
    {
        return $this->render('register_successfull_notification.html.twig', []);
    }
    
    #[Route('/employee_save', name: 'employee_save')]
    public function employeeSave(EntityManagerInterface $entityManager, LoggerInterface $logger): Response
    {
        try {
            $employeeRepository = $entityManager->getRepository(Employee::class);

            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, TRUE);
            $surname = $input["surname"];
            $name = $input["name"];
            $username = $input["username"];
            $password = $input["password"];
            $phone_number = $input["phone_number"];
            $email = $input["email"];
            $positions = $input["positions"];
            $roles = explode(",", $positions);

            $employee = new Employee();
            $employee->setSurname($surname);
            $employee->setName($name);
            $employee->setUsername($username);
            $employee->setPassword($password);
            $employee->setPhoneNumber($phone_number);
            $employee->setEmail($email);
            $employee->setRoles($roles);
            // tell Doctrine you want to (eventually) save the employee (no queries yet)
            $entityManager->persist($employee);

            // actually executes the queries (i.e. the INSERT query)
            $entityManager->flush();
            
            // Render the form view
            return new JsonResponse(["status" => "Success"]);
        } catch (Exception $e) {
            // Log the error
            $logger->error('An error occurred: ' . $e->getMessage());
            // Optionally, you can log the stack trace as well
            $logger->error('Stack trace: ' . $e->getTraceAsString());
        }
        return new JsonResponse(["status" => "Failed"]);
    }
}
