<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/symfony/api/auth/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Parse JSON body
        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse([
                'error' => 'Invalid JSON',
            ], Response::HTTP_BAD_REQUEST);
        }

        $login = $data['login'] ?? '';
        $password = $data['password'] ?? '';

        if (! $login || ! $password) {
            return new JsonResponse([
                'error' => 'Missing login or password',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Find user
        $user = $this->userRepository->findOneBy([
            'login' => $login,
        ]);
        if (! $user) {
            return new JsonResponse([
                'error' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verify password using Symfony hasher (compatible with legacy password_verify)
        if (! $this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse([
                'error' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Generate JWT using existing JwtService
        $userData = $this->jwtService->extractUserData($user);
        $token = $this->jwtService->generateJwtToken($userData);

        // Store token (for cross-app communication if needed)
        $this->jwtService->storeToken($token, $user->getId());

        return new JsonResponse([
            'token' => $token,
            'user'  => [
                'id'    => $user->getId(),
                'login' => $user->getLogin(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[Route('/symfony/api/auth/logout', name: 'api_logout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function logout(#[CurrentUser] User $user): JsonResponse
    {
        // Delete stored token
        $this->jwtService->deleteToken($user->getId());

        return new JsonResponse([
            'message' => 'Logged out successfully',
        ]);
    }

    #[Route('/symfony/api/auth/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse([
            'id'    => $user->getId(),
            'login' => $user->getLogin(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'name'  => $user->getName(),
        ]);
    }
}
