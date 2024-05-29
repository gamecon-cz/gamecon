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
$slevyA    = array_flat('<li>', $u->finance()->slevyAktivity(), '</li>');
$slevyV    = array_flat('<li>', $u->finance()->slevyVse(), '</li>');
$zaplaceno = $u->finance()->stav() >= 0;
$limit     = false;

$a   = $u->koncovkaDlePohlavi();
$uid = $u->id();

if (!$zaplaceno) {
    $castka                          = -$u->finance()->stav();
    $nejblizsiHromadneOdhlasovaniKdy = $systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy();
    $nejpozdejiZaplatitDo            = $systemoveNastaveni->nejpozdejiZaplatitDo();
    $limit                           = datum3($nejpozdejiZaplatitDo);
    if ($u->stat() == 'CZ') {
        $castka .= '&thinsp;Kč';
    } else {
        $castka = round($castka / KURZ_EURO, 2) . '&thinsp;€';
    }
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

    <?php if ($u && $u->jeOrganizator()) { ?>
        <div style="float:left">
            <h2>Slevy</h2>
            <?php if ($slevyA) { ?>
                <strong>Použité slevy na aktivity</strong>
                <ul><?= $slevyA ?></ul>
            <?php } ?>
            <?php if ($slevyV) { ?>
                <strong>Další bonusy</strong> (pokud si je objednáš)
                <ul><?= $slevyV ?></ul>
            <?php } ?>
        </div>
    <?php } ?>

    <div style="clear:both"></div>

    <?php if (!$zaplaceno) { ?>
        <?php if ($u->stat() == Stat::CZ) { ?>
            <h2 id="placeni">Platba</h2>
            <div>
                <strong>Číslo účtu:</strong> <?= UCET_CZ ?><br>
                <strong>Variabilní symbol:</strong> <?= $uid ?><br>
                <strong>Částka k zaplacení:</strong> <?= $castka ?>
            </div>
        <?php } else { ?>
            <h2 id="placeni">Platba (SEPA)</h2>
            <div>
                <strong>IBAN:</strong> <?= IBAN ?><br>
                <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                <strong>Poznámka pro příjemce:</strong> /VS/<?= $uid ?> <i>(včetně lomítek)</i><br>
                <strong>Částka k zaplacení:</strong> <?= $castka ?>
            </div>
        <?php } ?>

        <?php if (pred($nejblizsiHromadneOdhlasovaniKdy)) { ?>
            <?php if ($u->stat() === Stat::CZ) { ?>
                <p>GameCon je nutné zaplatit převodem <strong>do <?= $limit ?></strong>. Platíš celkem
                    <strong><?= $castka ?></strong>, variabilní symbol je tvoje ID <strong><?= $uid ?></strong>.</p>
            <?php } else { ?>
                <p>GameCon je nutné zaplatit převodem <strong>do <?= $limit ?></strong>. Platíš celkem
                    <strong><?= $castka ?></strong>, přesné údaje o platbě nalezneš výše.</p>
            <?php } ?>
            <?php if (pred($systemoveNastaveni->prvniHromadneOdhlasovani())) { ?>
                <?php if (!$u->maPravoNerusitObjednavky()) { ?>
                    <ul class="seznam-bez-okraje">
                        <li class="poznamka">Při pozdější platbě tě systém dne
                            <strong><?php echo datum3($systemoveNastaveni->prvniHromadneOdhlasovani()) ?> automaticky
                                odhlásí</strong>.
                        </li>
                        <li class="poznamka">
                            Peníze navíc můžeš využít na přihlášení aktivit na GameConu a přeplatek ti po GameConu rádi
                            vrátíme.
                        </li>
                    </ul>
                <?php } ?>
            <?php } else { ?>
                <ul class="seznam-bez-okraje">
                    <li class="poznamka">Při pozdější platbě tě systém dne
                        <strong><?php echo datum3($nejblizsiHromadneOdhlasovaniKdy) ?> automaticky
                            odhlásí</strong>.
                    </li>
                    <li class="poznamka">
                        Peníze navíc můžeš využít na přihlášení aktivit na GameConu a přeplatek ti po GameConu rádi
                        vrátíme.
                    </li>
                </ul>
            <?php } ?>
        <?php } else { ?>
            <?php if ($u->stat() == Stat::CZ) { ?>
                <p>Zaplatit můžeš převodem nebo na místě. Platíš celkem <strong><?= $castka ?></strong>, variabilní
                    symbol je tvoje ID <strong><?= $uid ?></strong>.</p>
            <?php } else { ?>
                <p>Zaplatit můžeš převodem nebo na místě. Platíš celkem <strong><?= $castka ?></strong>, přesné údaje o
                    platbě nalezneš výše.</p>
            <?php } ?>
            <ul class="seznam-bez-okraje">
                <li class="poznamka">
                    Peníze navíc můžeš využít na přihlášení aktivit na GameConu a přeplatek ti po GameConu rádi vrátíme.
                </li>
            </ul>
        <?php } ?>
    <?php } else { ?>
    <div>
        <?php if ($u->stat() == Stat::CZ) { ?>
            <h2 id="placeni">Platba</h2>
            <p>Všechny tvoje pohledávky jsou <strong style="color:green">v pořádku zaplaceny</strong>, není potřeba nic
                platit. Pokud si ale chceš dokupovat aktivity na místě se slevou nebo bez nutnosti používat hotovost,
                můžeš si samozřejmě kdykoli převést peníze do zásoby:</p>
            <div>
                <strong>Číslo účtu:</strong> <?= UCET_CZ ?><br>
                <strong>Variabilní symbol:</strong> <?= $uid ?><br>
            </div>
        <?php } else { ?>
            <h2 id="placeni">Platba (SEPA)</h2>
            <p>Všechny tvoje pohledávky jsou <strong style="color:green">v pořádku zaplaceny</strong>, není potřeba nic
                platit. Pokud si ale chceš dokupovat aktivity na místě se slevou nebo bez nutnosti používat hotovost,
                můžeš si samozřejmě kdykoli převést peníze do zásoby:</p>
            <div>
                <strong>IBAN:</strong> <?= IBAN ?><br>
                <strong>BIC/SWIFT:</strong> <?= BIC_SWIFT ?><br>
                <strong>Poznámka pro příjemce:</strong> /VS/<?= $uid ?> <i>(včetně lomítek)</i><br>
            </div>
        <?php }
        }
        $qrKodProPlatbu = $u->finance()->dejQrKodProPlatbu();
        if ($qrKodProPlatbu !== null) {
            ?>
            <div style="text-align: center">
                <img src="<?= $u->finance()->dejQrKodProPlatbu()->getDataUri() ?>" alt="qrPlatba">
            </div>
        <?php } ?>
    </div>
</div>
