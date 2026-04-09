<?php

use Gamecon\Accounting;
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

$veci      = Accounting::getPersonalFinance($u, showDiscounts: true);
$slevyA    = array_flat('<li>', $u->finance()->slevyNaAktivity(), '</li>');
$slevyV    = array_flat('<li>', $u->finance()->slevyVse(), '</li>');
$zaplaceno = $u->finance()->stav() >= 0;
$limit     = false;

$a   = $u->koncovkaDlePohlavi();
$uid = $u->id();
$statUzivatele = $u->stat();
$jeCeskaQrPlatba = $statUzivatele === Stat::CZ;
$jeSlovenskaQrPlatba = $statUzivatele === Stat::SK;
$nadpisZahranicniPlatby = $jeSlovenskaQrPlatba
    ? 'Platba (SK)'
    : 'Platba (SEPA)';

if (!$zaplaceno) {
    $castka                        = -$u->finance()->stav();
    $nejblizsiHromadneOdhlasovaniKdy = $systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy();
    $nejpozdejiZaplatitDo            = $systemoveNastaveni->nejpozdejiZaplatitDo(
        $systemoveNastaveni->ted(),
    );
    $limit                           = datum4($nejpozdejiZaplatitDo);
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

        .payment-methods {
            display: grid;
            gap: 24px;
            margin-top: 16px;
        }

        .payment-method {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 16px;
            background: #fafafa;
        }

        .payment-method__title {
            margin: 0 0 12px;
        }

        .payment-method__content {
            display: grid;
            grid-template-columns: minmax(240px, 1fr) auto;
            gap: 24px;
            align-items: center;
        }

        .payment-method__details {
            line-height: 1.8;
        }

        .payment-method__qr {
            text-align: center;
        }

        .payment-method__qr img {
            display: block;
            width: min(100%, 300px);
            height: auto;
            margin: 0 auto;
        }

        @media (max-width: 760px) {
            .payment-method__content {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div style="float:left;width:250px;margin-bottom:24px; margin-right: 50px" class="tabVeci">
        <h2>Objednané věci</h2>
        <?= $veci->formatForHtml(positivePrices: true) ?>
    </div>

    <?php if ($slevyA || $slevyV) { ?>
        <div style="float:left">
            <h2>Bonusy</h2>
            <ul><?= trim($slevyA . $slevyV) ?></ul>
        </div>
    <?php } ?>

    <div style="clear:both"></div>

    <?php
    $qrKodProCeskouPlatbu = $u->finance()->dejQrKodProCeskouPlatbu();
    $qrKodProSlovenskouPlatbu = $jeSlovenskaQrPlatba
        ? $u->finance()->dejQrKodProSlovenskouPlatbu()
        : null;
    $qrKodProSepaPlatbu = $u->finance()->dejQrKodProSepaPlatbu();
    ?>

    <?php if (!$zaplaceno): ?>
        <h2 id="placeni">Platba</h2>
        <div class="payment-methods">
            <section class="payment-method">
                <h3 class="payment-method__title">Platba (CZ)</h3>
                <div class="payment-method__content">
                    <div class="payment-method__details">
                        <strong>Číslo účtu:</strong> <?= UCET_CZ ?><br>
                        <strong>Variabilní symbol:</strong> <?= $uid ?><br>
                        <strong>Částka k zaplacení:</strong> <?= $castkaCZ ?>
                    </div>
                    <?php if (($jeCeskaQrPlatba || $jeSlovenskaQrPlatba) && $qrKodProCeskouPlatbu !== null): ?>
                        <div class="payment-method__qr">
                            <img src="<?= $qrKodProCeskouPlatbu->getDataUri() ?>" alt="qrPlatbaCz">
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($jeSlovenskaQrPlatba): ?>
                <section class="payment-method" id="placeni-sepa">
                    <h3 class="payment-method__title"><?= $nadpisZahranicniPlatby ?></h3>
                    <div class="payment-method__content">
                        <div class="payment-method__details">
                            <strong>IBAN:</strong> <?= IBAN ?><br>
                            <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                            <strong>Reference platby:</strong> VS<?= $uid ?><br>
                            <strong>Částka k zaplacení:</strong> <?= $castkaEUR ?>
                        </div>
                        <?php if ($qrKodProSlovenskouPlatbu !== null): ?>
                            <div class="payment-method__qr">
                                <img src="<?= $qrKodProSlovenskouPlatbu->getDataUri() ?>" alt="qrPlatbaSk">
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="payment-method" id="placeni-epc">
                <h3 class="payment-method__title">Platba (SEPA)</h3>
                <div class="payment-method__content">
                    <div class="payment-method__details">
                        <strong>IBAN:</strong> <?= IBAN ?><br>
                        <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                        <strong>Reference platby:</strong> VS<?= $uid ?><br>
                        <strong>Částka k zaplacení:</strong> <?= $castkaEUR ?>
                    </div>
                    <div class="payment-method__qr">
                        <img src="<?= $qrKodProSepaPlatbu->getDataUri() ?>" alt="qrPlatbaSepa">
                    </div>
                </div>
            </section>
        </div>

        <?php if (pred($nejpozdejiZaplatitDo)): ?>
            <p>
                GameCon je nutné zaplatit převodem <strong>do <?= $limit ?></strong> (tento den musejí být peníze na účtu GameConu). Platíš celkem
                <strong><?= $castkaCZ . ' / ' . $castkaEUR ?></strong>, přesné údaje o platbě nalezneš výše.
            </p>

            <?php if (pred($nejpozdejiZaplatitDo) && !$u->maPravoNerusitObjednavky()): ?>
                <ul class="seznam-bez-okraje">
                    <li class="poznamka">Při pozdější platbě tě systém dne
                        <strong><?= datum3($nejpozdejiZaplatitDo) ?></strong> automaticky odhlásí.
                    </li>
                    <li class="poznamka">
                        Peníze navíc můžeš využít na přihlášení aktivit na GameConu a přeplatek ti po GameConu rádi vrátíme.
                    </li>
                </ul>
            <?php else: ?>
                <ul class="seznam-bez-okraje">
                    <li class="poznamka">Při pozdější platbě tě systém dne
                        <strong><?= datum3($nejpozdejiZaplatitDo) ?></strong> automaticky odhlásí.
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
        <h2 id="placeni">Platba</h2>
        <p>
            Všechny tvoje pohledávky jsou <strong style="color:green">v pořádku zaplaceny</strong>, není potřeba nic
            platit. Pokud si ale chceš dokupovat aktivity na místě se slevou nebo bez nutnosti používat hotovost,
            můžeš si samozřejmě kdykoli převést peníze do zásoby:
        </p>
        <div class="payment-methods">
            <section class="payment-method">
                <h3 class="payment-method__title">Platba (CZ)</h3>
                <div class="payment-method__content">
                    <div class="payment-method__details">
                        <strong>Číslo účtu:</strong> <?= UCET_CZ ?><br>
                        <strong>Variabilní symbol:</strong> <?= $uid ?><br>
                    </div>
                    <?php if (($jeCeskaQrPlatba || $jeSlovenskaQrPlatba) && $qrKodProCeskouPlatbu !== null): ?>
                        <div class="payment-method__qr">
                            <img src="<?= $qrKodProCeskouPlatbu->getDataUri() ?>" alt="qrPlatbaCz">
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($jeSlovenskaQrPlatba): ?>
                <section class="payment-method" id="placeni-sepa">
                    <h3 class="payment-method__title"><?= $nadpisZahranicniPlatby ?></h3>
                    <div class="payment-method__content">
                        <div class="payment-method__details">
                            <strong>IBAN:</strong> <?= IBAN ?><br>
                            <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                            <strong>Reference platby:</strong> VS<?= $uid ?><br>
                        </div>
                        <?php if ($qrKodProSlovenskouPlatbu !== null): ?>
                            <div class="payment-method__qr">
                                <img src="<?= $qrKodProSlovenskouPlatbu->getDataUri() ?>" alt="qrPlatbaSk">
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="payment-method" id="placeni-epc">
                <h3 class="payment-method__title">Platba (SEPA)</h3>
                <div class="payment-method__content">
                    <div class="payment-method__details">
                        <strong>IBAN:</strong> <?= IBAN ?><br>
                        <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                        <strong>Reference platby:</strong> VS<?= $uid ?><br>
                    </div>
                    <div class="payment-method__qr">
                        <img src="<?= $qrKodProSepaPlatbu->getDataUri() ?>" alt="qrPlatbaSepa">
                    </div>
                </div>
            </section>
        </div>
    <?php endif; ?>
