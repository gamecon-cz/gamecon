<?php

use Gamecon\Accounting;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Stat;
use Gamecon\Uzivatel\Finance;

$this->blackarrowStyl(true);

/** @var Uzivatel|null $u */
/** @var SystemoveNastaveni $systemoveNastaveni */

if (!$u) { //jen přihlášení
    throw new \Neprihlasen();
}
// texty jsou v katalogu (finance.*), částky a data v jazyce stránky; názvy aktivit, předmětů a poznámky k platbám jsou data
$this->setObsahPrelozeny(true);

if (!FINANCE_VIDITELNE) {
    $urlWebu = urlWebu();
    $nadpis = htmlspecialchars(t('finance.nadpis'));
    $popis = htmlspecialchars(t('finance.skryte.popis'));
    $zpet = htmlSVlozkami(
        t('finance.skryte.zpet', ['stranka' => znackaVlozky('stranka')]),
        ['stranka' => '<i>' . htmlspecialchars(t('finance.skryte.uvodni_stranka')) . '</i>'],
    );
    echo <<<HTML
<div class="stranka">
    <h1>{$nadpis}</h1>

    <p>{$popis}</p>

    <p><a href="{$urlWebu}">{$zpet}</a></p>
</div>
HTML;

    return; // přehled vidí jen přihlášení na GC (a jen po začátku letošních registrací)
}

$jazyk     = AktualniJazyk::jazyk();
$veci      = Accounting::getPersonalFinance($u, showDiscounts: true);
$slevyA    = array_flat('<li>', array_map('htmlspecialchars', $u->finance()->slevyNaAktivity()), '</li>');
$slevyV    = array_flat('<li>', array_map('htmlspecialchars', $u->finance()->slevyVse($jazyk)), '</li>');
$bonusZaVedeniAktivit = Finance::zaokouhli($u->finance()->bonusZaAktivity());
if ($bonusZaVedeniAktivit > 0.0) {
    $slevyA .= '<li>' . htmlSVlozkami(
        t('finance.bonus_za_vedeni_aktivit', ['castka' => znackaVlozky('castka')]),
        ['castka' => Finance::formatujCastkuVKorunachHtml($bonusZaVedeniAktivit)],
    ) . '</li>';
}
$zaplaceno = $u->finance()->stav() >= 0;

$uid = $u->id();
$statUzivatele = $u->stat();
$vychoziTypQrPlatby = match ($statUzivatele) {
    Stat::CZ => 'cz',
    Stat::SK => 'sk',
    default => 'sepa',
};
$jeVychoziCz = $vychoziTypQrPlatby === 'cz';
$jeVychoziSk = $vychoziTypQrPlatby === 'sk';
$jeVychoziSepa = $vychoziTypQrPlatby === 'sepa';

$uvod = htmlSVlozkami(
    t('finance.uvod', [
        'pohlavi'   => $u->pohlavi(),
        'zaplaceni' => znackaVlozky('zaplaceni'),
        'odkaz'     => znackaVlozky('odkaz'),
    ]),
    [
        'zaplaceni' => '<b>' . htmlspecialchars(t('finance.uvod_zaplaceni')) . '</b>',
        'odkaz'     => '<a href="finance#placeni">' . htmlspecialchars(t('finance.uvod_uplne_dole')) . '</a>',
    ],
);
$popisekVyberuQr = htmlspecialchars(t('finance.platba.vyber_qr'));
$nadpisPlatbyCz = htmlspecialchars(t('finance.platba.nadpis_typu', ['typ' => 'CZ']));
$nadpisPlatbySk = htmlspecialchars(t('finance.platba.nadpis_typu', ['typ' => 'SK']));
$nadpisPlatbySepa = htmlspecialchars(t('finance.platba.nadpis_typu', ['typ' => 'SEPA']));
$popisekCislaUctu = htmlspecialchars(t('finance.platba.cislo_uctu'));
$popisekVariabilnihoSymbolu = htmlspecialchars(t('finance.platba.variabilni_symbol'));
$popisekReferencePlatby = htmlspecialchars(t('finance.platba.reference_platby'));
$popisekCastky = htmlspecialchars(t('finance.platba.castka_k_zaplaceni'));
$poznamkaKCastceVEurech = htmlspecialchars(t('finance.platba.castka_v_eurech'));
$preplatek = htmlspecialchars(t('finance.platba.preplatek'));

if (!$zaplaceno) {
    $nejblizsiHromadneOdhlasovaniKdy = $systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy();
    $nejpozdejiZaplatitDo            = $systemoveNastaveni->nejpozdejiZaplatitDo(
        $systemoveNastaveni->ted(),
    );
    $castkaCZ = Finance::formatujCastkuVKorunachHtml(-$u->finance()->stav());
    // Stejná částka (vč. kurzové rezervy), jaká jde do QR kódu, ať se výpis a QR nerozejdou.
    $castkaEUR = Finance::formatujCastkuVEurechHtml($u->finance()->dejCastkuProQrPlatbuVEurech());
    // eurová část se ukáže, až účastník přepne na platbu SK nebo SEPA (soubory/qr-prepinac-plateb.js)
    $platisCelkem = htmlSVlozkami(
        t('finance.platba.platis_celkem', ['castka' => znackaVlozky('castka')]),
        ['castka' => '<strong>' . $castkaCZ . '<span data-qr-eur-text' . ($jeVychoziCz ? ' hidden' : '') . '> / ' . $castkaEUR . '</span></strong>'],
    );
    $eurovaCastka = htmlspecialchars(t('finance.platba.eurova_castka'));
    $zaplatitDo = htmlSVlozkami(
        t('finance.platba.zaplatit_do', ['do' => znackaVlozky('do')]),
        ['do' => '<strong>' . htmlspecialchars(t('finance.platba.do_data', ['datum' => $nejpozdejiZaplatitDo])) . '</strong>'],
    );
    $odhlaseni = htmlSVlozkami(
        t('finance.platba.odhlaseni', ['datum' => znackaVlozky('datum')]),
        ['datum' => '<strong>' . htmlspecialchars(datum3($nejpozdejiZaplatitDo)) . '</strong>'],
    );
}

?>

<div class="stranka">

    <h1><?= htmlspecialchars(t('finance.nadpis')) ?></h1>
    <p><?= $uvod ?></p>


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

        .tabVeci table .objednavky--polozka td:first-child {
            padding-left: 1em;
        }

        .payment-methods {
            display: grid;
            gap: 24px;
            margin-top: 16px;
        }

        .payment-methods__switch {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .payment-methods__switch-button {
            padding: 8px 14px;
            border: 1px solid #E22630;
            background-color: #E22630;
            color: #fff;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            cursor: pointer;
        }

        .payment-methods__switch-button.is-aktivni,
        .payment-methods__switch-button[aria-pressed="true"] {
            border-color: #10111A;
            background-color: #10111A;
        }

        .payment-method {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 16px;
            background: #fafafa;
        }

        .payment-method[hidden] {
            display: none;
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
        <h2><?= htmlspecialchars(t('finance.objednane_veci')) ?></h2>
        <?= $veci->formatForHtml(positivePrices: true) ?>
    </div>

    <?php if ($slevyA || $slevyV) { ?>
        <div style="float:left">
            <h2><?= htmlspecialchars(t('finance.bonusy')) ?></h2>
            <ul><?= trim($slevyA . $slevyV) ?></ul>
        </div>
    <?php } ?>

    <div style="clear:both"></div>

    <?php
    $qrKodProCeskouPlatbu = $u->finance()->dejQrKodProCeskouPlatbu();
    $qrKodProSlovenskouPlatbu = $u->finance()->dejQrKodProSlovenskouPlatbu();
    $qrKodProSepaPlatbu = $u->finance()->dejQrKodProSepaPlatbu();
    ?>

    <?php if (!$zaplaceno): ?>
        <h2 id="placeni"><?= htmlspecialchars(t('finance.platba.nadpis')) ?></h2>
        <div class="payment-methods" data-qr-prepinac-plateb="<?= $vychoziTypQrPlatby ?>">
            <div class="payment-methods__switch" aria-label="<?= $popisekVyberuQr ?>">
                <button type="button"
                        class="payment-methods__switch-button<?= $jeVychoziCz ? ' is-aktivni' : '' ?>"
                        data-qr-tlacitko="cz"
                        aria-pressed="<?= $jeVychoziCz ? 'true' : 'false' ?>">
                    CZ
                </button>
                <button type="button"
                        class="payment-methods__switch-button<?= $jeVychoziSk ? ' is-aktivni' : '' ?>"
                        data-qr-tlacitko="sk"
                        aria-pressed="<?= $jeVychoziSk ? 'true' : 'false' ?>">
                    SK
                </button>
                <button type="button"
                        class="payment-methods__switch-button<?= $jeVychoziSepa ? ' is-aktivni' : '' ?>"
                        data-qr-tlacitko="sepa"
                        aria-pressed="<?= $jeVychoziSepa ? 'true' : 'false' ?>">
                    SEPA
                </button>
            </div>

            <section class="payment-method" data-qr-karta="cz"<?= $jeVychoziCz ? '' : ' hidden' ?>>
                <h3 class="payment-method__title"><?= $nadpisPlatbyCz ?></h3>
                <div class="payment-method__content">
                    <div class="payment-method__details">
                        <strong><?= $popisekCislaUctu ?></strong> <?= UCET_CZ ?><br>
                        <strong><?= $popisekVariabilnihoSymbolu ?></strong> <?= $uid ?><br>
                        <strong><?= $popisekCastky ?></strong> <?= $castkaCZ ?>
                    </div>
                    <?php if ($qrKodProCeskouPlatbu !== null): ?>
                        <div class="payment-method__qr">
                            <img src="<?= $qrKodProCeskouPlatbu->getDataUri() ?>" alt="qrPlatbaCz">
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="payment-method" data-qr-karta="sk"<?= $jeVychoziSk ? '' : ' hidden' ?>>
                <h3 class="payment-method__title"><?= $nadpisPlatbySk ?></h3>
                <div class="payment-method__content">
                    <div class="payment-method__details">
                        <strong>IBAN:</strong> <?= IBAN ?><br>
                        <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                        <strong><?= $popisekReferencePlatby ?></strong> VS:<?= $uid ?><br>
                        <strong><?= $popisekCastky ?></strong> <?= $castkaEUR ?>
                        <br><small class="poznamka"><?= $poznamkaKCastceVEurech ?></small>
                    </div>
                    <?php if ($qrKodProSlovenskouPlatbu !== null): ?>
                        <div class="payment-method__qr">
                            <img src="<?= $qrKodProSlovenskouPlatbu->getDataUri() ?>" alt="qrPlatbaSk">
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="payment-method" data-qr-karta="sepa"<?= $jeVychoziSepa ? '' : ' hidden' ?>>
                <h3 class="payment-method__title"><?= $nadpisPlatbySepa ?></h3>
                <div class="payment-method__content">
                    <div class="payment-method__details">
                        <strong>IBAN:</strong> <?= IBAN ?><br>
                        <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                        <strong><?= $popisekReferencePlatby ?></strong> VS:<?= $uid ?><br>
                        <strong><?= $popisekCastky ?></strong> <?= $castkaEUR ?>
                        <br><small class="poznamka"><?= $poznamkaKCastceVEurech ?></small>
                    </div>
                    <div class="payment-method__qr">
                        <img src="<?= $qrKodProSepaPlatbu->getDataUri() ?>" alt="qrPlatbaSepa">
                    </div>
                </div>
            </section>
        </div>

        <?php if (pred($nejpozdejiZaplatitDo)): ?>
            <p>
                <?= $zaplatitDo ?> <?= $platisCelkem ?>
                <span data-qr-eur-text<?= $jeVychoziCz ? ' hidden' : '' ?>><?= $eurovaCastka ?></span>
            </p>

            <ul class="seznam-bez-okraje">
                <li class="poznamka"><?= $odhlaseni ?></li>
                <li class="poznamka"><?= $preplatek ?></li>
            </ul>
        <?php else: ?>
            <p>
                <?= htmlspecialchars(t('finance.platba.prevodem_nebo_na_miste')) ?> <?= $platisCelkem ?>
                <span data-qr-eur-text<?= $jeVychoziCz ? ' hidden' : '' ?>><?= $eurovaCastka ?></span>
            </p>
            <ul class="seznam-bez-okraje">
                <li class="poznamka"><?= $preplatek ?></li>
            </ul>
        <?php endif; ?>
    <?php else: ?>
        <h2 id="placeni"><?= htmlspecialchars(t('finance.platba.nadpis')) ?></h2>
        <p>
            <?= htmlSVlozkami(
                t('finance.platba.zaplaceno', ['zaplaceny' => znackaVlozky('zaplaceny')]),
                ['zaplaceny' => '<strong style="color:green">' . htmlspecialchars(t('finance.platba.zaplaceny')) . '</strong>'],
            ) ?>
        </p>
        <div class="payment-methods" data-qr-prepinac-plateb="<?= $vychoziTypQrPlatby ?>">
            <div class="payment-methods__switch" aria-label="<?= $popisekVyberuQr ?>">
                <button type="button"
                        class="payment-methods__switch-button<?= $jeVychoziCz ? ' is-aktivni' : '' ?>"
                        data-qr-tlacitko="cz"
                        aria-pressed="<?= $jeVychoziCz ? 'true' : 'false' ?>">
                    CZ
                </button>
                <button type="button"
                        class="payment-methods__switch-button<?= $jeVychoziSk ? ' is-aktivni' : '' ?>"
                        data-qr-tlacitko="sk"
                        aria-pressed="<?= $jeVychoziSk ? 'true' : 'false' ?>">
                    SK
                </button>
                <button type="button"
                        class="payment-methods__switch-button<?= $jeVychoziSepa ? ' is-aktivni' : '' ?>"
                        data-qr-tlacitko="sepa"
                        aria-pressed="<?= $jeVychoziSepa ? 'true' : 'false' ?>">
                    SEPA
                </button>
            </div>

            <section class="payment-method" data-qr-karta="cz"<?= $jeVychoziCz ? '' : ' hidden' ?>>
                <h3 class="payment-method__title"><?= $nadpisPlatbyCz ?></h3>
                <div class="payment-method__content">
                    <div class="payment-method__details">
                        <strong><?= $popisekCislaUctu ?></strong> <?= UCET_CZ ?><br>
                        <strong><?= $popisekVariabilnihoSymbolu ?></strong> <?= $uid ?><br>
                    </div>
                    <?php if ($qrKodProCeskouPlatbu !== null): ?>
                        <div class="payment-method__qr">
                            <img src="<?= $qrKodProCeskouPlatbu->getDataUri() ?>" alt="qrPlatbaCz">
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="payment-method" data-qr-karta="sk"<?= $jeVychoziSk ? '' : ' hidden' ?>>
                <h3 class="payment-method__title"><?= $nadpisPlatbySk ?></h3>
                <div class="payment-method__content">
                    <div class="payment-method__details">
                        <strong>IBAN:</strong> <?= IBAN ?><br>
                        <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                        <strong><?= $popisekReferencePlatby ?></strong> VS:<?= $uid ?><br>
                    </div>
                    <?php if ($qrKodProSlovenskouPlatbu !== null): ?>
                        <div class="payment-method__qr">
                            <img src="<?= $qrKodProSlovenskouPlatbu->getDataUri() ?>" alt="qrPlatbaSk">
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="payment-method" data-qr-karta="sepa"<?= $jeVychoziSepa ? '' : ' hidden' ?>>
                <h3 class="payment-method__title"><?= $nadpisPlatbySepa ?></h3>
                <div class="payment-method__content">
                    <div class="payment-method__details">
                        <strong>IBAN:</strong> <?= IBAN ?><br>
                        <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                        <strong><?= $popisekReferencePlatby ?></strong> VS:<?= $uid ?><br>
                    </div>
                    <div class="payment-method__qr">
                        <img src="<?= $qrKodProSepaPlatbu->getDataUri() ?>" alt="qrPlatbaSepa">
                    </div>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <script src="soubory/qr-prepinac-plateb.js"></script>
