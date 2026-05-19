<?php

/** @var string|null $podstranka */

$osobniProgram = true;

if (($podstranka ?? '') === '') {
    $queryString = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    back(URL_ADMIN . '/program-osobni/muj' . ($queryString ? '?' . $queryString : ''));
}

require __DIR__ . '/program-uzivatele.php';
