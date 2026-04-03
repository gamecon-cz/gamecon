<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// Load legacy configuration constants and set GAMECON_DB_* env vars via putenv().
// Only load the minimal config — don't boot SystemoveNastaveni (which creates another kernel).
require_once dirname(__DIR__, 2).'/nastaveni/zavadec-autoloader.php';
require_once dirname(__DIR__, 2).'/model/funkce/funkce.php';
require_once dirname(__DIR__, 2).'/model/funkce/skryte-nastaveni-z-env-funkce.php';
require_once dirname(__DIR__, 2).'/nastaveni/zavadec-nastaveni.php';

// Set DB env vars from legacy constants for Symfony services.yaml
putenv('GAMECON_DB_NAME=' . DB_NAME);
putenv('GAMECON_DB_ANONYM_NAME=' . (defined('DB_ANONYM_NAME') ? DB_ANONYM_NAME : ''));
putenv('GAMECON_DB_HOST=' . DB_SERV);
putenv('GAMECON_DB_PORT=' . DB_PORT);
putenv('GAMECON_DB_USER=' . DB_USER);
putenv('GAMECON_DB_PASSWORD=' . DB_PASS);
if (defined('URL_WEBU') && URL_WEBU) {
    putenv('DEFAULT_URI=' . URL_WEBU);
}
if (defined('SPEC')) {
    putenv('LEGACY_CACHE_DIR=' . SPEC);
}
if (defined('APP_SECRET') && !getenv('APP_SECRET')) {
    putenv('APP_SECRET=' . APP_SECRET);
}

// Load environment variables from root .env file (for non-legacy vars)
$envFile = dirname(__DIR__, 2).'/.env';
if (file_exists($envFile)) {
    (new Dotenv())->load($envFile);
}

// Set Symfony environment from env vars or defaults
$_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? '1';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    Debug::enable();
}

try {
    $kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
    $request = Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (\Throwable $e) {
    // API errors must be JSON, not HTML
    $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
    $response = new JsonResponse(
        [
            'error' => $e->getMessage(),
            'code' => $statusCode,
        ],
        $statusCode,
    );
    $response->send();
}
