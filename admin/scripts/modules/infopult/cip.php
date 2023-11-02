<?php
/**
 * Párování NFC čipu
 *
 * nazev: Čip
 * pravo: 100
 * submenu_hidden: true
 */

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

use Gamecon\Shop\Cip\CipHtml;
use Gamecon\Vyjimkovac\Vyjimkovac;

$cipHtml = new CipHtml(
    Vyjimkovac::js(URL_WEBU),
    $systemoveNastaveni,
);

echo $uPracovni
    ? $cipHtml->dejHtmlCipu($uPracovni)
    : '<div>Vyber pracovního uživatele</div>';
