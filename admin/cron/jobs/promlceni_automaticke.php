<?php

declare(strict_types=1);

use Gamecon\Kanaly\GcMail;
use Gamecon\Logger\JobResultLogger;
use Gamecon\Report\KonfiguraceReportu;
use Gamecon\Uzivatel\PromlceniZustatku;
use Gamecon\Uzivatel\UzivatelKPromlceni;

/** @var bool $znovu */

require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (! $cronNaCas) {
    return;
}

set_time_limit(300);

global $systemoveNastaveni;

// Zkontroluj, jestli je správný čas (1 den po skončení GameConu)
$gcBeziDo = $systemoveNastaveni->gcBeziDo();
$denPoGc = $gcBeziDo->modify('+1 day');
$ted = $systemoveNastaveni->ted();
$output = new JobResultLogger();

// Spustit pouze pokud jsme v rozmezí 1 den po GC (s tolerancí 23 hodin)
if ($ted < $denPoGc) {
    $output->logs(
        sprintf(
            'Automatické promlčení zůstatků: Ještě je brzy. Očekáváno: %s, teď: %s',
            $denPoGc->format('Y-m-d H:i:s'),
            $ted->format('Y-m-d H:i:s'),
        ),
    );

    return;
}

$rocnik = $systemoveNastaveni->rocnik();
$promlceniZustatku = new PromlceniZustatku($systemoveNastaveni, new JobResultLogger());

// Zkontroluj, jestli už nebylo promlčení provedeno
if (! $znovu && ($promlcenoKdy = $promlceniZustatku->automatickaPromlceniProvedenaKdy($rocnik))) {
    $output->logs(
        sprintf(
            'Automatické promlčení zůstatků: Promlčení po ročníku %d už bylo provedeno v %s',
            $rocnik,
            $promlcenoKdy->format('Y-m-d H:i:s'),
        ),
    );

    return;
}

// 1. Najdi uživatele k promlčení
$uzivateleKPromlceni = $promlceniZustatku->najdiUzivateleKPromlceni();

if (count($uzivateleKPromlceni) === 0) {
    $output->logs('Automatické promlčení zůstatků: Žádní uživatelé k promlčení');

    // Pošli CFO informaci, že nikdo nebyl promlčen
    $cfosEmaily = Uzivatel::cfosEmaily();
    (new GcMail($systemoveNastaveni))
        ->adresati($cfosEmaily
            ?: ['info@gamecon.cz'])
        ->predmet("Automatické promlčení zůstatků GC {$rocnik}: 0 promlčených")
        ->text(<<<TEXT
Automatické promlčení zůstatků po skončení GameConu {$rocnik} bylo provedeno.

Výsledek: Žádní uživatelé nesplňovali kritéria pro promlčení zůstatků.

GameCon skončil: {$gcBeziDo->format('d.m.Y H:i')}
TEXT,
        )
        ->odeslat(GcMail::FORMAT_TEXT);

    return;
}

// 2. Pošli CFO report před promlčením
$reportPredPromlcenim = $promlceniZustatku->vytvorCfoReport($uzivateleKPromlceni);
$pocetUzivatelu = count($reportPredPromlcenim);
$celkovaSuma = array_sum(array_column($reportPredPromlcenim, 'promlcena_castka'));

// Vytvoř dočasný XLSX soubor s reportem
$tempFile = tempnam($systemoveNastaveni->privateCacheDir(), 'promlceni_report_') . '.xlsx';
$konfiguraceReportu = (new KonfiguraceReportu())
    ->setRowToFreeze(1)
    ->setColumnsToFreezeUpTo('E')
    ->setMaxGenericColumnWidth(50)
    ->setDestinationFile($tempFile);

Report::zPole($reportPredPromlcenim)->tFormat('xlsx', null, $konfiguraceReportu);

// Snímek zůstatků všech uživatelů před promlčením, aby ho šlo porovnat s finálním reportem
$reportVsechPredPromlcenim = $promlceniZustatku->vytvorCfoReportVsechZustatku();

$tempFileVsechniPred = tempnam($systemoveNastaveni->privateCacheDir(), 'promlceni_report_vsichni_pred_') . '.xlsx';
$konfiguraceReportuVsechniPred = (new KonfiguraceReportu())
    ->setRowToFreeze(1)
    ->setColumnsToFreezeUpTo('C')
    ->setMaxGenericColumnWidth(50)
    ->setDestinationFile($tempFileVsechniPred);

Report::zPole($reportVsechPredPromlcenim)->tFormat('xlsx', null, $konfiguraceReportuVsechniPred);

$pocetVsechUzivatelu = count($reportVsechPredPromlcenim);

// Pošli CFO report o uživatelích, kteří budou promlčeni
$cfosEmaily = Uzivatel::cfosEmaily();
(new GcMail($systemoveNastaveni))
    ->adresati($cfosEmaily
        ?: ['info@gamecon.cz'])
    ->predmet("Automatické promlčení zůstatků GC {$rocnik}: Report před promlčením")
    ->prilohaSoubor($tempFile)
    ->prilohaNazev("promlceni-zustatku-gc-{$rocnik}-pred.xlsx")
    ->prilohaSoubor($tempFileVsechniPred)
    ->prilohaNazev("zustatky-vsech-uzivatelu-pred-promlcenim-gc-{$rocnik}.xlsx")
    ->text(<<<TEXT
Automatické promlčení zůstatků po skončení GameConu {$rocnik} bude nyní provedeno.

Přehled před promlčením:
- Počet uživatelů k promlčení: {$pocetUzivatelu}
- Celková suma k promlčení: {$celkovaSuma} Kč
- Uživatelů v databázi celkem: {$pocetVsechUzivatelu}

V příloze najdete dva reporty:
- promlceni-zustatku-gc-{$rocnik}-pred.xlsx - uživatelé k promlčení, jejich účast na GC a částky
- zustatky-vsech-uzivatelu-pred-promlcenim-gc-{$rocnik}.xlsx - zůstatky všech uživatelů v databázi

GameCon skončil: {$gcBeziDo->format('d.m.Y H:i')}
TEXT,
    )
    ->odeslat(GcMail::FORMAT_HTML);

foreach ([$tempFile, $tempFileVsechniPred] as $docasnySoubor) {
    if (file_exists($docasnySoubor)) {
        @unlink($docasnySoubor);
    }
}
unset($tempFile, $tempFileVsechniPred);

// 3. Promlč zůstatky
$idsUzivatelu = array_map(fn (
    UzivatelKPromlceni $u,
) => $u->uzivatel->id(), $uzivateleKPromlceni);
$vysledek = $promlceniZustatku->promlcZustatky($idsUzivatelu, Uzivatel::SYSTEM);

// 4. Zaloguj automatické promlčení do databáze
$promlceniZustatku->zalogujAutomatickePromlceni($rocnik, $vysledek['pocet'], $vysledek['suma']);

// 5. Načti aktuální stav všech uživatelů v databázi pro finální report
$reportPoPromlceni = $promlceniZustatku->vytvorCfoReportVsechZustatku();

// Vytvoř XLSX s aktuálním stavem všech uživatelů
$tempFileAktualni = tempnam($systemoveNastaveni->privateCacheDir(), 'promlceni_report_aktualni_') . '.xlsx';
$konfiguraceReportuAktualni = (new KonfiguraceReportu())
    ->setRowToFreeze(1)
    ->setColumnsToFreezeUpTo('C')
    ->setMaxGenericColumnWidth(50)
    ->setDestinationFile($tempFileAktualni);

Report::zPole($reportPoPromlceni)->tFormat('xlsx', null, $konfiguraceReportuAktualni);

// 6. Pošli CFO finální report po promlčení
(new GcMail($systemoveNastaveni))
    ->adresati($cfosEmaily
        ?: ['info@gamecon.cz'])
    ->predmet("Automatické promlčení zůstatků GC {$rocnik}: DOKONČENO")
    ->prilohaSoubor($tempFileAktualni)
    ->prilohaNazev("zustatky-vsech-uzivatelu-po-promlceni-gc-{$rocnik}.xlsx")
    ->text(<<<TEXT
Automatické promlčení zůstatků po skončení GameConu {$rocnik} bylo úspěšně dokončeno.

Výsledek promlčení:
- Promlčeno uživatelů: {$vysledek['pocet']}
- Celková promlčená suma: {$vysledek['suma']} Kč

V příloze najdete aktuální report zůstatků všech uživatelů v databázi po promlčení.

GameCon skončil: {$gcBeziDo->format('d.m.Y H:i')}
TEXT,
    )
    ->odeslat(GcMail::FORMAT_HTML);

if (isset($tempFileAktualni) && file_exists($tempFileAktualni)) {
    @unlink($tempFileAktualni);
}

$output->logs("Automatické promlčení zůstatků: Dokončeno. Promlčeno {$vysledek['pocet']} uživatelů, celkem {$vysledek['suma']} Kč");
