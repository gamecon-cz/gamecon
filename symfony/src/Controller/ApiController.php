<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LegacySessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends AbstractController
{
    private LegacySessionService $legacySession;

    public function __construct(LegacySessionService $legacySession)
    {
        $this->legacySession = $legacySession;
    }

    public function handle(string $endpoint): Response
    {
        if (! $this->legacySession->hasAdminAccess()) {
            return new JsonResponse([
                'error' => 'Unauthorized',
            ], 403);
        }

        // Handle specific API endpoints in Symfony
        switch ($endpoint) {
            case 'test':
                return new JsonResponse([
                    'message' => 'Symfony API working',
                    'endpoint' => $endpoint,
                ]);

            default:
                // For now, return not found - later we'll delegate to legacy
                return new JsonResponse([
                    'error' => 'API endpoint not found in Symfony',
                ], 404);
        }
    }
}
