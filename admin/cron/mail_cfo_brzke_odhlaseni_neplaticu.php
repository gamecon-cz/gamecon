<?php

use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Role\Role;

require_once __DIR__ . '/_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(30);

global $systemoveNastaveni;

$hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

$odhlaseniProvedenoKdy = $hromadneOdhlaseniNeplaticu->odhlaseniProvedenoKdy();
if ($odhlaseniProvedenoKdy) {
    logs("Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)}");
    return;
}

$overenaPlatnostZpetne           = DateTimeGamecon::overenaPlatnostZpetne($systemoveNastaveni)
    ->modifyStrict('+1 day'); // jako kdybychom bychom pouštěli hromadné odhlašování zítra
$nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($systemoveNastaveni, $overenaPlatnostZpetne);


$emailCfoOBlizicimSeOdhlaseniOdeslanKdy = $hromadneOdhlaseniNeplaticu->cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy($nejblizsiHromadneOdhlasovaniKdy);
if ($emailCfoOBlizicimSeOdhlaseniOdeslanKdy) {
    logs("Email pro CFO o zítřejším hromadné, odhlášení už byl odeslán {$emailCfoOBlizicimSeOdhlaseniOdeslanKdy->format(DateTimeCz::FORMAT_DB)}");
    return;
}

// abychom měli čerstvé informace o neplatičích
require __DIR__ . '/fio_stazeni_novych_plateb.php';

$zpravy                          = [];
try {
    foreach ($hromadneOdhlaseniNeplaticu->neplaticiAKategorie()
             as ['uzivatel' => $uzivatel, 'kategorie_neplatice' => $kategorieNeplatice]) {
        /** @var \Gamecon\Uzivatel\KategorieNeplatice $kategorieNeplatice */
        $zpravy[] = "Účastník '{$uzivatel->jmenoNick()}' ({$uzivatel->id()}) bude zítra odhlášen, protože má kategorii neplatiče {$kategorieNeplatice->dejCiselnouKategoriiNeplatice()}";
    }
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
    return;
}

$cfos       = Uzivatel::zRole(Role::CFO);
$cfosEmaily = array_filter(
    array_map(static fn(Uzivatel $cfo) => $cfo->mail(), $cfos),
    static fn($mail) => is_string($mail) && filter_var($mail, FILTER_VALIDATE_EMAIL) !== false
);

$budeOdhlaseno = count($zpravy);
$zpravyString = implode(";\n", $zpravy);
(new GcMail())
    ->adresati($cfosEmaily ?: ['info@gamecon.cz'])
    ->predmet("Zítra bude hromadně odhlášeno $budeOdhlaseno neplatičů z GC")
    ->text(<<<TEXT
        Zítra Gamecon systém odhlásí $budeOdhlaseno účastníků z letošního Gameconu, protože jsou neplatiči.
        ═══════════════════════════════════════════════════════════════════════════════════════════════════
        $zpravyString
        TEXT
    )
    ->odeslat();

$hromadneOdhlaseniNeplaticu->zalogujCfoNotifikovanOBrzkemHromadnemOdhlaseni(
    $budeOdhlaseno,
    $nejblizsiHromadneOdhlasovaniKdy,
    Uzivatel::zId(Uzivatel::SYSTEM)
);
