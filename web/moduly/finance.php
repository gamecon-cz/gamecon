<?php

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Stat;

$this->blackarrowStyl(true);

/** @var Uzivatel|null $u */
/** @var SystemoveNastaveni $systemoveNastaveni */

if (!$u) { //jen přihlášení
    throw new \Neprihlasen();
}
if (!FINANCE_VIDITELNE) {
    $urlWebu = URL_WEBU;
    echo <<<HTML
<div class="stranka">
    <h1>Přehled financí</h1>

    <p>Zde by byl seznam všech položek, které sis letos na GameConu objednal(a) a tvůj celkový stav financí.</p>

    <p><a href="{$urlWebu}">Zpět na <i>Úvodní stránku</i></a></p>
</div>
HTML;

    return; // přehled vidí jen přihlášení na GC (a jen po začátku letošních registrací)
}

$veci      = $u->finance()->prehledHtml();
$slevyA    = array_flat('<li>', $u->finance()->slevyNaAktivity(), '</li>');
$slevyV    = array_flat('<li>', $u->finance()->slevyVse(), '</li>');
$zaplaceno = $u->finance()->stav() >= 0;
$limit     = false;

$a   = $u->koncovkaDlePohlavi();
$uid = $u->id();

if (!$zaplaceno) {
    $castka                        = -$u->finance()->stav();
    $nejblizsiHromadneOdhlasovaniKdy = $systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy();
    $nejpozdejiZaplatitDo            = $systemoveNastaveni->tretiVlnaKdy();
    $limit                           = datum4($nejpozdejiZaplatitDo)    ;
    $castkaCZ = $castka . '&thinsp;Kč';
    $castkaEUR = round($castka / KURZ_EURO, 2) . '&thinsp;€';
}

?>

<div class="stranka">

    <h1>Přehled financí</h1>
    <p>V následujícím přehledu vidíš seznam všech položek, které sis na GameConu objednal<?= $a ?>, s výslednými cenami
        po započítání všech slev. Pokud je tvůj celkový stav financí záporný, pokyny k <b>zaplacení</b> najdeš <a
            href="finance#placeni">úplně dole</a>.</p>


    <style>
        .tabVeci table {
            border-collapse: collapse;
        }

        .tabVeci table td {
            border-bottom: solid 1px #ddd;
            padding-right: 5px;
        }

        .tabVeci table td:last-child {
            width: 20px;
        }
    </style>
    <div style="float:left;width:250px;margin-bottom:24px; margin-right: 50px" class="tabVeci">
        <h2>Objednané věci</h2>
        <?= $veci ?>
    </div>

    <?php if ($slevyA || $slevyV) { ?>
        <div style="float:left">
            <h2>Bonusy</h2>
            <?php if ($slevyA || $slevyV) { ?>
                <ul><?= trim($slevyA . $slevyV) ?></ul>
            <?php } ?>
        </div>
    <?php } ?>

    <div style="clear:both"></div>

    <?php
    $qrKodProPlatbu = $u->finance()->dejQrKodProPlatbu();
    ?>

    <?php if (!$zaplaceno): ?>
        <h2 id="placeni">Platba (CZ)</h2>
        <div>
            <strong>Číslo účtu:</strong> <?= UCET_CZ ?><br>
            <strong>Variabilní symbol:</strong> <?= $uid ?><br>
            <strong>Částka k zaplacení:</strong> <?= $castkaCZ ?>
        </div>

        <?php if ($qrKodProPlatbu !== null): ?>
            <div style="text-align: center; margin-top: 16px">
                <img src="<?= $qrKodProPlatbu->getDataUri() ?>" alt="qrPlatba">
            </div>
        <?php endif; ?>

        <?php if ($u->stat() !== Stat::CZ): ?>
            <h2 id="placeni-sepa">Platba (SEPA)</h2>
            <div>
                <strong>IBAN:</strong> <?= IBAN ?><br>
                <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                <strong>Poznámka pro příjemce:</strong> /VS/<?= $uid ?> <i>(včetně lomítek)</i><br>
                <strong>Částka k zaplacení:</strong> <?= $castkaEUR ?>
            </div>
        <?php endif; ?>

        <?php if (pred($nejblizsiHromadneOdhlasovaniKdy)): ?>
            <p>
                GameCon je nutné zaplatit převodem <strong>do <?= $limit ?></strong> (tento den musejí být peníze na účtu GameConu). Platíš celkem
                <strong><?= $castkaCZ . ' / ' . $castkaEUR ?></strong>, přesné údaje o platbě nalezneš výše.
            </p>

            <?php if (pred($systemoveNastaveni->prvniHromadneOdhlasovani()) && !$u->maPravoNerusitObjednavky()): ?>
                <ul class="seznam-bez-okraje">
                    <li class="poznamka">Při pozdější platbě tě systém dne
                        <strong><?= datum3($systemoveNastaveni->prvniHromadneOdhlasovani()) ?></strong> automaticky odhlásí.
                    </li>
                    <li class="poznamka">
                        Peníze navíc můžeš využít na přihlášení aktivit na GameConu a přeplatek ti po GameConu rádi vrátíme.
                    </li>
                </ul>
            <?php else: ?>
                <ul class="seznam-bez-okraje">
                    <li class="poznamka">Při pozdější platbě tě systém dne
                        <strong><?= datum3($nejblizsiHromadneOdhlasovaniKdy) ?></strong> automaticky odhlásí.
                    </li>
                    <li class="poznamka">
                        Peníze navíc můžeš využít na přihlášení aktivit na GameConu a přeplatek ti po GameConu rádi vrátíme.
                    </li>
                </ul>
            <?php endif; ?>
        <?php else: ?>
            <p>
                Zaplatit můžeš převodem nebo na místě. Platíš celkem
                <strong><?= $castkaCZ . ' / ' . $castkaEUR ?></strong>, přesné údaje o platbě nalezneš výše.
            </p>
            <ul class="seznam-bez-okraje">
                <li class="poznamka">
                    Peníze navíc můžeš využít na přihlášení aktivit na GameConu a přeplatek ti po GameConu rádi vrátíme.
                </li>
            </ul>
        <?php endif; ?>
    <?php else: ?>
        <h2 id="placeni">Platba (CZ)</h2>
        <p>
            Všechny tvoje pohledávky jsou <strong style="color:green">v pořádku zaplaceny</strong>, není potřeba nic
            platit. Pokud si ale chceš dokupovat aktivity na místě se slevou nebo bez nutnosti používat hotovost,
            můžeš si samozřejmě kdykoli převést peníze do zásoby:
        </p>
        <div>
            <strong>Číslo účtu:</strong> <?= UCET_CZ ?><br>
            <strong>Variabilní symbol:</strong> <?= $uid ?><br>
        </div>

        <?php if ($qrKodProPlatbu !== null): ?>
            <div style="text-align: center; margin-top: 16px">
                <img src="<?= $qrKodProPlatbu->getDataUri() ?>" alt="qrPlatba">
            </div>
        <?php endif; ?>

        <?php if ($u->stat() !== Stat::CZ): ?>
            <h2 id="placeni-sepa">Platba (SEPA)</h2>
            <div>
                <strong>IBAN:</strong> <?= IBAN ?><br>
                <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                <strong>Poznámka pro příjemce:</strong> /VS/<?= $uid ?> <i>(včetně lomítek)</i><br>
            </div>
        <?php endif; ?>
    <?php endif; ?>
