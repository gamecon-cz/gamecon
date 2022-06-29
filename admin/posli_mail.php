<?php
require __DIR__ . '/../nastaveni/zavadec.php';

/** získáme @var array|string[] $protipy */
require_once __DIR__ . '/scripts/konstanty.php'; // lokální konstanty pro admin

echo (new GcMail('Pokus z ' . $_SERVER['SERVER_NAME']))
    ->adresat('jaroslav.tyc.83@gmail.com')
    ->odeslat();
