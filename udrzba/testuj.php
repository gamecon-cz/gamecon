<?php

require_once __DIR__ . '/_pomocne.php';

$eol = PHP_EOL;
echo " ===============$eol";
echo "‖ POUŠTÍM TESTY ‖$eol";
echo " ===============$eol";

call_check(['php', __DIR__ . '/../vendor/bin/phpunit']);
