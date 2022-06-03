<?php

require __DIR__ . '/../nastaveni/zavadec.php';

/** získáme @var array|string[] $protipy */
require_once __DIR__ . '/scripts/konstanty.php'; // lokální konstanty pro admin
require_once __DIR__ . '/scripts/admin-menu.php'; // třída administračního menu

if (HTTPS_ONLY) {
    httpsOnly();
}

// nastaví uživatele $u a $uPracovni
require __DIR__ . '/scripts/prihlaseni.php';

$filesystem = new \Symfony\Component\Filesystem\Filesystem();
$filesystem->mirror(__DIR__ . '/..', __DIR__ . '/../../sciator');

echo 'hotovo';
