<?php

declare(strict_types=1);

use Gamecon\Uzivatel\PromlceniZustatku;
use Gamecon\Kanaly\GcMail;
use Gamecon\Report\KonfiguraceReportu;
use Gamecon\Uzivatel\UzivatelKPromlceni;
use Gamecon\Logger\JobResultLogger;

/** @var bool $znovu */

require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(300);

global $systemoveNastaveni;

// Zkontroluj, jestli je správný čas (1 den po skončení GameConu)
$gcBeziDo = $systemoveNastaveni->gcBeziDo();
$denPoGc  = $gcBeziDo->modify('+1 day');
$ted      = $systemoveNastaveni->ted();
$output   = new JobResultLogger();

// Spustit pouze pokud jsme v rozmezí 1 den po GC (s tolerancí 23 hodin)
if ($ted < $denPoGc) {
    $output->logs(
        sprintf(
            'Automatické promlčení zůstatků: Ještě je brzy. Očekáváno: %s, ted: %s',
            $denPoGc->format('Y-m-d H:i:s'),
            $ted->format('Y-m-d H:i:s'),
        ),
    );

    return;
}

$rocnik            = $systemoveNastaveni->rocnik();
$promlceniZustatku = new PromlceniZustatku($systemoveNastaveni, new JobResultLogger());

// Zkontroluj, jestli už nebylo promlčení provedeno
if ($promlceniZustatku->automatickaPromlceniProvedenaKdy($rocnik) && !$znovu) {
    $output->logs('Automatické promlčení zůstatků: Promlčení už bylo provedeno pro rocnik ' . $rocnik);

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
        ->predmet("Automatické promlčení zůstatků GC $rocnik: 0 promlčených")
        ->text(<<<TEXT
Automatické promlčení zůstatků po skončení GameConu $rocnik bylo provedeno.

Výsledek: Žádní uživatelé nesplňovali kritéria pro promlčení zůstatků.

GameCon skončil: {$gcBeziDo->format('d.m.Y H:i')}
TEXT,
        )
        ->odeslat(GcMail::FORMAT_TEXT);

    return;
}

// 2. Pošli CFO report před promlčením
$reportPredPromlcenim = $promlceniZustatku->vytvorCfoReport($uzivateleKPromlceni);
$pocetUzivatelu       = count($reportPredPromlcenim);
$celkovaSuma          = array_sum(array_column($reportPredPromlcenim, 'promlcena_castka'));

// Vytvoř dočasný XLSX soubor s reportem
$tempFile           = tempnam($systemoveNastaveni->cacheDir(), 'promlceni_report_') . '.xlsx';
$konfiguraceReportu = (new KonfiguraceReportu())
    ->setRowToFreeze(1)
    ->setColumnsToFreezeUpTo('E')
    ->setMaxGenericColumnWidth(50)
    ->setDestinationFile($tempFile);

Report::zPole($reportPredPromlcenim)->tFormat('xlsx', null, $konfiguraceReportu);

// Pošli CFO report o uživatelích, kteří budou promlčeni
$cfosEmaily = Uzivatel::cfosEmaily();
(new GcMail($systemoveNastaveni))
    ->adresati($cfosEmaily
        ?: ['info@gamecon.cz'])
    ->predmet("Automatické promlčení zůstatků GC $rocnik: Report před promlčením")
    ->prilohaSoubor($tempFile)
    ->prilohaNazev("promlceni-zustatku-gc-$rocnik-pred.xlsx")
    ->text(<<<TEXT
Automatické promlčení zůstatků po skončení GameConu $rocnik bude nyní provedeno.

Přehled před promlčením:
- Počet uživatelů: $pocetUzivatelu
- Celková suma k promlčení: $celkovaSuma Kč

V příloze najdete detailní report se všemi uživateli, jejich účastí na GC a částkami k promlčení.

GameCon skončil: {$gcBeziDo->format('d.m.Y H:i')}
TEXT,
    )
    ->odeslat(GcMail::FORMAT_HTML);

if (isset($tempFile) && file_exists($tempFile)) {
    @unlink($tempFile);
}
unset($tempFile);

// 3. Promlč zůstatky
$idsUzivatelu = array_map(fn(
    UzivatelKPromlceni $u,
) => $u->uzivatel->id(), $uzivateleKPromlceni);
$vysledek     = $promlceniZustatku->promlcZustatky($idsUzivatelu, Uzivatel::SYSTEM);

// 4. Zaloguj automatické promlčení do databáze
$promlceniZustatku->zalogujAutomatickePromlceni($rocnik, $vysledek['pocet'], $vysledek['suma']);

// 5. Načti aktuální stav všech uživatelů v databázi pro finální report
$reportPoPromlceni = dbFetchAll(<<<SQL
SELECT
    uzivatele_hodnoty.id_uzivatele,
    uzivatele_hodnoty.login_uzivatele AS nick,
    uzivatele_hodnoty.jmeno_uzivatele,
    uzivatele_hodnoty.prijmeni_uzivatele,
    uzivatele_hodnoty.email1_uzivatele AS email,
    uzivatele_hodnoty.zustatek AS aktualni_zustatek
FROM uzivatele_hodnoty
SQL,
);

// Vytvoř XLSX s aktuálním stavem všech uživatelů
$tempFileAktualni           = tempnam($systemoveNastaveni->cacheDir(), 'promlceni_report_aktualni_') . '.xlsx';
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
    ->predmet("Automatické promlčení zůstatků GC $rocnik: DOKONČENO")
    ->prilohaSoubor($tempFileAktualni)
    ->prilohaNazev("zustatky-vsech-uzivatelu-po-promlceni-gc-$rocnik.xlsx")
    ->text(<<<TEXT
Automatické promlčení zůstatků po skončení GameConu $rocnik bylo úspěšně dokončeno.

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
