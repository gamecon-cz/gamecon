<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Path;
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

    /**
     * What PHP puts into argv[0] for `php -` and `php -r`: not files, so not paths either.
     */
    private const array PSEUDO_SCRIPTS = ['-', 'Standard input code'];

    private readonly string $appRootDir;

    /**
     * A stack, because a command may run another one through the application.
     *
     * @var list<string|null>
     */
    private array $consoleCommandNames = [];

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        string $appRootDir,
    ) {
        $this->appRootDir = Path::canonicalize(realpath($appRootDir) ?: $appRootDir);
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
     * What the application resolved from the command line, which the arguments may only abbreviate.
     */
    #[AsEventListener(event: ConsoleEvents::COMMAND)]
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->consoleCommandNames[] = $event->getCommand()?->getName();
    }

    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function onConsoleTerminate(): void
    {
        array_pop($this->consoleCommandNames);
    }

    /**
     * `console <command>` of a Symfony command, the route name of a Symfony request, the path of a legacy
     * page, or the script that ran as a path from the app root. Never the query string or a script's
     * arguments, which may carry tokens.
     */
    public function getOrigin(): string
    {
        $consoleCommandName = end($this->consoleCommandNames);
        if (is_string($consoleCommandName)) {
            return $this->fit('console ' . $consoleCommandName);
        }

        $request = $this->requestStack->getMainRequest();
        if ($request !== null) {
            $route = $request->attributes->get('_route');

            return $this->fit(is_string($route) && $route !== '' ? $route : $request->getPathInfo());
        }

        if (PHP_SAPI === 'cli') {
            return $this->fit($this->getScriptPath((string) (($_SERVER['argv'] ?? [])[0] ?? '')));
        }

        $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');

        return $this->fit($path !== false ? $path : 'unknown');
    }

    /**
     * From the app root, because scripts of one name live in several directories; one outside the app
     * keeps its absolute path. The directory is resolved through symlinks like the app root is, so a
     * symlinked deploy path does not make the app look foreign.
     */
    private function getScriptPath(string $script): string
    {
        if ($script === '') {
            return 'cli';
        }
        if (in_array($script, self::PSEUDO_SCRIPTS, true)) {
            return $script;
        }

        $absolutePath = Path::makeAbsolute($script, getcwd() ?: $this->appRootDir);
        $directory = realpath(dirname($absolutePath));
        if ($directory !== false) {
            $absolutePath = Path::join($directory, basename($absolutePath));
        }

        return Path::isBasePath($this->appRootDir, $absolutePath)
            ? Path::makeRelative($absolutePath, $this->appRootDir)
            : $absolutePath;
    }

    private function fit(string $origin): string
    {
        return mb_substr($origin, 0, self::ORIGIN_MAX_LENGTH);
    }
}
