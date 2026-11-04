<?php

use Gamecon\Web\NahravaniPoCastech;
use Gamecon\Web\NahravaniPoCastechPozadavek;
use Gamecon\Web\NahravaniPodEditorem;
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

$form = Novinka::form(get('id'));
$form->processPost();
echo $form->full();

if ($novinka) {
    echo $nahravaniPodEditorem->html();
    $jeBlog = (int)$novinka->typ() === Novinka::BLOG;
    echo (new QrOdkaz($novinka->urlNaWebu(), 'qr-' . ($jeBlog ? 'blog-' . $novinka->url() : 'novinky')))
        ->html($jeBlog ? '' : 'Novinky nemají vlastní stránku, odkaz vede na výpis novinek.');
} else {
    echo '<p class="tip">Obrázky a soubory půjde nahrát, až novinku uložíš.</p>';
}
