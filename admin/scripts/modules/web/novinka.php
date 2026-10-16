<?php

use Gamecon\Web\QrOdkaz;

/**
 * Přidat novinku
 *
 * nazev: Přidat novinku
 * pravo: 105
 */

$form = Novinka::form(get('id'));
$form->processPost();
echo $form->full();

$novinka = get('id') ? Novinka::zId(get('id')) : null;
if ($novinka) {
    $jeBlog = (int)$novinka->typ() === Novinka::BLOG;
    echo (new QrOdkaz($novinka->urlNaWebu(), 'qr-' . ($jeBlog ? 'blog-' . $novinka->url() : 'novinky')))
        ->html($jeBlog ? '' : 'Novinky nemají vlastní stránku, odkaz vede na výpis novinek.');
}
