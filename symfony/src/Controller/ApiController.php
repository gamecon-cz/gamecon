<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LegacySessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends AbstractController
{
    public function __construct(
        private readonly LegacySessionService $legacySession,
    ) {
    }

    public function handle(string $endpoint): Response
    {
        if (! $this->legacySession->hasAdminAccess()) {
            return new JsonResponse([
                'error' => 'Unauthorized',
            ], 403);
        }

        // Handle specific API endpoints in Symfony
        return match ($endpoint) {
            'test' => new JsonResponse([
                'message'  => 'Symfony API working',
                'endpoint' => $endpoint,
            ]),
            // For now, return not found - later we'll delegate to legacy
            default => new JsonResponse([
                'error' => 'API endpoint not found in Symfony',
            ], 404),
        };
    }
}
