<?php

use Gamecon\Web\ObrazkyObsahu;
use Gamecon\Web\QrOdkaz;

/**
 * Přidat novinku
 *
 * nazev: Přidat novinku
 * pravo: 105
 */

$novinka = get('id') ? Novinka::zId(get('id')) : null;
$obrazkyObsahu = $novinka ? ObrazkyObsahu::proNovinku($novinka) : null;
$obrazkyObsahu?->zpracujPost();

$form = Novinka::form(get('id'));
$form->processPost();
echo $form->full();

if ($novinka) {
    echo $obrazkyObsahu->html();
    $jeBlog = (int)$novinka->typ() === Novinka::BLOG;
    echo (new QrOdkaz($novinka->urlNaWebu(), 'qr-' . ($jeBlog ? 'blog-' . $novinka->url() : 'novinky')))
        ->html($jeBlog ? '' : 'Novinky nemají vlastní stránku, odkaz vede na výpis novinek.');
} else {
    echo '<p class="tip">Obrázky půjde nahrát, až novinku uložíš.</p>';
}
