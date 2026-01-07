<?php

namespace App\Controller;

use App\Entity\AdminCode;
use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class SuperAdminController extends BaseController
{
    /**
     * Displays the main page for super admin.
     */
    #[Route('/super/admin', name: 'app_super_admin', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderLocalized('super_admin/index.html.twig', [
            'controller_name' => 'SuperAdminController',
        ]);
    }

    /**
     * Shows a list of all admin users.
     */
    #[Route('/admin_list', name: 'show_All_admins', methods: ['GET'])]
    public function showAllAdmins(EntityManagerInterface $em): Response
    {
        $admins = $em->getRepository(Employee::class)->findAllWithRoleAdmin();

        return $this->renderLocalized('super_admin/admin_list.html.twig', [
            'employees' => $admins,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
        ]);
    }

    /**
     * Shows the admin edit form for the given admin ID.
     */
    #[Route('/admin_edit/{id}', name: 'admin_edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(EntityManagerInterface $em, int $id): Response
    {
        $admin = $em->getRepository(Employee::class)->find($id);
        if ($admin === null) {
            throw $this->createNotFoundException('Admin not found');
        }

        return $this->renderLocalized('super_admin/admin_edit.html.twig', [
            'admin' => $admin,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
        ]);
    }

    /**
     * Saves changes to an admin profile.
     */
    #[Route('/admin_save/{id}', name: 'save_admin', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveAdmin(
        Request $request,
        EntityManagerInterface $em,
        int $id,
        LoggerInterface $logger
    ): JsonResponse {
        try {
            $admin = $em->getRepository(Employee::class)->find($id);
            if ($admin === null) {
                return new JsonResponse(['status' => 'Error', 'message' => 'Admin not found'], 404);
            }

            $data = json_decode((string) $request->getContent(), true);
            if (!is_array($data)) {
                return new JsonResponse(['status' => 'Error', 'message' => 'Invalid JSON'], 400);
            }

            $surname = isset($data['surname']) ? trim((string) $data['surname']) : '';
            $name = isset($data['name']) ? trim((string) $data['name']) : '';
            $email = isset($data['email']) ? trim((string) $data['email']) : '';
            $phoneNumber = isset($data['phoneNumber']) ? trim((string) $data['phoneNumber']) : '';

            if ($surname === '' || $name === '' || $email === '') {
                return new JsonResponse(['status' => 'Error', 'message' => 'Missing required fields'], 400);
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return new JsonResponse(['status' => 'Error', 'message' => 'Invalid email'], 400);
            }

            $admin->setSurname($surname);
            $admin->setName($name);
            $admin->setEmail($email);

            if ($phoneNumber !== '' && method_exists($admin, 'setPhoneNumber')) {
                $admin->setPhoneNumber($phoneNumber);
            }

            if (array_key_exists('roles', $data)) {
                $roles = $data['roles'];

                if (!is_array($roles)) {
                    return new JsonResponse(['status' => 'Error', 'message' => 'Roles must be an array'], 400);
                }

                $allowedRoles = [
                    'ROLE_SUPER_ADMIN',
                    'ROLE_ADMIN',
                    'ROLE_WAREHOUSEMAN',
                    'ROLE_WAREHOUSE_MANAGER',
                    'ROLE_TRANSLATOR',
                    'ROLE_EVENT_MANAGER',
                    'ROLE_ACCOUNTING',
                ];

                $roles = array_values(array_unique(array_filter(
                    array_map('strval', $roles),
                    static fn (string $r): bool => in_array($r, $allowedRoles, true)
                )));

                if ($roles === []) {
                    return new JsonResponse(['status' => 'Error', 'message' => 'No valid roles provided'], 400);
                }

                if (!in_array('ROLE_ADMIN', $roles, true) && !in_array('ROLE_SUPER_ADMIN', $roles, true)) {
                    return new JsonResponse(['status' => 'Error', 'message' => 'Admin must keep ROLE_ADMIN or ROLE_SUPER_ADMIN'], 400);
                }

                $admin->setRoles($roles);
            }

            $em->flush();

            return new JsonResponse(['status' => 'Success']);
        } catch (\Throwable $e) {
            $logger->error('Error in saveAdmin: ' . $e->getMessage(), [
                'exception' => $e,
                'admin_id' => $id,
            ]);

            return new JsonResponse(['status' => 'Error', 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Generates a one-time admin code and returns it (plaintext) to the super admin.
     */
    #[Route('/admin/get-code/api', name: 'admin_get_code_api', methods: ['POST'])]
    public function getCodeApi(EntityManagerInterface $em): JsonResponse
    {
        $plainCode = strtoupper(bin2hex(random_bytes(3)));

        $adminCode = new AdminCode();
        $adminCode->setCodeHash(password_hash($plainCode, PASSWORD_BCRYPT));
        $adminCode->setCreatedAt(new \DateTimeImmutable());

        $em->persist($adminCode);
        $em->flush();

        return new JsonResponse(['code' => $plainCode]);
    }
}