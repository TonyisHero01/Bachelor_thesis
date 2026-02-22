<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Color;
use App\Entity\Size;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

#[IsGranted('ROLE_WAREHOUSE_MANAGER')]
final class CatalogDictionaryController extends BaseController
{
    public function __construct(
        Environment $twig,
        LoggerInterface $logger,
        ManagerRegistry $doctrine,
    ) {
        parent::__construct($twig, $logger, $doctrine);
    }

    /**
     * Creates a new category.
     */
    #[Route('/bms/save_category', name: 'save_category', methods: ['POST'])]
    public function createCategory(
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
    ): Response {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data) || empty($data['name'])) {
            return new JsonResponse(['error' => 'Invalid JSON or missing name'], 400);
        }

        $name = trim((string) $data['name']);
        if ($name === '') {
            return new JsonResponse(['error' => 'Category name cannot be empty'], 400);
        }

        $category = (new Category())->setName($name);

        $em->persist($category);
        $em->flush();

        $this->notifyReindex($httpClient, $logger, 'category_create', [
            'categoryId' => $category->getId(),
            'name' => $name,
        ]);

        return new JsonResponse([]);
    }

    /**
     * Modifies the category name and triggers TF-IDF retraining.
     */
    #[Route('/bms/modify_category', name: 'modify_category', methods: ['POST'])]
    public function modifyCategory(
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
    ): JsonResponse {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $id = $data['id'] ?? null;
        $newName = $data['new_name'] ?? null;

        if ($id === null || $newName === null || trim((string) $newName) === '') {
            return new JsonResponse(['success' => false, 'message' => 'Missing ID or new name'], 400);
        }

        $category = $em->getRepository(Category::class)->find((int) $id);
        if ($category === null) {
            return new JsonResponse(['success' => false, 'message' => 'Category not found'], 404);
        }

        $oldName = (string) $category->getName();
        $category->setName((string) $newName);
        $em->flush();

        $this->notifyReindex($httpClient, $logger, 'category_modify', [
            'categoryId' => (int) $id,
            'oldName' => $oldName,
            'newName' => (string) $newName,
        ]);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Creates a new color.
     */
    #[Route('/bms/save_color', name: 'save_color', methods: ['POST'])]
    public function createColor(Request $request, EntityManagerInterface $em): Response
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data) || empty($data['name']) || empty($data['hex'])) {
            return new JsonResponse(['error' => 'Invalid JSON or missing fields'], 400);
        }

        $color = (new Color())
            ->setName((string) $data['name'])
            ->setHex((string) $data['hex']);

        $em->persist($color);
        $em->flush();

        return new JsonResponse([]);
    }

    /**
     * Modifies an existing color.
     */
    #[Route('/bms/modify_color', name: 'modify_color', methods: ['POST'])]
    public function modifyColor(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid JSON'], 400);
        }

        $colorId = $data['id'] ?? null;
        $newName = $data['new_name'] ?? null;
        $newHex = $data['new_hex'] ?? null;

        if ($colorId === null || $newName === null || $newHex === null) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid data'], 400);
        }

        $color = $em->getRepository(Color::class)->find((int) $colorId);
        if ($color === null) {
            return new JsonResponse(['status' => 'error', 'message' => 'Color not found'], 404);
        }

        $color->setName((string) $newName);
        $color->setHex((string) $newHex);
        $em->flush();

        return new JsonResponse(['status' => 'success']);
    }

    /**
     * Creates a new size.
     */
    #[Route('/bms/create_size', name: 'size_create', methods: ['POST'])]
    public function createSize(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON.'], 400);
        }

        $sizeName = trim((string) ($data['name'] ?? ''));
        if ($sizeName === '') {
            return new JsonResponse(['success' => false, 'message' => 'Size name cannot be empty.'], 400);
        }

        $size = (new Size())->setName($sizeName);

        $em->persist($size);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Modifies an existing size name.
     */
    #[Route('/bms/modify_size/{id}', name: 'size_modify', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function modifySize(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON.'], 400);
        }

        $newSizeName = trim((string) ($data['name'] ?? ''));
        if ($newSizeName === '') {
            return new JsonResponse(['success' => false, 'message' => 'New size name cannot be empty.'], 400);
        }

        $size = $em->getRepository(Size::class)->find($id);
        if ($size === null) {
            return new JsonResponse(['success' => false, 'message' => 'Size not found.'], 404);
        }

        $size->setName($newSizeName);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Notify python-api to rebuild TF-IDF vectors.
     * Failure should NOT break main business flow.
     */
    private function notifyReindex(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $reason,
        array $context = []
    ): void {
        $baseUrl = (string) $this->getParameter('python_api_base_url');
        $baseUrl = rtrim($baseUrl, '/');

        if ($baseUrl === '') {
            $logger->warning('[TFIDF] python_api_base_url is empty, skip reindex', [
                'reason' => $reason,
                'context' => $context,
            ]);
            return;
        }

        try {
            $resp = $httpClient->request('POST', $baseUrl . '/reindex', [
                'json' => ['mode' => 'full'],
                'timeout' => 10,
            ]);

            $status = $resp->getStatusCode();
            $body = $resp->toArray(false);

            if ($status < 200 || $status >= 300) {
                $logger->error('[TFIDF] reindex non-2xx', [
                    'status' => $status,
                    'body' => $body,
                    'reason' => $reason,
                    'context' => $context,
                ]);
                return;
            }

            $logger->info('[TFIDF] reindex ok', [
                'status' => $status,
                'body' => $body,
                'reason' => $reason,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            $logger->error('[TFIDF] reindex request failed', [
                'msg' => $e->getMessage(),
                'reason' => $reason,
                'context' => $context,
            ]);
        }
    }
}