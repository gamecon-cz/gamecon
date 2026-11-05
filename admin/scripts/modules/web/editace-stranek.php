<?php

use Gamecon\Web\NahravaniPoCastech;
use Gamecon\Web\NahravaniPoCastechPozadavek;
use Gamecon\Web\NahravaniPodEditorem;
use Gamecon\Web\OdkazNaWeb;
use Gamecon\Web\QrOdkaz;
use Gamecon\XTemplate\XTemplate;

/**
 * nazev: Editace stránek
 * ikona: pen-to-square
 * pravo: 105
 */

/**
 * @var Uzivatel $u
 */

$nahravaniPoCastech = NahravaniPoCastech::proUzivatele($u->id());
NahravaniPoCastechPozadavek::obslouzPokudJe($nahravaniPoCastech);

if (get('id') || get('akce') === 'nova') {
    // režim editace
    // ne $stranka: modul běží ve scope admin/index.php, který si v $stranka drží název stránky adminu pro submenu
    $upravovanaStranka = get('id') ? Stranka::zId(get('id')) : null;
    $nahravaniPodEditorem = $upravovanaStranka
        ? NahravaniPodEditorem::proStranku($upravovanaStranka, $nahravaniPoCastech)
        : null;
    $nahravaniPodEditorem?->zpracujPost();
    $f = Stranka::form(get('id'));
    $f->processPost();
    echo $upravovanaStranka
        ? OdkazNaWeb::html($upravovanaStranka->urlNaWebu(), 'Stránka na webu')
        : '';
    echo $f->full();
    if ($upravovanaStranka) {
        echo $nahravaniPodEditorem->html();
        echo (new QrOdkaz($upravovanaStranka->urlNaWebu(), 'qr-' . str_replace('/', '-', $upravovanaStranka->url())))->html();
    } else {
        echo '<p class="tip">Obrázky a soubory půjde nahrát, až stránku uložíš.</p>';
    }
    return;
}

$t = new XTemplate(__DIR__ . '/editace-stranek.xtpl');
$t->parseEach(Stranka::zVsech(), 'stranka', 'editaceStranek.radek');
$t->parse('editaceStranek');
$t->out('editaceStranek');
