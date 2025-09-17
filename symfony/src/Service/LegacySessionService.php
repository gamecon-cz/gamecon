<?php

namespace App\Service;

class LegacySessionService
{
    public function initializeLegacyEnvironment(): void
    {
        // Initialize the legacy autoloader and environment
        if (!defined('URL_ADMIN')) {
            require_once __DIR__ . '/../../nastaveni/zavadec.php';
        }
    }

    public function getCurrentUser()
    {
        $this->initializeLegacyEnvironment();
        return \Uzivatel::zSession();
    }

    public function hasAdminAccess(): bool
    {
        $user = $this->getCurrentUser();
        return $user && ($user->jeOrganizator() || $user->maPravo(\Gamecon\Pravo::ADMINISTRACE_INFOPULT));
    }
}