<?php

require_once __DIR__ . '/_pomocne.php';

nadpis('POUŠTÍM TESTY');

call_check(['php', __DIR__ . '/../vendor/bin/phpunit']);
