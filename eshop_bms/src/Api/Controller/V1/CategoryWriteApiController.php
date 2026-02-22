<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Category;
use App\Entity\CategoryTranslation;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CATEGORIES_WRITE')]
#[Route('/api/v1/categories', name: 'api_v1_categories_write_')]
class CategoryWriteApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * Returns the authenticated user and assigned roles.
     */
    #[Route('/_debug/auth', methods: ['GET'])]
    public function debugAuth(): JsonResponse
    {
        return $this->json([
            'user' => $this->getUser(),
            'roles' => $this->getUser()?->getRoles(),
        ]);
    }

    /**
     * Creates a new category with translations.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->getJson($request);

        $category = new Category();

        $error = $this->applyCategoryName($category, $payload, true);
        if ($error !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $error],
                400
            );
        }

        $this->em->persist($category);
        $this->em->flush();

        return $this->json(
            [
                'status' => 'success',
                'data' => ['id' => $category->getId()],
            ],
            201
        );
    }

    /**
     * Updates an existing category and its translations.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $payload = $this->getJson($request);

        $category = $this->categoryRepository->find($id);
        if ($category === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Category not found'],
                404
            );
        }

        $error = $this->applyCategoryName($category, $payload, false);
        if ($error !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $error],
                400
            );
        }

        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['id' => $category->getId()],
        ]);
    }

    /**
     * Deletes a category by identifier.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if ($category === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Category not found'],
                404
            );
        }

        $this->em->remove($category);
        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['deletedId' => $id],
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
     * Applies category name and translations from request payload.
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyCategoryName(
        Category $category,
        array $payload,
        bool $isCreate
    ): ?string {
        if (isset($payload['translations']) && is_array($payload['translations'])) {
            $translations = [];

            foreach ($payload['translations'] as $locale => $value) {
                $locale = strtolower(trim((string) $locale));
                $value = trim((string) $value);

                if ($locale !== '' && $value !== '') {
                    $translations[$locale] = $value;
                }
            }

            if ($translations === []) {
                return 'translations cannot be empty';
            }

            foreach ($translations as $locale => $name) {
                $this->upsertTranslation($category, $locale, $name);
            }

            if (isset($translations['en'])) {
                $category->setName($translations['en']);
            } else {
                $first = reset($translations);
                if (is_string($first) && $first !== '') {
                    $category->setName($first);
                }
            }

            return null;
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '') {
                return 'name cannot be empty';
            }

            $locale = strtolower(trim((string) ($payload['locale'] ?? 'en')));
            if ($locale === '') {
                $locale = 'en';
            }

            $this->upsertTranslation($category, $locale, $name);
            $category->setName($name);

            return null;
        }

        if ($isCreate) {
            return 'Either "translations" or "name" is required';
        }

        return null;
    }

    /**
     * Inserts or updates a single category translation.
     */
    private function upsertTranslation(
        Category $category,
        string $locale,
        string $name
    ): void {
        foreach ($category->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                $translation->setName($name);
                return;
            }
        }

        $translation = new CategoryTranslation();
        $translation->setLocale($locale);
        $translation->setName($name);

        $category->addTranslation($translation);
    }
}