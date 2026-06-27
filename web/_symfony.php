<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/*
 * Symfony router shim pro VEŘEJNÝ web (web/index.php).
 *
 * Obdoba admin/_symfony.php, ale bez podmínky na přihlášeného uživatele —
 * veřejné Symfony cesty (např. obnova hesla) obsluhují i nepřihlášené.
 * Volá se jen pro předem vybrané cesty (viz web/index.php), takže kernel
 * nebootujeme zbytečně.
 *
 * @var string $aktualniCesta cesta requestu (bez query), normalizovaná na „/foo"
 */

if (! isset($_ENV['APP_SECRET'])) {
    $_ENV['APP_SECRET'] = $_SERVER['APP_SECRET'] ?? (defined('APP_SECRET') ? APP_SECRET : 'fallback_secret_change_in_production');
}

$jsmeNaLocale = jsmeNaLocale();
$kernel = new Kernel($jsmeNaLocale
    ? 'dev'
    : 'prod', $jsmeNaLocale);
$kernel->boot();

/** @var Symfony\Component\Routing\Router $router */
$router = $kernel->getContainer()->get('router');
$matcher = $router->getMatcher();

try {
    $routeMatch = $matcher->match($aktualniCesta);
} catch (ResourceNotFoundException $e) {
    $routeMatch = null;
}

if ($routeMatch) {
    // Legacy běží pod prefixem /web (Apache rewrite), Symfony route je bez něj.
    // Kernelu proto předáme request s čistou cestou ($aktualniCesta), aby
    // routing v handle() trefil controller; metodu, POST, cookies, soubory,
    // hlavičky i query zachováme z reálného requestu.
    $globalni = Request::createFromGlobals();
    $request = Request::create(
        uri: $aktualniCesta,
        method: $globalni->getMethod(),
        parameters: $globalni->isMethod('POST')
            ? $globalni->request->all()
            : $globalni->query->all(),
        cookies: $globalni->cookies->all(),
        files: $globalni->files->all(),
        server: $globalni->server->all(),
        content: $globalni->getContent(),
    );
    $request->query->replace($globalni->query->all());

    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
    exit;
}

// Žádná shoda → kernel ukliď a vrať se do legacy routeru.
$kernel->shutdown();
