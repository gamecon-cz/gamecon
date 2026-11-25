<?php

use Gamecon\Uzivatel\PrihlaseniQrKodem;

$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev('Přihlášení');

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
        Chyba::nastav($chybaQr);
    }
    back();
}
if (post('qrZrusit')) {
    $prihlaseniQrKodem->zrus();
    back();
}

if (post('prihlasit')) {
    if (! Gamecon\Antibot\Altcha::zGlobals()->overReseni((string) post('altcha'))) {
        Chyba::nastav('Ověř prosím, že nejsi robot.');
    } else {
        if (post('trvale')) {
            $u = Uzivatel::prihlasTrvale(post('login'), post('heslo'));
        } else {
            $u = Uzivatel::prihlas(post('login'), post('heslo'));
        }

        if ($u) {
            back($poPrihlaseni ?? URL_WEBU . '/prihlaska');
        } else {
            Chyba::nastav(hlaska('chybaPrihlaseni'));
        }
    }
}

if ($u) {
    back($poPrihlaseni ?? URL_WEBU);
}

// Vypršelý nebo zamítnutý kód zapomene a ukáže formulář s heslem.
$zadostQr = $prihlaseniQrKodem->getZivouZadost();
if ($zadostQr !== null) {
    $this->pridejJsSoubor(__DIR__ . '/../soubory/prihlaseni-qr.js');
    ?>

  <div class="formular formular_stranka formular_stranka-login prihlaseniQr"
       data-prihlaseni-qr
       data-stav-url="<?php echo URL_WEBU; ?>/api/prihlaseniQr"
       data-po-prihlaseni="<?php echo URL_WEBU; ?>/prihlaska">
    <div class="bg"></div>

    <div class="formular_strankaNadpis">Přihlášení</div>

    <h1 class="prihlaseniQr_nadpis">Přihlášení QR kódem</h1>

    <img class="prihlaseniQr_kod" src="<?php echo $prihlaseniQrKodem->getQrKodSvg(); ?>"
         alt="QR kód pro přihlášení" width="240" height="240">

    <ol class="prihlaseniQr_navod">
      <li>Na telefonu, kde máš přihlášený účet na webu GameConu, naskenuj kód fotoaparátem,
        nebo na webu v Nastavení tlačítkem <b>Načíst QR kód</b>.</li>
      <li>Na telefonu vyber tohle číslo:
        <span class="prihlaseniQr_cislo"><?php echo $zadostQr['cislo']; ?></span></li>
    </ol>

    <p class="prihlaseniQr_zprava" data-prihlaseni-qr-zprava aria-live="polite">
      Kód platí <?php echo intdiv(PrihlaseniQrKodem::PLATNOST_SEKUND, 60); ?> minuty.
    </p>

    <form method="post">
        <?php if ($zadostQr['trvale']) { ?>
          <input type="hidden" name="trvale" value="true">
        <?php } ?>
      <button type="submit" name="qrKod" value="1" class="formular_primarni">Nový kód</button>
      <button type="submit" name="qrZrusit" value="1" class="formular_sekundarni">Přihlásit heslem</button>
    </form>
  </div>

    <?php
    return;
}

?>

<form method="post" class="formular formular_stranka formular_stranka-login">
    <div class="bg"></div>

    <div class="formular_strankaNadpis">Přihlášení</div>

    <label class="formular_polozka">
        E-mailová adresa
        <input type="text" name="login" autocomplete="username" autofocus value="<?php echo htmlspecialchars((string) post('login')); ?>" placeholder="" tabindex="1">
    </label>

    <a href="zapomenute-heslo" class="formular_zapomenuteHeslo" tabindex="2">zapomenuté heslo</a>
    <label class="formular_polozka">
        Heslo
        <input type="password" name="heslo" autocomplete="current-password" placeholder="" tabindex="1">
    </label>

    <label style="margin: 30px 0; display: block" class="formular_polozka-checkbox">
        <input type="checkbox" name="trvale" value="true" checked tabindex="1">
        Trvale přihlásit
    </label>

    <input type="hidden" name="prihlasit" value="true">

    <?php echo altchaWidget(); ?>

    <input type="submit" value="Přihlásit se" class="formular_primarni formular_primarni-sipka" tabindex="1">

    <?php
    // Ne cestou ke schválení QR kódu: tam se přihlašuje právě telefon, který kód načte.
    if ($poPrihlaseni === null) { ?>
      <button type="submit" name="qrKod" value="1" class="formular_sekundarni prihlaseniQr_tlacitko" tabindex="1">
        Přihlásit QR kódem
      </button>
    <?php } ?>

    <div class="formular_registrovat formular_duleziteInfo">
        Nemám účet <a href="registrace" tabindex="2">registrovat</a>
    </div>
</form>
