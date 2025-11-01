<?php

/** @var Uzivatel $u */

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaTym;

// todo: zabezpečení - uživatel může volat jen kód sebe pokud není organizátor

$u = Uzivatel::zSession();
$this->bezStranky(true);
$response = [];

if (!$u) {
    return ;
}

$aktivitaId = array_key_exists('aktivitaId', $_GET)
    ? (int)$_GET['aktivitaId']
    : -1;

$uzivatelId = array_key_exists('uzivatelId', $_GET)
    ? (int)$_GET['uzivatelId']
    : $u->id();

$kod = AktivitaTym::vratKodTymuProUzivatele($uzivatelId, $aktivitaId);

$response["kod"] = $kod;

$jsonConfig = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
header('Content-type: application/json');
echo json_encode($response, $jsonConfig);
