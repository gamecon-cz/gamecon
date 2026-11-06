<?php

use Gamecon\Web\NahravaniPoCastech;
use Gamecon\Web\NahravaniPoCastechPozadavek;
use Gamecon\Web\NahravaniPodEditorem;
use Gamecon\Web\OdkazNaWeb;
use Gamecon\Web\QrOdkaz;

/**
 * Přidat novinku
 *
 * nazev: Přidat novinku
 * ikona: newspaper
 * pravo: 105
 */

/**
 * @var Uzivatel $u
 */

$nahravaniPoCastech = NahravaniPoCastech::proUzivatele($u->id());
NahravaniPoCastechPozadavek::obslouzPokudJe($nahravaniPoCastech);

$novinka = get('id') ? Novinka::zId(get('id')) : null;
$nahravaniPodEditorem = $novinka
    ? NahravaniPodEditorem::proNovinku($novinka, $nahravaniPoCastech)
    : null;
$nahravaniPodEditorem?->zpracujPost();
$jeBlog = $novinka !== null && (int)$novinka->typ() === Novinka::BLOG;

$form = Novinka::form(get('id'));
$form->processPost();
echo $novinka
    ? OdkazNaWeb::html($novinka->urlNaWebu(), $jeBlog ? 'Příspěvek na webu' : 'Výpis novinek na webu')
    : '';
echo $form->full();

if ($novinka) {
    echo $nahravaniPodEditorem->html();
    echo (new QrOdkaz($novinka->urlNaWebu(), 'qr-' . ($jeBlog ? 'blog-' . $novinka->url() : 'novinky')))
        ->html($jeBlog ? '' : 'Novinky nemají vlastní stránku, odkaz vede na výpis novinek.');
} else {
    echo '<p class="tip">Obrázky a soubory půjde nahrát, až novinku uložíš.</p>';
}
