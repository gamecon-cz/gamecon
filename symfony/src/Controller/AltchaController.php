<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LegacySessionService;
use Gamecon\Antibot\Altcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vydává podepsané ALTCHA výzvy pro widget na veřejných formulářích
 * (obnova hesla, registrace, přihlášení). Jeden endpoint pro všechny formuláře
 * bez ohledu na to, jestli je renderuje Symfony nebo legacy modul.
 */
class AltchaController extends AbstractController
{
    public function __construct(
        private readonly LegacySessionService $legacySession,
    ) {
    }

    #[Route('/altcha-challenge', name: 'altcha_challenge', methods: ['GET'])]
    public function challenge(): Response
    {
        $this->legacySession->initializeLegacyEnvironment();

        // Widget čeká čistý JSON výzvy; cache vypneme, ať každý request dostane
        // čerstvou (jednorázovou) výzvu.
        return new JsonResponse(
            Altcha::zGlobals()->challengeJson(),
            Response::HTTP_OK,
            [
                'Cache-Control' => 'no-store',
            ],
            true,
        );
    }
}
