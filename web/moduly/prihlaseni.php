<?php

use Gamecon\Uzivatel\PrihlaseniQrKodem;

$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev(t('prihlaseni.nazev'));
$this->setObsahPrelozeny(true);

/** @var Uzivatel|null $u */

// Stránka schválení QR kódu sem posílá toho, kdo na telefonu přihlášený není, a po přihlášení se na ni vrací.
$poPrihlaseni = PrihlaseniQrKodem::getUrlSchvaleniZParametru(get(PrihlaseniQrKodem::PARAMETR_SCHVALIT_PO_PRIHLASENI));
$prihlaseniQrKodem = PrihlaseniQrKodem::zSession(doAdminu: false);

if (post('odhlasit')) {
    if ($u) {
        $u->odhlas();
    }
    back();
}

// Dřív než přihlášení heslem, tlačítko je ve stejném formuláři.
if (post('qrKod')) {
    $chybaQr = $prihlaseniQrKodem->zaloz(
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        trvale: (bool) post('trvale'),
    );
    if ($chybaQr !== null) {
        Chyba::nastav(htmlspecialchars($chybaQr));
    }
    back();
}
if (post('qrZrusit')) {
    $prihlaseniQrKodem->zrus();
    back();
}

if (post('prihlasit')) {
    if (! Gamecon\Antibot\Altcha::zGlobals()->overReseni((string) post('altcha'))) {
        Chyba::nastav(htmlspecialchars(t('ucet.nejsi_robot')));
    } else {
        if (post('trvale')) {
            $u = Uzivatel::prihlasTrvale(post('login'), post('heslo'));
        } else {
            $u = Uzivatel::prihlas(post('login'), post('heslo'));
        }

        if ($u) {
            back($poPrihlaseni ?? urlWebu('prihlaska'));
        } else {
            Chyba::nastav(htmlspecialchars(t('prihlaseni.chyba')));
        }
    }
}

if ($u) {
    back($poPrihlaseni ?? urlWebu());
}

// Vypršelý nebo zamítnutý kód zapomene a ukáže formulář s heslem.
$zadostQr = $prihlaseniQrKodem->getZivouZadost();
if ($zadostQr !== null) {
    $this->pridejJsSoubor(__DIR__ . '/../soubory/prihlaseni-qr.js');
    ?>

  <div class="formular formular_stranka formular_stranka-login prihlaseniQr"
       data-prihlaseni-qr
       data-stav-url="<?php echo URL_WEBU; ?>/api/prihlaseniQr"
       data-po-prihlaseni="<?php echo htmlspecialchars(urlWebu('prihlaska')); ?>"
       data-zprava-zamitnuto="<?php echo htmlspecialchars(t('prihlaseni.qr.zamitnuto')); ?>"
       data-zprava-vyprselo="<?php echo htmlspecialchars(t('prihlaseni.qr.vyprselo')); ?>"
       data-zprava-zadna="<?php echo htmlspecialchars(t('prihlaseni.qr.neplatny')); ?>">
    <div class="bg"></div>

    <div class="formular_strankaNadpis"><?php echo htmlspecialchars(t('prihlaseni.nazev')); ?></div>

    <h1 class="prihlaseniQr_nadpis"><?php echo htmlspecialchars(t('prihlaseni.qr.nadpis')); ?></h1>

    <img class="prihlaseniQr_kod" src="<?php echo $prihlaseniQrKodem->getQrKodSvg(); ?>"
         alt="<?php echo htmlspecialchars(t('prihlaseni.qr.alt')); ?>" width="240" height="240">

    <ol class="prihlaseniQr_navod">
      <li><?php echo htmlSVlozkami(
          t('prihlaseni.qr.naskenuj', ['tlacitko' => znackaVlozky('tlacitko')]),
          ['tlacitko' => '<b>' . htmlspecialchars(t('registrace.jine_zarizeni.nacist_qr')) . '</b>'],
      ); ?></li>
      <li><?php echo htmlspecialchars(t('prihlaseni.qr.vyber_cislo')); ?>
        <span class="prihlaseniQr_cislo"><?php echo $zadostQr['cislo']; ?></span></li>
    </ol>

    <p class="prihlaseniQr_zprava" data-prihlaseni-qr-zprava aria-live="polite">
      <?php echo htmlspecialchars(t('prihlaseni.qr.platnost', ['minut' => intdiv(PrihlaseniQrKodem::PLATNOST_SEKUND, 60)])); ?>
    </p>

    <form method="post">
        <?php if ($zadostQr['trvale']) { ?>
          <input type="hidden" name="trvale" value="true">
        <?php } ?>
      <button type="submit" name="qrKod" value="1" class="formular_primarni"><?php echo htmlspecialchars(t('prihlaseni.qr.novy_kod')); ?></button>
      <button type="submit" name="qrZrusit" value="1" class="formular_sekundarni"><?php echo htmlspecialchars(t('prihlaseni.qr.heslem')); ?></button>
    </form>
  </div>

    <?php
    return;
}

?>

<form method="post" class="formular formular_stranka formular_stranka-login">
    <div class="bg"></div>

    <div class="formular_strankaNadpis"><?php echo htmlspecialchars(t('prihlaseni.nazev')); ?></div>

    <label class="formular_polozka">
        <?php echo htmlspecialchars(t('prihlaseni.email')); ?>
        <input type="text" name="login" autocomplete="username" autofocus value="<?php echo htmlspecialchars((string) post('login')); ?>" placeholder="" tabindex="1">
    </label>

    <a href="zapomenute-heslo" class="formular_zapomenuteHeslo" tabindex="2"><?php echo htmlspecialchars(t('prihlaseni.zapomenute_heslo')); ?></a>
    <label class="formular_polozka">
        <?php echo htmlspecialchars(t('prihlaseni.heslo')); ?>
        <input type="password" name="heslo" autocomplete="current-password" placeholder="" tabindex="1">
    </label>

    <label style="margin: 30px 0; display: block" class="formular_polozka-checkbox">
        <input type="checkbox" name="trvale" value="true" checked tabindex="1">
        <?php echo htmlspecialchars(t('prihlaseni.trvale')); ?>
    </label>

    <input type="hidden" name="prihlasit" value="true">

    <?php echo altchaWidget(); ?>

    <input type="submit" value="<?php echo htmlspecialchars(t('prihlaseni.prihlasit_se')); ?>" class="formular_primarni formular_primarni-sipka" tabindex="1">

    <?php
    // Ne cestou ke schválení QR kódu: tam se přihlašuje právě telefon, který kód načte.
    if ($poPrihlaseni === null) { ?>
      <button type="submit" name="qrKod" value="1" class="formular_sekundarni prihlaseniQr_tlacitko" tabindex="1">
        <?php echo htmlspecialchars(t('prihlaseni.qr_kodem')); ?>
      </button>
    <?php } ?>

    <div class="formular_registrovat formular_duleziteInfo">
        <?php echo htmlspecialchars(t('prihlaseni.nemam_ucet')); ?> <a href="registrace" tabindex="2"><?php echo htmlspecialchars(t('prihlaseni.registrovat')); ?></a>
    </div>
</form>
