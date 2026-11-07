<?php

require_once __DIR__ . '/_pomocne.php';

nadpis('POUŠTÍM TESTY');

call_check([__DIR__ . '/../bin/phpunit.sh']);
