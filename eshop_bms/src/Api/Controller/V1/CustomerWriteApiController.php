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
use Throwable;

#[IsGranted('ROLE_CUSTOMERS_WRITE')]
#[Route('/api/v1/customers', name: 'api_v1_customers_write_')]
class CustomerWriteApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustomerRepository $customerRepository,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /**
     * Creates a new customer.
     *
     * Expected body:
     * - email: string (required)
     * - password: string (required)
     * - created_at: string (optional, ISO8601 or "Y-m-d H:i:s")
     * - is_verified: bool (optional, default false)
     * - wishlist: int[] (optional)
     * - reset_token: string|null (optional)
     * - reset_token_expiration: string|null (optional, ISO8601 or "Y-m-d H:i:s")
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->getJson($request);

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        if ($email === '') {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Email is required',
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

        $password = isset($payload['password']) ? (string) $payload['password'] : '';
        if (trim($password) === '') {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => 'Password is required',
                ],
                400
            );
        }

        $customer = new Customer();
        $customer->setEmail($email);
        $customer->setPasswordHash($this->hasher->hashPassword($customer, $password));

        $createdAtError = $this->applyCreatedAt($customer, $payload, true);
        if ($createdAtError !== null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => $createdAtError,
                ],
                400
            );
        }

        $this->applyIsVerified($customer, $payload);

        $wishlistError = $this->applyWishlist($customer, $payload);
        if ($wishlistError !== null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => $wishlistError,
                ],
                400
            );
        }

        $customer->setResetToken(
            array_key_exists('reset_token', $payload)
                ? ($payload['reset_token'] === null ? null : trim((string) $payload['reset_token']))
                : null
        );

        $resetExpirationError = $this->applyResetTokenExpiration($customer, $payload);
        if ($resetExpirationError !== null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => $resetExpirationError,
                ],
                400
            );
        }

        $this->em->persist($customer);
        $this->em->flush();

        return $this->json(
            [
                'status' => 'success',
                'data' => [
                    'id' => $customer->getId(),
                ],
            ],
            201
        );
    }

    /**
     * Updates an existing customer by identifier.
     *
     * Allowed partial body:
     * - email: string (non-empty)
     * - password: string (non-empty)
     * - created_at: string|null (ISO8601 or "Y-m-d H:i:s")
     * - is_verified: bool
     * - wishlist: int[]|null
     * - reset_token: string|null
     * - reset_token_expiration: string|null (ISO8601 or "Y-m-d H:i:s")
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $payload = $this->getJson($request);

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

        $emailError = $this->applyEmail($customer, $payload);
        if ($emailError !== null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => $emailError,
                ],
                str_contains($emailError, 'already registered') ? 409 : 400
            );
        }

        $passwordError = $this->applyPassword($customer, $payload);
        if ($passwordError !== null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => $passwordError,
                ],
                400
            );
        }

        $createdAtError = $this->applyCreatedAt($customer, $payload, false);
        if ($createdAtError !== null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => $createdAtError,
                ],
                400
            );
        }

        $this->applyIsVerified($customer, $payload);

        $wishlistError = $this->applyWishlist($customer, $payload);
        if ($wishlistError !== null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => $wishlistError,
                ],
                400
            );
        }

        if (array_key_exists('reset_token', $payload)) {
            $token = $payload['reset_token'];
            $customer->setResetToken($token === null ? null : trim((string) $token));
        }

        $resetExpirationError = $this->applyResetTokenExpiration($customer, $payload);
        if ($resetExpirationError !== null) {
            return $this->json(
                [
                    'status' => 'error',
                    'message' => $resetExpirationError,
                ],
                400
            );
        }

        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $customer->getId(),
            ],
        ]);
    }

    /**
     * Deletes a customer by identifier.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
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

        $this->em->remove($customer);
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => [
                'deletedId' => $id,
            ],
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

    /**
     * Applies email update rules (non-empty, must be unique).
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyEmail(Customer $customer, array $payload): ?string
    {
        if (!array_key_exists('email', $payload)) {
            return null;
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email === '') {
            return 'Email cannot be empty';
        }

        $existing = $this->customerRepository->findOneBy(['email' => $email]);
        if ($existing !== null && $existing->getId() !== $customer->getId()) {
            return 'Email already registered';
        }

        $customer->setEmail($email);

        return null;
    }

    /**
     * Applies password update rules (non-empty).
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyPassword(Customer $customer, array $payload): ?string
    {
        if (!array_key_exists('password', $payload)) {
            return null;
        }

        $password = (string) ($payload['password'] ?? '');
        if (trim($password) === '') {
            return 'Password cannot be empty';
        }

        $customer->setPasswordHash($this->hasher->hashPassword($customer, $password));

        return null;
    }

    /**
     * Applies created_at value from payload.
     *
     * Rules:
     * - create: if not provided, defaults to now
     * - update: if provided as null/empty, sets to null
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyCreatedAt(Customer $customer, array $payload, bool $isCreate): ?string
    {
        if (!array_key_exists('created_at', $payload)) {
            if ($isCreate) {
                $customer->setCreatedAt(new DateTimeImmutable());
            }

            return null;
        }

        $raw = $payload['created_at'];

        if ($raw === null || $raw === '') {
            if ($isCreate) {
                $customer->setCreatedAt(new DateTimeImmutable());
            } else {
                $customer->setCreatedAt(null);
            }

            return null;
        }

        $dt = $this->parseDateTimeImmutable((string) $raw);
        if ($dt === null) {
            return 'Invalid created_at. Use ISO8601 or "Y-m-d H:i:s".';
        }

        $customer->setCreatedAt($dt);

        return null;
    }

    /**
     * Applies is_verified flag from payload (defaults to false on create).
     *
     * @param array<string, mixed> $payload
     */
    private function applyIsVerified(Customer $customer, array $payload): void
    {
        if (array_key_exists('is_verified', $payload)) {
            $customer->setIsVerified((bool) $payload['is_verified']);
            return;
        }

        if ($customer->isIsVerified() === null) {
            $customer->setIsVerified(false);
        }
    }

    /**
     * Applies wishlist from payload.
     *
     * Rules:
     * - wishlist must be an array of integers, null clears it, and omission keeps it unchanged
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyWishlist(Customer $customer, array $payload): ?string
    {
        if (!array_key_exists('wishlist', $payload)) {
            return null;
        }

        if ($payload['wishlist'] === null) {
            $customer->setWishlist([]);
            return null;
        }

        if (!is_array($payload['wishlist'])) {
            return 'wishlist must be an array of integers';
        }

        $customer->setWishlist($this->sanitizeWishlist($payload['wishlist']));

        return null;
    }

    /**
     * Applies reset_token_expiration from payload.
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyResetTokenExpiration(Customer $customer, array $payload): ?string
    {
        if (!array_key_exists('reset_token_expiration', $payload)) {
            return null;
        }

        $raw = $payload['reset_token_expiration'];

        if ($raw === null || $raw === '') {
            $customer->setResetTokenExpiration(null);
            return null;
        }

        $dt = $this->parseDateTimeImmutable((string) $raw);
        if ($dt === null) {
            return 'Invalid reset_token_expiration. Use ISO8601 or "Y-m-d H:i:s".';
        }

        $customer->setResetTokenExpiration($dt);

        return null;
    }

    /**
     * Parses a datetime string using ISO8601/RFC3339 first, then "Y-m-d H:i:s".
     */
    private function parseDateTimeImmutable(string $raw): ?DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (Throwable) {
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw);

        return $dt instanceof DateTimeImmutable ? $dt : null;
    }

    /**
     * Normalizes wishlist values to a unique list of integers.
     *
     * @param array<int, mixed> $wishlist
     *
     * @return int[]
     */
    private function sanitizeWishlist(array $wishlist): array
    {
        $out = [];

        foreach ($wishlist as $value) {
            if (is_int($value)) {
                $out[] = $value;
                continue;
            }

            if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
                $out[] = (int) $value;
            }
        }

        return array_values(array_unique($out));
    }
}