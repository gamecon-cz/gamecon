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

    /**
     * Any user by id, not just the one signed in — the admin desk acts for someone else, and
     * the permissions that decide what may be booked live only on the legacy user.
     */
    public function getUserById(int $id): ?\Uzivatel
    {
        $this->initializeLegacyEnvironment();

        // Uncached: the permissions this object carries decide what may be booked, and the
        // cache is a process-static that would outlive a revocation in a worker.
        return \Uzivatel::zId($id);
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
