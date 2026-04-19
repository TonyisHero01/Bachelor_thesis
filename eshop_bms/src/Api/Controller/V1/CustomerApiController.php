<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CUSTOMERS_READ')]
#[Route('/api/v1/customers', name: 'api_v1_customers_')]
class CustomerApiController extends AbstractController
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Returns a list of all customers (intended for back-office use).
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $customers = $this->customerRepository->findAll();

        $data = array_map(
            static function (Customer $customer): array {
                return [
                    'id' => $customer->getId(),
                    'email' => $customer->getEmail(),
                    'created_at' => $customer->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'is_verified' => $customer->isIsVerified(),
                    'wishlist' => $customer->getWishlist(),
                ];
            },
            $customers
        );

        return $this->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Returns a single customer detail by identifier.
     */
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $customer = $this->customerRepository->find($id);

        if ($customer === null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Customer not found',
                ],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'created_at' => $customer->getCreatedAt()?->format('Y-m-d H:i:s'),
                'is_verified' => $customer->isIsVerified(),
                'wishlist' => $customer->getWishlist(),
            ],
        ]);
    }

    /**
     * Registers a new customer.
     *
     * Expected body:
     * - email: string
     * - password: string
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $payload = $this->getJson($request);

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';

        if ($email === '' || $password === '') {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Email and password are required',
                ],
                400
            );
        }

        if ($this->customerRepository->findOneBy(['email' => $email]) !== null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Email already registered',
                ],
                409
            );
        }

        $customer = new Customer();
        $customer->setEmail($email);
        $customer->setPasswordHash($hasher->hashPassword($customer, $password));
        $customer->setCreatedAt(new DateTimeImmutable());
        $customer->setIsVerified(false);

        $this->em->persist($customer);
        $this->em->flush();

        return $this->json(
            [
                'status' => 'success',
                'data' => [
                    'id' => $customer->getId(),
                    'email' => $customer->getEmail(),
                ],
            ],
            201
        );
    }

    /**
     * Returns a customer's wishlist by customer identifier.
     */
    #[Route('/{id}/wishlist', name: 'wishlist', methods: ['GET'])]
    public function wishlist(int $id): JsonResponse
    {
        $customer = $this->customerRepository->find($id);
        if ($customer === null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Customer not found',
                ],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => $customer->getWishlist(),
        ]);
    }

    /**
     * Adds or removes a product from a customer's wishlist.
     *
     * Expected body:
     * - product_id: int
     * - action: "add" or "remove" (default: "add")
     */
    #[Route('/{id}/wishlist', name: 'wishlist_update', methods: ['POST'])]
    public function updateWishlist(int $id, Request $request): JsonResponse
    {
        $customer = $this->customerRepository->find($id);
        if ($customer === null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Customer not found',
                ],
                404
            );
        }

        $payload = $this->getJson($request);

        $productId = $payload['product_id'] ?? null;
        $action = isset($payload['action']) ? (string) $payload['action'] : 'add';

        if (!is_int($productId) && !(is_string($productId) && ctype_digit($productId))) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Invalid parameters',
                ],
                400
            );
        }

        if (!in_array($action, ['add', 'remove'], true)) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Invalid parameters',
                ],
                400
            );
        }

        $productIdInt = (int) $productId;

        if ($action === 'add') {
            $customer->addToWishlist($productIdInt);
        } else {
            $customer->removeFromWishlist($productIdInt);
        }

        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => $customer->getWishlist(),
        ]);
    }

    /**
     * Decodes JSON request body into an associative array.
     *
     * @return array<string, mixed>
     */
    private function getJson(Request $request): array
    {
        $raw = (string) $request->getContent();
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}