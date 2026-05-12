<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(
    ['ok' => true, 'message' => 'Mock: akce zaznamenána, žádná skutečná změna v DB'],
    JSON_UNESCAPED_UNICODE,
);
