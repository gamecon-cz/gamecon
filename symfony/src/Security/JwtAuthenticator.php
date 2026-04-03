<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Support requests with Authorization: Bearer {token}
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        // Extract token from header
        $authorizationHeader = $request->headers->get('Authorization');
        if (! $authorizationHeader || ! str_starts_with($authorizationHeader, 'Bearer ')) {
            throw new AuthenticationException('Missing or invalid Authorization header');
        }

        $token = substr($authorizationHeader, 7);

        // Decode using existing JwtService
        $payload = $this->jwtService->decodeJwtToken($token);
        if (! $payload) {
            throw new AuthenticationException('Invalid JWT token');
        }

        // Load user from database (Firebase JWT returns stdClass, not array)
        $userData = $payload['user'] ?? null;
        $userId = is_array($userData) ? ($userData['id'] ?? null) : ($userData->id ?? null);
        if (! $userId) {
            throw new AuthenticationException('JWT token missing user ID');
        }

        $user = $this->userRepository->find($userId);

        if (! $user) {
            throw new UserNotFoundException(sprintf('User with ID %d not found', $userId));
        }

        // Return passport with user badge
        return new SelfValidatingPassport(
            new UserBadge($user->getLogin(), fn () => $user)
        );
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
            $passport->getUser(),
            $firewallName,
            $passport->getUser()->getRoles()
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Allow request to continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error'   => 'Authentication failed',
            'message' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
