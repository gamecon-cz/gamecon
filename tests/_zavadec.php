<?php

namespace Gamecon\Tests;

use Gamecon\Tests\DBTest;

require_once __DIR__ . '/../nastaveni/verejne-nastaveni-tests.php';
require_once __DIR__ . '/../nastaveni/zavadec-zaklad.php';

DBTest::resetujDB("_localhost-2023_06_21_21_58_55-dump.sql");
DBTest::smazDbNakonec();
