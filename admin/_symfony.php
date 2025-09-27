<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/** @var Uzivatel|null $u */
if (!$u) {
    return;
}

// SYMFONY INTEGRATION: Try Symfony routing first for routes defined in Symfony
// Set environment variable for APP_SECRET if not defined
if (!isset($_ENV['APP_SECRET'])) {
    $_ENV['APP_SECRET'] = $_SERVER['APP_SECRET'] ?? 'fallback_secret_change_in_production';
}

$kernel = new Kernel('dev', true);
$kernel->boot();

/**
 * @var string $stranka
 * @var string|null $podstranka
 */
// Get router and check if current path matches any Symfony route
$router = $kernel->getContainer()->get('router');
$currentPath = '/' . ($stranka ?: '') . ($podstranka ? '/' . $podstranka : '');

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
