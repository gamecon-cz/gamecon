<?php

error_reporting(E_ALL ^ E_STRICT);

$DATABASE_USER='root';
$DATABASE_PASSWORD='';
$DATABASE_NAME='gamecon';
$DATABASE_SERVER='localhost';

define('VYVOJOVA', 1);
define('OSTRA', 2);
define('VETEV', VYVOJOVA);

define('ADMIN_WWW_CESTA','../www'); //cesta z rootu admina do rootu uživatelské části
define('SDILENE_WWW_CESTA','../www'); //cesta z sdílených tříd do rootu uživatelské části
define('URL_WEBU','http://localhost/gamecon/www'); //absolutní url uživatelského webu
define('URL_ADMIN','http://localhost/gamecon/admin'); //absolutní url adminu

date_default_timezone_set('Europe/Prague');
