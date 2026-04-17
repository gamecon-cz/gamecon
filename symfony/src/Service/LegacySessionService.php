<?php

declare(strict_types=1);

namespace App\Service;

use Gamecon\Pravo;

class LegacySessionService
{
    public function initializeLegacyEnvironment(): void
    {
        // Initialize the legacy autoloader and environment
        if (! defined('URL_ADMIN')) {
            require_once __DIR__ . '/../../../nastaveni/zavadec-zaklad.php';
        }
        assert(defined('URL_ADMIN'), 'Legacy environment not initialized properly.');
    }

    public function getCurrentUser(): ?\Uzivatel
    {
        $this->initializeLegacyEnvironment();

        return \Uzivatel::zSession();
    }

    public function hasAdminAccess(): bool
    {
        $user = $this->getCurrentUser();

        if (! $user) {
            return false;
        }

        return array_intersect(
            range(Pravo::ADMINISTRACE_INFOPULT, Pravo::ADMINISTRACE_WEB_LOGA),
            $user->prava(),
        ) !== [];
    }
}
