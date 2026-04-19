<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Color;
use App\Entity\ColorTranslation;
use App\Repository\ColorRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_COLORS_WRITE')]
#[Route('/api/v1/colors', name: 'api_v1_colors_write_')]
class ColorWriteApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ColorRepository $colorRepository,
    ) {
    }

    /**
     * Creates a new color with optional HEX and required name/translation.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->getJson($request);

        $color = new Color();

        $hexError = $this->applyHex($color, $payload, true);
        if ($hexError !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $hexError],
                400
            );
        }

        $nameError = $this->applyColorNames($color, $payload, true);
        if ($nameError !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $nameError],
                400
            );
        }

        $this->em->persist($color);
        $this->em->flush();

        return $this->json(
            [
                'status' => 'success',
                'data' => ['id' => $color->getId()],
            ],
            201
        );
    }

    /**
     * Updates an existing color by identifier.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $payload = $this->getJson($request);

        $color = $this->colorRepository->find($id);
        if ($color === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Color not found'],
                404
            );
        }

        $hexError = $this->applyHex($color, $payload, false);
        if ($hexError !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $hexError],
                400
            );
        }

        $nameError = $this->applyColorNames($color, $payload, false);
        if ($nameError !== null) {
            return $this->json(
                ['status' => 'error', 'message' => $nameError],
                400
            );
        }

        $this->em->flush();

        return $this->json([
            'status' => 'success',
            'data' => ['id' => $color->getId()],
        ]);
    }

    /**
     * Deletes a color by identifier.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $color = $this->colorRepository->find($id);
        if ($color === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Color not found'],
                404
            );
        }

        $this->em->remove($color);
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
     * Applies HEX value from payload.
     *
     * Rules:
     * - create: HEX is optional
     * - update: if "hex" is present, it must be non-empty (entity is non-nullable)
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyHex(Color $color, array $payload, bool $isCreate): ?string
    {
        if (!array_key_exists('hex', $payload)) {
            return null;
        }

        $hex = $payload['hex'];

        if ($hex === null || $hex === '') {
            return $isCreate ? null : 'Hex cannot be empty';
        }

        try {
            $color->setHex((string) $hex);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Applies color names and translations from payload.
     *
     * Supported payload formats:
     * - translations: { "en": "Red", "cz": "Červená" }
     * - name + locale: "Red" + "en"
     *
     * Rules:
     * - create: at least one non-empty name is required
     * - update: name fields are optional
     *
     * @param array<string, mixed> $payload
     *
     * @return string|null Error message or null on success
     */
    private function applyColorNames(Color $color, array $payload, bool $isCreate): ?string
    {
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
                return $isCreate ? 'translations cannot be empty' : null;
            }

            foreach ($translations as $locale => $name) {
                $this->upsertTranslation($color, $locale, $name);
            }

            if (isset($translations['en'])) {
                $color->setName($translations['en']);
            } else {
                $first = reset($translations);
                if (is_string($first) && $first !== '') {
                    $color->setName($first);
                }
            }

            return null;
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '') {
                return $isCreate ? 'name cannot be empty' : null;
            }

            $locale = strtolower(trim((string) ($payload['locale'] ?? 'en')));
            if ($locale === '') {
                $locale = 'en';
            }

            $this->upsertTranslation($color, $locale, $name);
            $color->setName($name);

            return null;
        }

        return $isCreate ? 'Missing color name. Provide "translations" or "name" + "locale".' : null;
    }

    /**
     * Inserts or updates a single color translation.
     */
    private function upsertTranslation(Color $color, string $locale, string $name): void
    {
        foreach ($color->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                $translation->setName($name);
                return;
            }
        }

        $translation = new ColorTranslation();
        $translation->setLocale($locale);
        $translation->setName($name);
        $translation->setColor($color);

        $color->addTranslation($translation);
        $this->em->persist($translation);
    }
}