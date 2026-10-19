<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Who is acting and where the action came from, for the audit log. Both come from what the
 * process already holds in memory: an audit row must not cost a query.
 */
class AuditOriginProvider
{
    /**
     * @see \App\Entity\OrderItemLog::$origin the length of its column
     */
    private const int ORIGIN_MAX_LENGTH = 255;

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * NULL when nobody is signed in: a console command or a cron job.
     */
    public function getActorId(): ?int
    {
        $user = $this->security->getUser();
        if ($user !== null) {
            assert($user instanceof User);

            return $user->getId();
        }

        // Legacy pages never boot a Symfony request, so the user lives in the legacy session.
        if (class_exists(\Uzivatel::class, false)) {
            return \Uzivatel::aktualniEditorIdZeSession();
        }

        return null;
    }

    /**
     * The route name of a Symfony request, the path of a legacy page, or the script that ran.
     * Never the query string or a command's arguments, which may carry tokens.
     */
    public function getOrigin(): string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request !== null) {
            $route = $request->attributes->get('_route');

            return $this->fit(is_string($route) && $route !== '' ? $route : $request->getPathInfo());
        }

        if (PHP_SAPI === 'cli') {
            $arguments = $_SERVER['argv'] ?? [];
            $script = basename((string) ($arguments[0] ?? 'cli'));

            // Only the console names its command in the first argument; elsewhere it is data.
            return $this->fit($script === 'console' ? trim($script . ' ' . ($arguments[1] ?? '')) : $script);
        }

        $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');

        return $this->fit($path !== false ? $path : 'unknown');
    }

    private function fit(string $origin): string
    {
        return mb_substr($origin, 0, self::ORIGIN_MAX_LENGTH);
    }
}
