<?php

declare(strict_types=1);

require ADMIN . '/scripts/zvlastni/program-test-mock.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode($programTestMockAktivityUzivatel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
