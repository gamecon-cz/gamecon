<?php
require __DIR__ . '/../nastaveni/zavadec.php';

/** získáme @var array|string[] $protipy */
require_once __DIR__ . '/scripts/konstanty.php'; // lokální konstanty pro admin
require_once __DIR__ . '/scripts/admin-menu.php'; // třída administračního menu

if (HTTPS_ONLY) {
    httpsOnly();
}

// nastaví uživatele $u a $uPracovni
/** @var $u */
require __DIR__ . '/scripts/prihlaseni.php';

if (!$u) {
    header('HTTP/1.1 403 Forbidden');
    die;
}

phpinfo();

die;
