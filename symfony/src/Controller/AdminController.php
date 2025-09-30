<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LegacySessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends AbstractController
{
    private LegacySessionService $legacySession;

    public function __construct(LegacySessionService $legacySession)
    {
        $this->legacySession = $legacySession;
    }

    public function dashboard(): Response
    {
        if (! $this->legacySession->hasAdminAccess()) {
            return new RedirectResponse('/admin/login.php');
        }

        return new Response('
            <h1>Symfony Admin Dashboard</h1>
            <p>This is the new Symfony-powered admin dashboard!</p>
            <p><a href="/admin/">Back to legacy admin</a></p>
        ');
    }

    public function users(): Response
    {
        if (! $this->legacySession->hasAdminAccess()) {
            return new RedirectResponse('/admin/login.php');
        }

        return new Response('
            <h1>User Management (Symfony)</h1>
            <p>User management functionality would go here.</p>
            <p><a href="/admin/">Back to legacy admin</a></p>
        ');
    }

    public function activities(): Response
    {
        if (! $this->legacySession->hasAdminAccess()) {
            return new RedirectResponse('/admin/login.php');
        }

        return new Response('
            <h1>Activity Management (Symfony)</h1>
            <p>Activity management functionality would go here.</p>
            <p><a href="/admin/">Back to legacy admin</a></p>
        ');
    }
}
