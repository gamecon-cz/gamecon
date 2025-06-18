<?php

use Gamecon\Vyjimkovac\Logovac;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Web\Urls;

/**
 * Stránka pro tvorbu a editaci aktivit. Brand new.
 *
 * nazev: Nová aktivita
 * pravo: 102
 * submenu_group: 1
 * submenu_order: 1
 */

/**
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 * @var \Uzivatel|null $u
 */

if (Aktivita::editorTestJson()) {       // samo sebe volání ajaxu
    echo Aktivita::editorChybyJson();
    exit;
}

/** @var Logovac $vyjimkovac */
try {
    if ($a = Aktivita::editorZpracuj($u?->maPravoNaProvadeniKorekci())) {  // úspěšné uložení změn ve formuláři
        if ($a->nova()) {
            back(Urls::urlAdminDetailAktivity($a->id()));
        } else {
            back();
        }
    }
} catch (ObrazekException $obrazekException) {
    if (!$obrazekException->zUrl()) {
        $vyjimkovac->zaloguj($obrazekException);
    }
    if (get('aktivitaId')) {
        chyba('Obrázek nelze přečíst: ' . $obrazekException->getMessage());
    } else {
        oznameni('Aktivita vytvořena.', false); // hack - obrázek selhal, ale zbytek nejspíš prošel, vypíšeme úspěch
        back('aktivity');
    }
}

Aktivita::prednactiVse($systemoveNastaveni);

$a = Aktivita::zId(
    id: get('aktivitaId'),
    zCache: true,
    systemoveNastaveni: $systemoveNastaveni,
);  // načtení aktivity podle předaného ID

$editorAktivity = Aktivita::editor($systemoveNastaveni, $a);         // načtení html editoru aktivity

?>

<form method="post" enctype="multipart/form-data" style="position: relative">
    <?= $editorAktivity ?>
</form>
