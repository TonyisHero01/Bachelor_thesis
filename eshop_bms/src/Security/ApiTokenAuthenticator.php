<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ApiToken;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiTokenRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Determines whether the authenticator should handle the current request.
     */
    public function supports(Request $request): ?bool
    {
        $auth = $request->headers->get('Authorization') ?? $request->headers->get('X-API-Token');

        $this->logger->warning('API AUTH SUPPORTS CALLED', [
            'path_info' => $request->getPathInfo(),
            'authorization' => $auth,
        ]);

        return is_string($auth) && $auth !== '';
    }

    /**
     * Authenticates a request using an API token in the format "publicId.secret".
     */
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $raw = $request->headers->get('Authorization') ?? $request->headers->get('X-API-Token');

        if (!is_string($raw) || $raw === '') {
            throw new AuthenticationException('Missing API token');
        }

        $raw = (string) preg_replace('/^Bearer\s+/i', '', $raw);

        if (!str_contains($raw, '.')) {
            throw new AuthenticationException('Invalid token format');
        }

        [$publicId, $secret] = explode('.', $raw, 2);

        /** @var ApiToken|null $token */
        $token = $this->repo->findOneBy([
            'publicId' => $publicId,
            'active' => true,
        ]);

        if ($token === null) {
            throw new AuthenticationException('Token not found or inactive');
        }

        if (!password_verify($secret, (string) $token->getSecretHash())) {
            throw new AuthenticationException('Invalid token secret');
        }

        $token->setLastUsedAt(new \DateTimeImmutable());
        $this->em->flush();

        $roles = $this->buildRolesFromScopes($token->getScopes() ?? []);

        return new SelfValidatingPassport(
            new UserBadge(
                $publicId,
                fn (): ApiTokenUser => new ApiTokenUser($publicId, $roles)
            )
        );
    }

    /**
     * Called when authentication succeeds; returning null continues the request.
     */
    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        return null;
    }

    /**
     * Called when authentication fails and returns a JSON 401 response.
     */
    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): ?Response {
        return new JsonResponse(
            ['status' => 'error', 'message' => 'Unauthorized'],
            Response::HTTP_UNAUTHORIZED
        );
    }

    /**
     * Converts API scopes (e.g. "categories.write") into Symfony roles (e.g. "ROLE_CATEGORIES_WRITE").
     *
     * @param array<int, mixed> $scopes
     *
     * @return list<string>
     */
    private function buildRolesFromScopes(array $scopes): array
    {
        $roles = [];

        foreach ($scopes as $scope) {
            $scope = trim((string) $scope);
            if ($scope === '') {
                continue;
            }

            $roles[] = 'ROLE_' . strtoupper(str_replace('.', '_', $scope));
        }

        return array_values(array_unique($roles));
    }
}