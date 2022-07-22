<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Logger\LogUdalosti;

/**
 * Stránka pro přehled všech přihlášených na aktivitu DrD. DrD je starý modul a
 * přes všechnu snahu je na pozadí black magic!
 *
 * nazev: Seznam na DrD
 * pravo: 102
 * submenu_group: 3
 * submenu_order: 2
 *
 * @var Uzivatel $u
 */

function textMailuOPostupu(Tym $tym, Aktivita $aktivita, bool $html = false) {
    $druzina = $tym->nazev();
    $text = <<<TEXT
Milá družino {$druzina}. Gratulujeme, postoupili jste do dalšího kola turnaje {$aktivita->nazev()}.

tým MDrD
TEXT;
    return $html
        ? preg_replace("~\n~", '<br>', $text)
        : $text;
}

$log = new LogUdalosti();
$vsichniUzDostaliMailOPostupu = function (Aktivita $zakladni, Aktivita $pristiKolo) use ($log) {
    $text = textMailuOPostupu($zakladni->tym(), $pristiKolo);
    foreach ($zakladni->prihlaseni() as $uc) {
        $metadataLogu = ['ucastnik' => $uc->id()];
        if (!$log->existujeLog($text, $metadataLogu, ROK)) {
            return false;
        }
    }
    return true;
};

$predmetMailuOPostupu = 'Gamecon: postoupení družiny v MDrD';

if (post('postoupiliDoSemifinale') || post('postoupiliDoFinale')) {
    $a = Aktivita::zId(post('zakladni'));
    $pristiKolo = Aktivita::zId(post('postoupiliDoSemifinale') ? post('semifinale') : post('finale'));

    $mail = new GcMail();
    $mail->predmet($predmetMailuOPostupu);
    $druzina = $a->tym()->nazev();

    if ($vsichniUzDostaliMailOPostupu($a, $pristiKolo)) {
        varovani("Zpráva o postupu do {$pristiKolo->nazev()} už byla dříve všem z této družiny odeslána");
    } else {
        $emaily = [];
        // E-maily účastníkům
        foreach ($a->prihlaseni() as $uc) {
            $text = textMailuOPostupu($a->tym(), $pristiKolo);
            $metadataLogu = ['ucastnik' => $uc->id()];
            if ($log->existujeLog($text, $metadataLogu, ROK)) {
                continue;
            }
            $mail->text($text);
            $mail->adresat($uc->mail());
            $mail->odeslat();
            $log->zalogovatUdalost($u, $text, $metadataLogu, ROK);
            $emaily[] = $uc->mail();
        }
        oznameni("Odeslána zpráva '$text' na emaily: " . implode(', ', $emaily));
    }
}

if (post('vypadliSemifinale') || post('vypadliFinale')) {
    $a = Aktivita::zId(post('zakladni'));
    $aVypadli = post('vypadliSemifinale') ? Aktivita::zId(post('semifinale')) : Aktivita::zId(post('finale'));

    foreach ($a->prihlaseni() as $uc) {
        $aVypadli->odhlas($uc, $u, Aktivita::BEZ_POKUT);
    }

    $mail = new GcMail();
    $mail->predmet('Gamecon: umístění družiny v MDrD');
    $druzina = $a->tym()->nazev();

    // E-maily účastníkům
    foreach ($a->prihlaseni() as $uc) {
        $mail->text(
            <<<TEXT
            Ahoj {$uc->nickNeboKrestniJmeno()},

            bohužel, tvoje družina {$druzina} nepostoupila do dalšího kola. Herní bloky, původně rezervované pro turnaj MDrD, jsou nyní volné a můžeš se tak přihlásit na jinou aktivitu.

            Díky a s pozdravem,
            tým MDrD
            TEXT
        );
        $mail->adresat($uc->mail());
        $mail->odeslat();
    }

    // E-mail PJovi
    foreach ($a->organizatori() as $pj) {
        $mail->text(
            <<<TEXT
            Ahoj {$pj->jmenoNick()}, tvoje čtvrtfinálová družina {$druzina} nepostoupila do další fáze turnaje MDrD (vypadla v {$aVypadli->nazev()}).

            Toto je informační mail, který obdrželi i hráči z družiny.

            S pozdravem, tým MDrD.
            TEXT
        );
        $mail->adresat($pj->mail());
        $mail->odeslat();
    }

    back();
}

$t = new XTemplate(__DIR__ . '/prihlaseni-drd.xtpl');

$semifinale = [];
$finale = [];
foreach (Aktivita::zFiltru(['typ' => TypAktivity::DRD, 'rok' => ROK]) as $a) {
    if ($a->cenaZaklad() == 0) {
        continue;
    }
    if (!$finale) { // načtení finále a semifinále
        foreach ($a->deti() as $dite) {
            $semifinale[] = $dite;
        }
        foreach ($semifinale[0]->deti() as $dite) {
            $finale[] = $dite;
        }
    }

    // přeskočení aktivit, kde zatím není družina
    $prihlaseni = $a->prihlaseni();
    if (!$prihlaseni) {
        continue; // prázdná aktivita
    }
    if (!$a->tym()) {
        continue; // družina se teprv sestavuje
    }

    // tisk konkrétní družiny / aktivity
    $t->assign('a', $a);
    $uc = null;
    foreach ($prihlaseni as $uc) {
        $t->assign('u', $uc);
        $t->parse('drd.druzina.clen');
    }
    if ($uc && !$semifinale[0]->prihlasen($uc) && !$semifinale[1]->prihlasen($uc)) {
        $t->parse('drd.druzina.zakladni');
    } elseif ($uc && !$finale[0]->prihlasen($uc)) {
        $t->parse('drd.druzina.semifinale');
    } elseif ($finale[0]->uzavrena()) {
        $t->parse('drd.druzina.finale');
    } elseif (!$a->uzavrena()) {
        $t->parse('drd.druzina.neuzavreno');
    } else {
        if ($semifinale[0]->prihlasen($uc)) {
            $t->assign('sfid', $semifinale[0]->id());
        }
        if ($semifinale[1]->prihlasen($uc)) {
            $t->assign('sfid', $semifinale[1]->id());
        }
        if ($finale[0]->prihlasen($uc)) {
            $t->assign('fid', $finale[0]->id());
        }
        if (!$vsichniUzDostaliMailOPostupu($a, $semifinale[0])) {
            $t->assign('textMailuOPostupuDoSemifinale', $predmetMailuOPostupu . '<br><br>' . textMailuOPostupu($a->tym(), $semifinale[0], true));
            $t->parse('drd.druzina.vyber.semifinale');
        }
        if (!$vsichniUzDostaliMailOPostupu($a, $finale[0])) {
            $t->assign('textMailuOPostupuDoFinale', $predmetMailuOPostupu . '<br><br>' . textMailuOPostupu($a->tym(), $finale[0], true));
            $t->parse('drd.druzina.vyber.finale');
        }
        $t->parse('drd.druzina.vyber');
    }
    $t->parse('drd.druzina');
}

$t->assign('zachovejScroll', URL_WEBU . '/soubory/blackarrow/_spolecne/zachovej-scroll.js');
$t->parse('drd');
$t->out('drd');
