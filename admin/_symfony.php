<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/** @var Uzivatel|null $u */
if (!$u) {
    return;
}

// SYMFONY INTEGRATION: Try Symfony routing first for routes defined in Symfony
// Ensure APP_SECRET env var is set from the legacy constant (defined in nastaveni-local-default.php)
if (!isset($_ENV['APP_SECRET']) && defined('APP_SECRET')) {
    $_ENV['APP_SECRET'] = APP_SECRET;
}

$jsmeNaLocale = jsmeNaLocale();
$kernel = new Kernel($jsmeNaLocale
    ? 'dev'
    : 'prod', $jsmeNaLocale);
$kernel->boot();

/**
 * @var string $stranka
 * @var string|null $podstranka
 */
// Get router and check if current path matches any Symfony route
$router = $kernel->getContainer()->get('router');
$currentPath = '/' . ($stranka
        ?: '') . ($podstranka
        ? '/' . $podstranka
        : '');

// Try to match the current path against Symfony routes
$matcher = $router->getMatcher();
try {
    $routeMatch = $matcher->match($currentPath);
} catch (ResourceNotFoundException $e) {
    $routeMatch = null; // No matching route found
}

if ($routeMatch) {
    // Route found in Symfony, handle with Symfony
    $request = Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
    exit;
}
