<?php

declare(strict_types=1);

use Gamecon\Uzivatel\PromlceniZustatku;
use Gamecon\Kanaly\GcMail;

/** @var bool $znovu */

require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(60);

global $systemoveNastaveni;

// Zkontroluj, jestli je správný čas (1 měsíc před otevřením registrací)
$regGcOd = $systemoveNastaveni->prihlasovaniUcastnikuOd($systemoveNastaveni->rocnik());
$mesicPredRegistraci = $regGcOd->modify('-1 month');
$ted = $systemoveNastaveni->ted();

// Spustit pouze pokud jsme v rozmezí 1 týden před registracemi (s tolerancí 23 hodin)
if ($ted > $mesicPredRegistraci->modify('+23 hours') /* příliš brzy */ || $ted < $mesicPredRegistraci /* příliš pozdě */) {
    logs('Varovné e-maily o promlčení (1 měsíc): Není správný čas. Očekáváno: ' . $mesicPredRegistraci->format('Y-m-d H:i:s') . ', ted: ' . $ted->format('Y-m-d H:i:s'));
    return;
}

$promlceniZustatku = new PromlceniZustatku($systemoveNastaveni);

// Zkontroluj, jestli už nebyly e-maily odeslány
$rocnik = $systemoveNastaveni->rocnik();
if ($promlceniZustatku->varovaniMesicOdeslanoKdy($rocnik) && !$znovu) {
    logs('Varovné e-maily o promlčení (1 měsíc): E-maily už byly odeslány pro rocnik ' . $rocnik);
    return;
}
$uzivatele = $promlceniZustatku->najdiUzivateleKPromlceni();

if (count($uzivatele) === 0) {
    logs('Varovné e-maily o promlčení (1 měsíc): Žádní uživatelé k varování');
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

    $predmet = "GameCon $rocnik - Tvůj zůstatek {$zustatek} Kč bude promlčen";

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

máš na GameCon účtu zůstatek $zustatek Kč, ale {$posledniRoky} jsi se GameConu nezúčastnil{$a}.

Hrozí promlčení zůstatku na Tvém GameCon účtu kvůli tvé neaktivitě.

Zůstatek bude promlčen krátce po skončení letošního GameConu $rocnik.

Co můžeš proti tomu udělat:
- Registrovat se na letošní GameCon $rocnik (registrace začnou {$regGcOd->format('d.m.Y')})
- Kontaktovat nás na info@gamecon.cz a domluvit se, kam chceš zůstatek vrátit

Tvůj zůstatek: $zustatek Kč

Děkujeme za pochopení!
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
$promlceniZustatku->zalogujVarovaniMesic($rocnik, $pocetOdeslanychEmailu);

// Poslat CFO informaci o počtu odeslaných e-mailů
$cfosEmaily = Uzivatel::cfosEmaily();
(new GcMail($systemoveNastaveni))
    ->adresati($cfosEmaily ?: ['info@gamecon.cz'])
    ->predmet("Varovné e-maily o promlčení zůstatků: odesláno $pocetOdeslanychEmailu e-mailů")
    ->text(<<<TEXT
Varovné e-maily o promlčení zůstatků (1 měsíc před otevřením registrací) byly odeslány.

Počet uživatelů: $pocetOdeslanychEmailu
Registrace začínají: {$regGcOd->format('d.m.Y H:i')}
Počet let neúčasti: $pocetLet

Uživatelé byli varováni, že jejich zůstatky budou promlčeny po skončení GameConu $rocnik, pokud se nezúčastní.
TEXT
    )
    ->odeslat(GcMail::FORMAT_TEXT);

logs("Varovné e-maily o promlčení (1 měsíc): Odesláno $pocetOdeslanychEmailu e-mailů");
