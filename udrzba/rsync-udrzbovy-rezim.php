<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__ . '/_pomocne.php';

/**
 * The switch of the maintenance mode, a file next to .htaccess: while it exists, every request to the site gets 503, see
 * dokumentace/nasazeni.md. It is not in git, so a deploy leaves it alone, see getRsyncProtectedPaths().
 */
const MAINTENANCE_SWITCH_FILE = 'maintenance.html';

/**
 * The page of the maintenance mode, in git. The switch made by hand links to it, the one made by {@see setMaintenanceViaRsync()} is a copy.
 */
const MAINTENANCE_PAGE_FILE = 'maintenance.off.html';

/**
 * What the page carries in its head, so that the check recognises it among the 503 of other layers. A page edited for a
 * case keeps it.
 */
const MAINTENANCE_PAGE_MARKER = '<meta name="gamecon-maintenance" content="1">';

/**
 * The deploy key may run only rsync in the directory of the site, so the maintenance mode is turned on and off over rsync
 * the way the deploy uploads files, the same key and the same server check. The key is held by `rrsync -munge`, which
 * makes a link it receives inert, so the switch is a copy of the page and not a link to it.
 */
function setMaintenanceViaRsync(
    bool   $maintenanceOn,
    string $pageFile,
    string $target,
): void {
    if ($maintenanceOn && !is_file($pageFile)) {
        throw new \RuntimeException('Chybí stránka údržby ' . MAINTENANCE_PAGE_FILE . ", hledal jsem ji v {$pageFile}");
    }

    withDeploySshFiles(static function (string $keyFile, string $knownHostsFile, ?int $sshPort) use ($maintenanceOn, $pageFile, $target): void {
        $rsh = buildRsyncSshCommand($keyFile, $knownHostsFile, $sshPort);
        if ($maintenanceOn) {
            msg('zapínám údržbový režim, nahrávám ' . MAINTENANCE_SWITCH_FILE);
            call_check(buildMaintenanceOnCommand($pageFile, $target, $rsh));

            return;
        }

        $filesystem     = new Filesystem();
        $emptyDirectory = sys_get_temp_dir() . '/gamecon-maintenance-empty-' . uniqid('', true);
        $filesystem->mkdir($emptyDirectory, 0700);
        try {
            msg('vypínám údržbový režim, mažu ' . MAINTENANCE_SWITCH_FILE);
            call_check(buildMaintenanceOffCommand($emptyDirectory, $target, $rsh));
        } finally {
            $filesystem->remove($emptyDirectory);
        }
    });
}

/**
 * The target of the deploy is a directory, `user@server:path/`; the switch lies in it. A target that ends with a colon is
 * the home directory of the account, where a slash would make it the root.
 */
function getMaintenanceSwitchTarget(string $target): string
{
    $directory = str_ends_with($target, ':')
        ? $target
        : rtrim($target, '/') . '/';

    return $directory . MAINTENANCE_SWITCH_FILE;
}

/**
 * @return list<string> arguments for {@see call_check()}
 */
function buildMaintenanceOnCommand(string $pageFile, string $target, string $rsh): array
{
    return [
        'rsync',
        '--compress',
        '--verbose',
        '--timeout=60',
        '--rsh', $rsh,
        $pageFile,
        getMaintenanceSwitchTarget($target),
    ];
}

/**
 * Deletes the switch from the server: nothing else is in the empty source and the filters leave only the switch to
 * delete, in case a filter goes wrong `--max-delete` stops rsync after the first deleted item.
 *
 * @return list<string> arguments for {@see call_check()}
 */
function buildMaintenanceOffCommand(string $emptyDirectory, string $target, string $rsh): array
{
    return [
        'rsync',
        '--recursive',
        '--delete-delay',
        '--max-delete=1',
        '--verbose',
        '--timeout=60',
        '--rsh', $rsh,
        '--filter=+ /' . MAINTENANCE_SWITCH_FILE,
        '--filter=- *',
        rtrim($emptyDirectory, '/') . '/',
        $target,
    ];
}

/**
 * The addresses of the production site, from the public settings that the deploy uses, one for each kind of .htaccess that
 * has to honour the switch: the root one, `web/`, the file of `cache/public/` that does not exist (an answer of its
 * own folder, not of the application), the admin on its own name and `/admin` on the name of the site.
 *
 * @return list<string>
 */
function getMaintenanceCheckUrls(string $publicSettingsFile): array
{
    $urls = readMaintenanceSiteUrls($publicSettingsFile);

    return [
        $urls['URL_WEBU'],
        $urls['URL_WEBU'] . '/web/',
        $urls['URL_WEBU'] . '/cache/public/maintenance-check',
        $urls['URL_ADMIN'],
        $urls['URL_WEBU'] . '/admin/?req=',
    ];
}

/**
 * The admin keeps its own copy of the page that its host serves as a file whether the maintenance is on or not, so
 * this address shows which page the server has.
 */
function getMaintenanceDeployedPageUrl(string $publicSettingsFile): string
{
    return readMaintenanceSiteUrls($publicSettingsFile)['URL_ADMIN'] . '/' . MAINTENANCE_PAGE_FILE;
}

/**
 * @return array{URL_WEBU: string, URL_ADMIN: string}
 */
function readMaintenanceSiteUrls(string $publicSettingsFile): array
{
    $settings = (string)file_get_contents($publicSettingsFile);
    $urls     = [];
    foreach (['URL_WEBU', 'URL_ADMIN'] as $constant) {
        if (preg_match("~define\\(\\s*'{$constant}'\\s*,\\s*'([^']+)'~", $settings, $matches) !== 1) {
            throw new \RuntimeException("V {$publicSettingsFile} chybí {$constant}, nevím, co po přepnutí údržby zkontrolovat");
        }
        $urls[$constant] = $matches[1];
    }

    return $urls;
}

/**
 * The server serves the page it was deployed with, and the switch uploads only a copy that no request reads. A switch
 * turned on before the deploy that brought the page and its marker would close the site with a page the check cannot
 * recognise, so a definite answer that the page is missing (404) or older (200 without the marker) stops it. A server
 * that does not answer, or answers with an error, is one more reason for the maintenance and not against it, so the
 * switch goes on and the check afterwards says what the site answers.
 *
 * @param callable(string): array{status: int, retryAfter: ?string, body: string} $fetch
 */
function assertMaintenancePageDeployed(string $pageUrl, callable $fetch, string $pageMarker): void
{
    try {
        $response = $fetch($pageUrl);
    } catch (\Exception $exception) {
        // a bug (an Error) must not pass as an unreachable server; the line is an annotation of the GitHub run
        echo "::warning::Nepodařilo se ověřit nasazenou stránku údržby ({$exception->getMessage()}), údržba se zapíná dál" . PHP_EOL;

        return;
    }
    $status = $response['status'];
    if ($status === 200 && str_contains($response['body'], $pageMarker)) {
        return;
    }
    if ($status === 200 || $status === 404) {
        throw new \RuntimeException(
            "{$pageUrl} odpovídá {$status}" . ($status === 200
                ? ' a stránka nemá značku údržby'
                : '') . ': ostrá ještě nemá nasazenou stránku údržby se značkou, dokonči nejdřív nasazení, údržba se nezapnula',
        );
    }
    echo "::warning::{$pageUrl} odpovídá {$status}, nasazenou stránku údržby se nepodařilo ověřit, údržba se zapíná dál" . PHP_EOL;
}

/**
 * @param list<string> $curlHeaders lines of `curl --dump-header -`, one block per response
 * @return array{status: int, retryAfter: ?string} of the last response
 */
function parseHttpResponseHeaders(array $curlHeaders): array
{
    $status     = null;
    $retryAfter = null;
    foreach ($curlHeaders as $line) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $line, $matches) === 1) {
            $status     = (int)$matches[1];
            $retryAfter = null;
        } elseif (preg_match('~^retry-after:\s*(.+?)\s*$~i', $line, $matches) === 1) {
            $retryAfter = $matches[1];
        }
    }
    if ($status === null) {
        throw new \RuntimeException('Odpověď nemá stavový řádek HTTP: ' . implode(' | ', $curlHeaders));
    }

    return ['status' => $status, 'retryAfter' => $retryAfter];
}

/**
 * Redirects are not followed, the answer of the address itself is what counts.
 *
 * @return array{status: int, retryAfter: ?string, body: string}
 */
function fetchHttpResponse(string $url): array
{
    $bodyFile = tempnam(sys_get_temp_dir(), 'gamecon-maintenance-body-');
    if ($bodyFile === false) {
        throw new \RuntimeException('Nejde vytvořit dočasný soubor pro tělo odpovědi');
    }
    try {
        $headers = parseHttpResponseHeaders(call_check_capture([
            'curl',
            '--silent',
            '--show-error',
            '--max-time', '30',
            '--output', $bodyFile,
            '--dump-header', '-',
            $url,
        ]));

        return ['status' => $headers['status'], 'retryAfter' => $headers['retryAfter'], 'body' => (string)file_get_contents($bodyFile)];
    } finally {
        unlink($bodyFile);
    }
}

/**
 * A 503 counts as the maintenance only with `Retry-After` and the marker of the page, so a 503 of another layer (Caddy,
 * Apache without PHP) does not pass for it. The server serves the deployed copy of the page, which may be older than
 * the one in this checkout, hence a marker and not the whole body. The other way round every status but 503 counts as
 * open: whether the site works is not for this check.
 *
 * @param list<string> $urls
 * @param callable(string): array{status: int, retryAfter: ?string, body: string} $fetch
 * @return list<string> what is not as the mode requires, empty when every address is
 */
function findMaintenanceProblems(bool $maintenanceOn, array $urls, callable $fetch, string $pageMarker): array
{
    $problems = [];
    foreach ($urls as $url) {
        try {
            $response = $fetch($url);
        } catch (\Throwable $throwable) {
            $problems[] = "{$url} se nepodařilo zkontrolovat: {$throwable->getMessage()}";
            continue;
        }
        if ($maintenanceOn && $response['status'] !== 503) {
            $problems[] = "{$url} odpovídá {$response['status']}, údržba má 503";
        } elseif ($maintenanceOn && !str_contains($response['body'], $pageMarker)) {
            $problems[] = "{$url} odpovídá 503, ale ne stránkou údržby, nejspíš z jiné vrstvy než z maintenance.html";
        } elseif ($maintenanceOn && $response['retryAfter'] === null) {
            $problems[] = "{$url} odpovídá 503 stránkou údržby, ale bez hlavičky Retry-After";
        } elseif (!$maintenanceOn && $response['status'] === 503) {
            $problems[] = "{$url} pořád odpovídá 503 údržby";
        }
    }

    return $problems;
}

/**
 * @param list<string> $urls
 * @param callable(string): array{status: int, retryAfter: ?string, body: string} $fetch
 * @param ?callable(int): mixed $sleep
 */
function waitForMaintenanceState(
    bool      $maintenanceOn,
    array     $urls,
    callable  $fetch,
    string    $pageMarker,
    int       $attempts,
    int       $secondsBetweenAttempts,
    ?callable $sleep = null,
): void {
    $sleep ??= static fn(int $seconds) => sleep($seconds);
    for ($attempt = 1; ; $attempt++) {
        $problems = findMaintenanceProblems($maintenanceOn, $urls, $fetch, $pageMarker);
        if ($problems === []) {
            msg(($maintenanceOn
                    ? 'údržba je zapnutá'
                    : 'údržba je vypnutá') . ', ověřeno požadavky na ' . count($urls) . ' adres: ' . implode(', ', $urls));

            return;
        }
        if ($attempt >= $attempts) {
            throw new \RuntimeException(implode('; ', $problems));
        }
        msg("pokus {$attempt} z {$attempts}: " . implode('; ', $problems));
        $sleep($secondsBetweenAttempts);
    }
}
