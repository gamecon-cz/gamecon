<?php

declare(strict_types=1);

use Gamecon\Uzivatel\PromlceniZustatku;
use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;

/** @var bool $znovu */

require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(60);

global $systemoveNastaveni;

// Zkontroluj, jestli je správný čas (1 týden před otevřením registrací)
$regGcOd = $systemoveNastaveni->prihlasovaniUcastnikuOd($systemoveNastaveni->rocnik());
$tydenPredRegistraci = $regGcOd->modify('-1 week');
$ted = $systemoveNastaveni->ted();

// Spustit pouze pokud jsme v rozmezí 1 týden před registracemi (s tolerancí 23 hodin)
if ($ted < $tydenPredRegistraci || $ted > $tydenPredRegistraci->modify('+23 hours')) {
    logs('Varovné e-maily o promlčení (1 týden): Není správný čas. Očekáváno: ' . $tydenPredRegistraci->format('Y-m-d H:i:s') . ', ted: ' . $ted->format('Y-m-d H:i:s'));
    return;
}

$promlceniZustatku = new PromlceniZustatku($systemoveNastaveni);

// Zkontroluj, jestli už nebyly e-maily odeslány
$rocnik = $systemoveNastaveni->rocnik();
if ($promlceniZustatku->varovaniTydenOdeslanoKdy($rocnik) && !$znovu) {
    logs('Varovné e-maily o promlčení (1 týden): E-maily už byly odeslány pro rocnik ' . $rocnik);
    return;
}
$uzivatele = $promlceniZustatku->najdiUzivateleKPromlceni();

if (count($uzivatele) === 0) {
    logs('Varovné e-maily o promlčení (1 týden): Žádní uživatelé k varování');
    return;
}

$rocnik = $systemoveNastaveni->rocnik();
$pocetLet = PromlceniZustatku::getPocetLetNeplatnosti();
$pocetOdeslanychEmailu = 0;

foreach ($uzivatele as $uzivatelKPromlceni) {
    $uzivatel = $uzivatelKPromlceni->uzivatel;
    if (!$uzivatel->mail()) {
        continue;
    }

    $zustatek = (int)$uzivatel->finance()->stav();
    $jmeno = $uzivatel->jmenoNick();

    $predmet = "PŘIPOMÍNKA: GameCon $rocnik - Tvůj zůstatek {$zustatek} Kč bude promlčen";

    $pattern = <<<ICU
        {pocetLet, plural,
            one {poslední # rok}
            few {poslední # roky}
            other {posledních # let}
        }
        ICU;
    $formatter = new \MessageFormatter('cs_CZ', $pattern);
    $posledniRoky = trim($formatter->format(['pocetLet' => $pocetLet]));
    $a = $uzivatel->koncovkaDlePohlavi();

    $zprava = <<<TEXT
Ahoj $jmeno,

toto je připomínka našeho předchozího e-mailu.

Máš na GameCon účtu zůstatek $zustatek Kč, ale {$posledniRoky} jsi se GameConu nezúčastnil{$a}.

REGISTRACE UŽ ZA TÝDEN!
Registrace na GameCon $rocnik začínají {$regGcOd->formatCasZacatekUdalosti()}.

Pokud se nezaregistruješ a nezúčastníš se letošního GameConu, tvůj zůstatek bude promlčen krátce po skončení akce.

Co můžeš udělat:
- Registrovat se na letošní GameCon $rocnik
- Kontaktovat nás na info@gamecon.cz, pokud máš dotazy

Tvůj zůstatek: $zustatek Kč

Děkujeme!
Tým GameConu
TEXT;

    (new GcMail($systemoveNastaveni))
        ->adresat($uzivatel->mail())
        ->predmet($predmet)
        ->text($zprava)
        ->odeslat(GcMail::FORMAT_TEXT);

    $pocetOdeslanychEmailu++;
    set_time_limit(10); // Prodloužit timeout pro každý e-mail
}

// Zaloguj odeslání do databáze
$promlceniZustatku->zalogujVarovaniTyden($rocnik, $pocetOdeslanychEmailu);

// Poslat CFO informaci o počtu odeslaných e-mailů
$cfosEmaily = Uzivatel::cfosEmaily();
(new GcMail($systemoveNastaveni))
    ->adresati($cfosEmaily ?: ['info@gamecon.cz'])
    ->predmet("Připomínka promlčení zůstatků: odesláno $pocetOdeslanychEmailu e-mailů")
    ->text(<<<TEXT
Připomínkové e-maily o promlčení zůstatků (1 týden před otevřením registrací) byly odeslány.

Počet uživatelů: $pocetOdeslanychEmailu
Registrace začínají: {$regGcOd->format('d.m.Y H:i')}
Počet let neúčasti: $pocetLet

Uživatelé byli znovu upozorněni, že jejich zůstatky budou promlčeny po skončení GameConu $rocnik, pokud se nezúčastní.
TEXT
    )
    ->odeslat(GcMail::FORMAT_TEXT);

logs("Varovné e-maily o promlčení (1 týden): Odesláno $pocetOdeslanychEmailu e-mailů");
