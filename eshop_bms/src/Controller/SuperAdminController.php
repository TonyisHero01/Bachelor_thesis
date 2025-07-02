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
use Twig\Environment;
use Psr\Log\LoggerInterface;
use App\Entity\AdminCode;

class SuperAdminController extends BaseController
{
    private $params;

    public function __construct(
        ParameterBagInterface $params,
        Environment $twig,
        LoggerInterface $logger
    ) {
        parent::__construct($twig, $logger);
        $this->params = $params;
    }

    #[Route('/super/admin', name: 'app_super_admin')]
    /**
     * Displays the main page for super admin.
     *
     * @return Response The rendered super admin index page.
     */
    public function index(): Response
    {
        return $this->renderLocalized('super_admin/index.html.twig', [
            'controller_name' => 'SuperAdminController',
        ]);
    }

    #[Route('/admin_list', name: 'show_All_admins')]
    /**
     * Shows a list of all admin users.
     *
     * @param EntityManagerInterface $entityManager Used to access the Employee repository.
     * @param AuthorizationCheckerInterface $authorizationChecker Checks user authentication.
     *
     * @return Response The rendered admin list page, or a not-logged-in page if unauthorized.
     */
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
    /**
     * Shows the admin edit form for the given admin ID.
     *
     * @param EntityManagerInterface $entityManager Used to fetch the admin entity.
     * @param int $id The ID of the admin to edit.
     * @param Request $request The current request object.
     * @param AuthorizationCheckerInterface $authorizationChecker Checks user authentication.
     *
     * @return Response The rendered edit form or not-logged-in page.
     */
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
    /**
     * Saves changes to an admin's profile.
     *
     * @param Request $request The HTTP request containing JSON data.
     * @param EntityManagerInterface $entityManager Used to persist admin entity changes.
     * @param int $id The ID of the admin being updated.
     * @param AuthorizationCheckerInterface $authorizationChecker Checks if user is authenticated.
     *
     * @return Response A JSON response indicating success or an error page if not logged in.
     */
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

    #[Route('/admin/get-code/api', name: 'admin_get_code_api')]
    /**
     * Generates a one-time admin code and returns it in plain text for secure operations.
     *
     * Access restricted to users with ROLE_SUPER_ADMIN.
     * The generated code is:
     * - 6 hexadecimal uppercase characters (e.g., "F3A9BC")
     * - Hashed using BCRYPT before storage
     * - Stored in the `admin_code` table with a timestamp
     *
     * The plain code is returned only once in the API response and never persisted in plain text.
     * Can be used as a temporary verification token (e.g., for executing untrusted code).
     *
     * @param EntityManagerInterface $em Used to persist the generated AdminCode entity.
     * @return JsonResponse Contains the one-time plaintext code for display to the super admin.
     */
    public function getCodeApi(EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $plainCode = strtoupper(bin2hex(random_bytes(3)));

        $hashedCode = password_hash($plainCode, PASSWORD_BCRYPT);

        $adminCode = new AdminCode();
        $adminCode->setCodeHash($hashedCode);
        $adminCode->setCreatedAt(new \DateTimeImmutable());

        $em->persist($adminCode);
        $em->flush();

        return new JsonResponse(['code' => $plainCode]);
    }
}
