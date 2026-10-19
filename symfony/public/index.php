<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// Load full legacy configuration — sets constants, DB env vars, and APP_SECRET.
// This is the same loader used by admin and web pages, ensuring the JWT secret matches.
require_once dirname(__DIR__, 2).'/nastaveni/zavadec-zaklad.php';

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
