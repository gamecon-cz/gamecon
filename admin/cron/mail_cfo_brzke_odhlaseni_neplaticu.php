<?php

use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Report\BfgrReport;
use Gamecon\Shop\Shop;

require_once __DIR__ . '/_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(30);

global $systemoveNastaveni;

$hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

$poradiOznameni = null;
foreach ([1 => '+1 day', 2 => '+1 hour'] as $poradiOznameni => $posun) {
    $overenaPlatnostZpetne           = DateTimeGamecon::overenaPlatnostZpetne($systemoveNastaveni)
        ->modifyStrict($posun); // jako kdybychom bychom pouštěli hromadné odhlašování zítra / za hodinu
    $nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($systemoveNastaveni, $overenaPlatnostZpetne);

    $odhlaseniProvedenoKdy = $hromadneOdhlaseniNeplaticu->odhlaseniProvedenoKdy($nejblizsiHromadneOdhlasovaniKdy);
    if ($odhlaseniProvedenoKdy) {
        logs("Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)}");
        return;
    }

    $cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy = $hromadneOdhlaseniNeplaticu->cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy(
        $nejblizsiHromadneOdhlasovaniKdy,
        $poradiOznameni,
    );
    if ($cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy) {
        logs("{$poradiOznameni}. email pro CFO o brzkém hromadném odhlášení už byl odeslán {$cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy->format(DateTimeCz::FORMAT_DB)}");
        unset($poradiOznameni);
        continue;
    } else {
        break; // tohle oznámení jsme ještě neposlali
    }
}

if (!$poradiOznameni) {
    return;
}

// abychom měli čerstvé informace o neplatičích
require __DIR__ . '/fio_stazeni_novych_plateb.php';

$zpravyNeplatici = [];
try {
    foreach ($hromadneOdhlaseniNeplaticu->neplaticiAKategorie()
             as ['neplatic' => $neplatic, 'kategorie_neplatice' => $kategorieNeplatice]) {
        /** @var \Gamecon\Uzivatel\KategorieNeplatice $kategorieNeplatice */
        /** @var \Uzivatel $neplatic */
        $zpravyNeplatici[] = "Účastník '{$neplatic->jmenoNick()}' ({$neplatic->id()}) bude zítra odhlášen, protože má kategorii neplatiče {$kategorieNeplatice->ciselnaKategoriiNeplatice()}";
    }
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
    return;
}

$zpravyPolozky = [];
foreach (Shop::letosniPolozkySeSpatnymKoncem($systemoveNastaveni) as $polozkaSeSpatnymKoncem) {
    $nabizetDoDleNastaveni = $polozkaSeSpatnymKoncem->doKdyNabizetDleNastaveni($systemoveNastaveni);
    if (!$nabizetDoDleNastaveni || !$polozkaSeSpatnymKoncem->nabizetDo()) {
        trigger_error(
            "Polozka '{$polozkaSeSpatnymKoncem->nazev()}' ({$polozkaSeSpatnymKoncem->idPredmetu()}) je údajně se špatným koncem, ale nemá žádný konec prodeje",
            E_USER_WARNING,
        );
        continue;
    }
    if ($nabizetDoDleNastaveni->getTimestamp() === $polozkaSeSpatnymKoncem->nabizetDo()->getTimestamp()) {
        trigger_error(
            "Polozka '{$polozkaSeSpatnymKoncem->nazev()}' ({$polozkaSeSpatnymKoncem->idPredmetu()}) je údajně se špatným koncem, ale přitom má konec prodeje správně",
            E_USER_WARNING,
        );
        continue;
    }
    if ($nabizetDoDleNastaveni->getTimestamp() > $polozkaSeSpatnymKoncem->nabizetDo()->getTimestamp()) {
        continue; // že se přestala prodávat o něco dřív nás teď nezajímá
    }
    if ($polozkaSeSpatnymKoncem->nabizetDo() < $systemoveNastaveni->ted()) {
        continue; // pokud se už neprodává, tak nás teď nezajímá
    }

    $zpravyPolozky[] = "Položka '{$polozkaSeSpatnymKoncem->nazev()}' ({$polozkaSeSpatnymKoncem->idPredmetu()}) by se měla přestat podávat {$nabizetDoDleNastaveni->formatCasStandard()}, ale bude se prodávat až do {$polozkaSeSpatnymKoncem->nabizetDo()->formatCasStandard()}. Opravte její datum konce prodeje v shopu.";
}

$bfgrSoubor = sys_get_temp_dir() . '/' . uniqid('bfgr-', true) . '.xlsx';
$bfgrReport = new BfgrReport($systemoveNastaveni);
$bfgrReport->exportuj('xlsx', true, $bfgrSoubor);

$cfosEmaily    = Uzivatel::cfosEmaily();
$budeOdhlaseno = count($zpravyNeplatici);
$brzy          = match ($poradiOznameni) {
    1 => 'Zítra',
    2 => 'Za hodinu',
    default => 'Brzy'
};
$uvod          = "$brzy Gamecon systém odhlásí $budeOdhlaseno účastníků z letošního Gameconu, protože jsou neplatiči.";
$oddelovac     = str_repeat('═', mb_strlen($uvod));
$zpravyString  = implode(";\n", $zpravyNeplatici);

if ($zpravyPolozky) {
    $zpravyPolozkyString = implode(";\n", $zpravyPolozky);
    $zpravyString        .= <<<TEXT

$oddelovac

$zpravyPolozkyString
TEXT;
}

(new GcMail($systemoveNastaveni))
    ->adresati($cfosEmaily ?: ['info@gamecon.cz'])
    ->predmet("$brzy bude hromadně odhlášeno $budeOdhlaseno neplatičů z GC")
    ->text(<<<TEXT
        $uvod

        $oddelovac

        $zpravyString
        TEXT,
    )
    ->prilohaSoubor($bfgrSoubor)
    ->odeslat(GcMail::FORMAT_TEXT);

$hromadneOdhlaseniNeplaticu->zalogujNotifikovaniCfoOBrzkemHromadnemOdhlaseni(
    $budeOdhlaseno,
    $nejblizsiHromadneOdhlasovaniKdy,
    $poradiOznameni,
    Uzivatel::zId(Uzivatel::SYSTEM),
);
