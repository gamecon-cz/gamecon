<?php

declare(strict_types=1);

// Dispatcher pro mock API endpointy v admin/scripts/api/mock/.
// Volá se jako: GET /admin/api/mock/{endpoint}

$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));
// např. ['admin', 'api', 'mock', 'prihlasenyUzivatel']
$endpoint = $segments[3] ?? '';

$file = __DIR__ . '/mock/' . $endpoint . '.php';

if (!$endpoint || !is_file($file)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => "Mock endpoint nenalezen: {$endpoint}"], JSON_UNESCAPED_UNICODE);
    return;
}

require $file;
