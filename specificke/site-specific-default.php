<?php

error_reporting(E_ALL);

$DATABASE_USER = 'root';
$DATABASE_PASSWORD = '';
$DATABASE_NAME = 'gamecon';
$DATABASE_SERVER = 'localhost';

define('VYVOJOVA', 1);
define('OSTRA', 2);
define('VETEV', VYVOJOVA);

define('SPECIFICKE', __DIR__);
define('WWW', __DIR__ . '/../novy');
define('ADMIN', __DIR__ . '/../admin');
define('URL_WEBU', 'http://localhost/gamecon/novy'); // absolutní url uživatelského webu
define('URL_ADMIN', 'http://localhost/gamecon/admin'); // absolutní url adminu

date_default_timezone_set('Europe/Prague');
