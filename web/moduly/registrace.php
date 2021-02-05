<?php

$this->blackarrowStyl(true);


$udb = []; // TODO temp

// pomocná funkce pro inputy
$input = function ($nazev, $typ, $klic) use ($udb) {
    $predvyplneno = $udb[$klic] ?? '';

    return '
        <label class="formular_polozka">
            '.$nazev.'
            <input
                type="'.$typ.'"
                name="tab['.$klic.']"
                value="'.$predvyplneno.'"
                placeholder=""
                required
            >
        </label>
    ';
};

// pomocná funcke pro selecty
$select = function ($nazev, $klic, $moznosti) use ($udb) {
    //...

    $htmlMoznosti = '<option disabled value selected></option>';
    foreach ($moznosti as $hodnota => $popis) {
        $htmlMoznosti .= '<option value="'.$hodnota.'">'.$popis.'</option>';
    }

    return '
        <label class="formular_polozka">
            '.$nazev.'
            <select required>
                '.$htmlMoznosti.'
            </select>
        </label>
    ';
};

?>

<form method="post" class="formular_stranka">
    <div class="formular_strankaNadpis">Registrace</div>
    <div class="fromular_strankaPodtitul">
        <div style="max-width: 250px">
            Jsi jenom krok od toho stát se součástí naprosto boží akce!
        </div>
    </div>

    <div class="formular_prihlasit formular_duleziteInfo">
        Již mám účet <a href="prihlaseni">přihlásit se</a>
    </div>

    <h2 class="formular_sekceNadpis">Osobní</h2>

    <?=$input('E-mailová adresa', 'email', 'email1_uzivatele')?>

    <div class="formular_sloupce">
        <?=$input('Jméno', 'text', 'jmeno_uzivatele')?>
        <?=$input('Příjmení', 'text', 'prijmeni_uzivatele')?>
        <?=$input('Datum narození', 'date', 'datum_narozeni')?>
        <?=$select('Pohlaví', 'pohlavi', ['f' => 'žena', 'm' => 'muž'])?>
    </div>

    <div class="formular_bydlisteTooltip">
        <span class="tooltip">
            Proč potřebujeme adresu?
            <div class="tooltip_obsah">
                Vyplň prosím následující údaje o sobě. Nejsme žádný velký bratr, ale potřebujeme je, abychom:<br>
                <ul>
                    <li>Tě mohli ubytovat a splnit své další zákonné povinnosti</li>
                    <li>maximálně urychlili tvoji registraci na místě a nemusel(a) jsi dlouho čekat ve frontě</li>
                    <li>věděli, že jsi to ty.</li>
                </ul>
            </div>
        </span>
    </div>
    <h2 class="formular_sekceNadpis">Bydliště</h2>

    <div class="formular_sloupce">
        <?=$input('Ulice a číslo popisné', 'text', 'ulice_a_cp_uzivatele')?>
        <?=$input('Město', 'text', 'mesto_uzivatele')?>
        <?=$input('PSČ', 'text', 'psc_uzivatele')?>
        <?=$select('Země', 'stat_uzivatele', [
            '1'  => 'Česká republika',
            '2'  => 'Slovenská republika',
            '-1' => '(jiný stát)',
        ])?>
    </div>

    <h2 class="formular_sekceNadpis">Ostatní</h2>

    <div class="formular_sloupce">
        <?=$input('Telefonní číslo', 'text', 'telefon_uzivatele')?>
        <?=$input('Přezdívka', 'text', 'login_uzivatele')?>
    </div>

    <?=$input('Heslo', 'password', 'heslo2')?><!-- TODO toto je jinak asi -->
    <?=$input('Heslo pro kontrolu', 'password', 'heslo3')?>

    <label style="margin: 30px 0 40px; display: block">
        <input type="checkbox">
        <span class="formular_duleziteInfo">
            Souhlasím se
            <span class="tooltip">
                <span class="formular_duleziteOdkaz">zpracováním osobních údajů</span>
                <div class="tooltip_obsah">
                    Prosíme o souhlas se zpracováním tvých údajů. Slibujeme, že je předáme jen těm, komu to bude kvůli vyloženě potřeba (např. vypravěčům nebo poskytovatlei ubytování). Kontaktovat tě budeme v rozumné míře pouze v souvislosti s GameConem.<br><br>
                    Plné právní znění najdeš <a href="legal" target="_blank">zde</a>
                </div>
            </span>
        </span>
    </label>

    <input type="submit" value="Přihlásit na GameCon" class="formular_primarni">
    <input type="submit" value="Jen vytvořit účet" class="formular_sekundarni">

</form>
