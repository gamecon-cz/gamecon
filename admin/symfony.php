<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

// Set environment variable for APP_SECRET if not defined
if (!isset($_ENV['APP_SECRET'])) {
    $_ENV['APP_SECRET'] = $_SERVER['APP_SECRET'] ?? 'fallback_secret_change_in_production';
}

require_once dirname(__DIR__).'/vendor/autoload.php';

$kernel = new Kernel('dev', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);