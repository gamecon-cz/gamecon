<?php

use App\Entity\User;
use App\Service\MealMenuReader;
use App\Service\MealSlot;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Web\PreactSekce;

/**
 * Jídelníček na stránce Jídelníček: vkládá se do jejího textu jako `(widget:jidelnicek)`.
 *
 * Jídla vidí každý; přihlášený účastník si pod nimi vybírá k objednaným obědům a večeřím.
 * Výběr je tatáž Preact komponenta jako v přihlášce.
 *
 * @var \Gamecon\XTemplate\XTemplate $t
 */

$systemoveNastaveni ??= SystemoveNastaveni::zGlobals();

if (!$systemoveNastaveni->jeJidelnicekZverejnen()) {
    $t->assign('datumZverejneni', $systemoveNastaveni->jidelnicekZverejnitOd()->format('j. n.'));
    $t->parse('jidelnicek.nezverejneno');

    return;
}

$velkePismeno = static fn(string $text): string => mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
// XTemplate neescapuje a názvy jídel píše admin
$html         = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_HTML5);
$rocnik       = $systemoveNastaveni->rocnik();

try {
    /** @var MealMenuReader $menuReader */
    $menuReader = $systemoveNastaveni->getService(MealMenuReader::class);
    $sloty      = array_filter(
        $menuReader->getSlots($rocnik),
        static fn(MealSlot $slot): bool => $slot->dishes !== [],
    );
} catch (\Throwable $chyba) {
    trigger_error('Jídelníček se nepodařilo načíst: ' . $chyba->getMessage(), E_USER_WARNING);
    $t->parse('jidelnicek.chyba');

    return;
}

if ($sloty === []) {
    $t->parse('jidelnicek.prazdny');

    return;
}

$slotyPoDnech = [];
foreach ($sloty as $slot) {
    $slotyPoDnech[$slot->day][] = $slot;
}
foreach ($slotyPoDnech as $slotyDne) {
    $t->assign('den', $html($velkePismeno(reset($slotyDne)->getDayName())));
    foreach ($slotyDne as $slot) {
        $vychozi = $slot->getDefaultDish();
        $t->assign('druh', $html($velkePismeno(explode(' ', $slot->name)[0])));
        foreach ($slot->dishes as $jidlo) {
            $t->assign([
                'nazev' => $html($jidlo->getName()),
                'popis' => $html((string)$jidlo->getDescription()),
            ]);
            if ($jidlo->isVegetarian()) {
                $t->parse('jidelnicek.jidla.den.slot.jidlo.vegetarianske');
            }
            if ($jidlo === $vychozi) {
                $t->parse('jidelnicek.jidla.den.slot.jidlo.vychozi');
            }
            if ($jidlo->getDescription()) {
                $t->parse('jidelnicek.jidla.den.slot.jidlo.popis');
            }
            $t->parse('jidelnicek.jidla.den.slot.jidlo');
        }
        $t->parse('jidelnicek.jidla.den.slot');
    }
    $t->parse('jidelnicek.jidla.den');
}
$t->assign('vyberDo', $systemoveNastaveni->prodejJidlaDo()->format('j. n.'));
$t->parse('jidelnicek.jidla');

$u = Uzivatel::zSession();
if (!$u) {
    $t->parse('jidelnicek.neprihlasen');

    return;
}

try {
    $zakaznik = $systemoveNastaveni->getService('doctrine.orm.entity_manager')->find(User::class, $u->id());
    $maPorce  = false;
    if ($zakaznik !== null) {
        foreach ($menuReader->forCustomer($zakaznik, $rocnik, false)->slots as $slotZakaznika) {
            $maPorce = $maPorce || $slotZakaznika->portions !== [];
        }
    }
    if ($maPorce) {
        $t->assign('vyberJidel', PreactSekce::html('preact-vyber-jidel', $u, $systemoveNastaveni));
        $t->parse('jidelnicek.vyber');
    } else {
        $t->assign('urlPrihlasky', URL_WEBU . '/prihlaska');
        $t->parse('jidelnicek.bezPorci');
    }
} catch (\Throwable $chyba) {
    trigger_error('Výběr jídel se nepodařilo připravit: ' . $chyba->getMessage(), E_USER_WARNING);
    $t->assign('urlPrihlasky', URL_WEBU . '/prihlaska');
    $t->parse('jidelnicek.vyberVPrihlasce');
}
