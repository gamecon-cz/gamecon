<?php

declare(strict_types=1);

use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Report\BfgrReport;
use Gamecon\Shop\Shop;

/** @var bool $znovu */

require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(30);

global $systemoveNastaveni;

$hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

$poradiOznameni = null;
// jako kdybychom bychom pouštěli hromadné odhlašování zítra / za hodinu
$posuny = [1 => '+1 hour', 2 => '+1 day'];
foreach ($posuny as $poradiOznameni => $posun) {
    // právě teď nebo před 23 hodinami
    $overenaPlatnostZpetne           = DateTimeGamecon::overenaPlatnostZpetne($systemoveNastaveni)
        ->modifyStrict($posun);
    $nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy(
        $systemoveNastaveni,
        $overenaPlatnostZpetne,
    );

    if ($nejblizsiHromadneOdhlasovaniKdy > $systemoveNastaveni->ted()->modify($posun)) {
        // POJISTKA PROTI PŘÍLIŽ BRZKÉMU SPUŠTĚNÍ
        logs("E-mail pro CFO se seznamem neplatičů: Hromadné odhlášení s posunem '$posun' bude až za dlouhou dobu, {$nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)} ({$nejblizsiHromadneOdhlasovaniKdy->relativniVBudoucnu()}). Přeskakuji.");
        $poradiOznameni = null;
        continue;
    }

    logs("E-mail pro CFO se seznamem neplatičů: zkouším {$nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)} ({$nejblizsiHromadneOdhlasovaniKdy->relativniVBudoucnu()}) (posun '$posun')");

    if ($znovu && !$systemoveNastaveni->jsmeNaOstre()) {
        break; // zkusíme hned
    }

    $odhlaseniProvedenoKdy = $hromadneOdhlaseniNeplaticu->odhlaseniProvedenoKdy($nejblizsiHromadneOdhlasovaniKdy);
    if ($odhlaseniProvedenoKdy) { // chceme informovat, že odhlášení bude, ne že bylo - tady končíme
        logs("Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)} ({$odhlaseniProvedenoKdy->relativni()}). Už nemá smysl informovat CFO o blížícím se odhlašování.");
        return;
    }

    $cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy = $hromadneOdhlaseniNeplaticu->cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy(
        $nejblizsiHromadneOdhlasovaniKdy,
        $poradiOznameni,
    );
    if (!$cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy) {
        break; // tohle oznámení jsme ještě neposlali
    }
    logs("{$poradiOznameni}. email pro CFO o brzkém hromadném odhlášení už byl odeslán {$cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy->format(DateTimeCz::FORMAT_DB)}");
    $poradiOznameni = null;
}

if (!$poradiOznameni) {
    return;
}

unset($posun);

// abychom měli čerstvé informace o neplatičích
requireOnceIsolated(__DIR__ . '/../fio_stazeni_novych_plateb.php');

$zpravyNeplatici = [];
try {
    $finalniPosun                    = $posuny[$poradiOznameni];
    $overenaPlatnostZpetne           = DateTimeGamecon::overenaPlatnostZpetne($systemoveNastaveni)
        ->modifyStrict($finalniPosun); // jako kdybychom bychom pouštěli hromadné odhlašování zítra / za hodinu
    $nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy(
        $systemoveNastaveni,
        $overenaPlatnostZpetne,
    );
    $kDatu                           = $systemoveNastaveni->ted()->modify($finalniPosun);
    $neplaticiAKategorie             = $hromadneOdhlaseniNeplaticu->neplaticiAKategorie(
        $nejblizsiHromadneOdhlasovaniKdy,
        null,
        $kDatu,
    );
    foreach ($neplaticiAKategorie as ['neplatic' => $neplatic, 'kategorie_neplatice' => $kategorieNeplatice]) {
        /** @var \Gamecon\Uzivatel\KategorieNeplatice $kategorieNeplatice */
        /** @var \Uzivatel $neplatic */
        $zpravyNeplatici[] = "Účastník '{$neplatic->jmenoNick()}' ({$neplatic->id()}) bude {$nejblizsiHromadneOdhlasovaniKdy->relativniVBudoucnu()} odhlášen, protože má kategorii neplatiče {$kategorieNeplatice->ciselnaKategoriiNeplatice()}";
    }
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
    logs("{$nevhodnyCasProHromadneOdhlasovani->getMessage()}.\nE-mail pro CFO se seznamem neplatičů, kterým hrozí odhlášení, necháme na příští běh CRONu.");
    // POJISTKA PROTI PŘÍLIŽ BRZKÉMU NEBO POZDNÍMU SPUŠTĚNÍ
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
// TODO tohle podle $posun a ne takhle natvrdo
$brzy         = match ($poradiOznameni) {
    1 => 'Zítra',
    2 => 'Za hodinu',
    default => 'Brzy'
};
$uvod         = "$brzy Gamecon systém odhlásí $budeOdhlaseno účastníků z letošního Gameconu, protože jsou neplatiči.";
$oddelovac    = count($zpravyNeplatici) > 0
    ? str_repeat('═', mb_strlen($uvod))
    : '';
$zpravyString = implode(";\n", $zpravyNeplatici);

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

logs('E-mail pro CFO se seznamem neplatičů: e-mail odeslán');
