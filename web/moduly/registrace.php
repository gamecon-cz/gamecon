<?php

/**
 * Stránka pro registraci a úpravu registračních údajů.
 *
 * Pokud je uživatel přihlášen, stránka vždycky slouží jen k úpravě. Pokud
 * uživatel přihlášen není, slouží vždy k registraci a poslání dál na přihlášku
 * na GC (pokud reg jede).
 *
 * Pokud uživatel není přihlášen a zkusí se přihlásit na GC, přihláška ho pošle
 * právě sem.
 */

use Gamecon\Cas\DateTimeCz;
use Gamecon\Uzivatel\SqlStruktura\UzivatelSqlStruktura as Sql;
use Gamecon\Stat;
use Gamecon\Uzivatel\Pohlavi;

/** @var Uzivatel|null $u */

/**
 * @throws Chyby
 */
$zpracujRegistraci = function () use ($u) {
    if (!post('registrovat')) {
        return;
    }
    if ($u) {
        throw Chyby::jedna('Jiný uživatel v tomto prohlížeči už je přihlášený.');
    }

    $id = Uzivatel::registruj((array)post('formData'));
    Uzivatel::prihlasId($id);

    if (post('aPrihlasit')) {
        oznameniPresmeruj(hlaska('regOkNyniPrihlaska'), 'prihlaska');
    } else {
        oznameni(hlaska('regOk'));
    }
};

/**
 *
 */
$zpracujUpravu = function () use ($u) {
    if (!post('upravit')) {
        return;
    }
    if (!$u) {
        throw Chyby::jedna('Došlo k odhlášení, přihlaš se prosím znovu.');
    }

    $u->uprav((array)post('formData'));

    oznameni(hlaska('upravaUzivatele'));
};

$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev('Registrace');

$chyby = null;
try {
    $zpracujRegistraci();
    $zpracujUpravu();
} catch (Chyby $e) {
    $chyby = $e;
}

$formData         = post('formData') ?? $u?->rawDb();
$souhlasilOsUdaje = $u || post('registrovat');
if ($chyby && $chyby->globalniChyba()) {
    Chyba::nastav($chyby->globalniChyba());
}

$zacatekPrihlasovani = (new DateTimeCz(REG_GC_OD))->format('j.&#160;n. \v\e H:i');

/**
 * Pomocná funkce pro inputy
 */
$input = static function (
    string $nazev,
    string $typ,
    string $klic,
    string $inputCss = '',
    string $predrazeneHtml = '',
) use ($formData, $chyby): string {
    $predvyplneno = $formData[$klic] ?? '';

    $requiredHtml   = $typ == 'date' ? 'required' : '';
    $additionalHtml = $typ === 'password'
        ? 'autocomplete="new-password"' // aby se nám automaticky nevkládalo heslo
        : '';

    $chybaHtml  = '';
    $chybaTrida = '';
    if ($chyby && ($chyba = $chyby->klic($klic))) {
        $chybaHtml  = '<div class="formular_chyba">' . $chyba . '</div>';
        $chybaTrida = 'formular_polozka-chyba';
    }

    return <<<HTML
        <label class="formular_polozka {$chybaTrida}">
            <div>{$nazev}</div>
            {$predrazeneHtml}
            <input
                id="input_{$klic}"
                style="{$inputCss}"
                type="{$typ}"
                name="formData[{$klic}]"
                value="{$predvyplneno}"
                placeholder=" "
                {$requiredHtml}
                {$additionalHtml}
            >
            {$chybaHtml}
        </label>
    HTML;
};

/**
 * Pomocná funcke pro selecty
 */
$select = static function (string $nazev, string $klic, array $moznosti) use ($formData, $chyby): string {
    $moznostiHtml = '<option disabled value selected></option>';
    foreach ($moznosti as $hodnota => $popis) {
        $selected     = ($formData[$klic] ?? null) == $hodnota;
        $selectedHtml = $selected ? 'selected' : '';
        $moznostiHtml .= '<option value="' . $hodnota . '" ' . $selectedHtml . '>' . $popis . '</option>';
    }

    $chybaHtml  = '';
    $chybaTrida = '';
    if ($chyby && ($chyba = $chyby->klic($klic))) {
        $chybaHtml  = '<div class="formular_chyba">' . $chyba . '</div>';
        $chybaTrida = 'formular_polozka-chyba';
    }

    return <<<HTML
        <label class="formular_polozka {$chybaTrida}">
            {$nazev}
            <select name="formData[{$klic}]" required>
            {$moznostiHtml}
            </select>
            {$chybaHtml}
        </label>
    HTML;
};

$telefonniPredvolbaInput = static function (string $klic, string $inputCss = '') use ($input) {
    $options     = [
        '',
        '+421',
        '+420',
    ];
    $optionsHtml = implode(
        "\n",
        array_map(
            static fn(string $predvolba) => "<option value='$predvolba'>$predvolba</option>",
            $options,
        ),
    );
    return <<<HTML
<select name="formData[{$klic}]" style="{$inputCss}" id="input_{$klic}">
  {$optionsHtml}
</select>
HTML;
};

?>

<form method="post" class="formular_stranka">
    <div class="bg"></div>

    <?php if ($u) { ?>
        <div class="formular_strankaNadpis">Nastavení</div>
    <?php } else { ?>
        <div class="formular_strankaNadpis">Registrace</div>
        <div class="fromular_strankaPodtitul">
            <div style="max-width: 250px">
                Jsi jenom krok od toho stát se součástí naprosto boží akce!
            </div>
        </div>

        <div class="formular_prihlasit formular_duleziteInfo">
            Již mám účet <a href="prihlaseni">přihlásit se</a>
        </div>

        <?php if (!REG_GC) { ?>
            <div class="formular_infobox">
                <b>Přihlašování na GameCon se spustí <?= $zacatekPrihlasovani ?></b>
                Můžeš si předem vytvořit účet a přihlašování ti připomeneme e-mailem.
            </div>
        <?php } ?>
    <?php } ?>

    <h2 class="formular_sekceNadpis">Osobní</h2>

    <?= $input('E-mailová adresa', 'email', 'email1_uzivatele') ?>

    <div class="formular_sloupce">
        <?= $input('Jméno', 'text', Sql::JMENO_UZIVATELE) ?>
        <?= $input('Příjmení', 'text', Sql::PRIJMENI_UZIVATELE) ?>
        <?= $input('Přezdívka', 'text', Sql::LOGIN_UZIVATELE) ?>
        <?= $input('Datum narození', 'date', Sql::DATUM_NAROZENI) ?>
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
        <?= $input('Ulice a číslo popisné', 'text', Sql::ULICE_A_CP_UZIVATELE) ?>
        <?= $input('Město', 'text', Sql::MESTO_UZIVATELE) ?>
        <?= $input('PSČ', 'text', Sql::PSC_UZIVATELE) ?>
        <?= $select('Země', Sql::STAT_UZIVATELE, [
            Stat::CZ_ID   => 'Česká republika',
            Stat::SK_ID   => 'Slovenská republika',
            Stat::JINY_ID => '(jiný stát)',
        ]) ?>
    </div>

    <h2 class="formular_sekceNadpis">Ostatní</h2>

    <div class="formular_sloupce">
        <?= $input('Telefonní číslo', 'text', Sql::TELEFON_UZIVATELE, 'width: 70%; float:right', $telefonniPredvolbaInput('predvolba', 'float: left; width: 29%')) ?>
        <?= $select('Pohlaví', Sql::POHLAVI, Pohlavi::seznamProSelect()) ?>
    </div>

    <?= $input('Heslo', 'password', 'heslo') ?>
    <?= $input('Heslo pro kontrolu', 'password', 'heslo_kontrola') ?>

    <div class="formular_bydlisteTooltip" style="margin-top: 15px">
        <span class="tooltip">
            Shrnutí souhlasu
            <div class="tooltip_obsah">
                Prosíme o souhlas se zpracováním tvých údajů. Slibujeme, že je předáme jen těm, komu to bude kvůli vyloženě potřeba (např. vypravěčům nebo poskytovateli ubytování). Kontaktovat tě budeme v rozumné míře pouze v souvislosti s GameConem.<br><br>
                Plné právní znění najdeš <a href="legal" target="_blank">zde</a>
            </div>
        </span>
    </div>
    <label style="margin: 30px 0 40px; display: block" class="formular_polozka-checkbox">
        <input type="checkbox" required <?= $souhlasilOsUdaje ? 'checked' : '' ?>>
        <span class="formular_duleziteInfo">
            Souhlasím se <a href="legal" target="_blank">zpracováním osobních údajů</a>
        </span>
    </label>

    <?php if ($u) { ?>
        <input type="hidden" name="upravit" value="true">
        <input type="submit" value="Uložit" class="formular_primarni">
    <?php } else if (REG_GC) { ?>
        <input type="hidden" name="registrovat" value="true">
        <input type="submit" name="aPrihlasit" value="Přihlásit na GameCon" class="formular_primarni">
        <input type="submit" value="Jen vytvořit účet" class="formular_sekundarni">
    <?php } else { ?>
        <input type="hidden" name="registrovat" value="true">
        <span class="tooltip">
            <input type="submit" name="aPrihlasit" value="Přihlásit na GameCon" class="formular_primarni" disabled>
            <div class="tooltip_obsah">
                Přihlašování na GameCon se spustí <?= $zacatekPrihlasovani ?>. Můžeš si předem vytvořit účet a přihlašování ti připomeneme e-mailem.
            </div>
        </span>
        <input type="submit" value="Jen vytvořit účet" class="formular_sekundarni">
    <?php } ?>

    <!-- workaround: rezervace místa pro tooltip souhlasu -->
    <div style="height: 30px"></div>
</form>

<script type="text/javascript">
    // pozor 'telefon_uzivatele' pochází z názvu sloupce SQL, pokud ho pejmenujeme, musíme změnit i tento název
    const telefonInput = document.getElementById('input_telefon_uzivatele')
    const predvolbaInput = document.getElementById('input_predvolba')
    const moznePredvolby = Array.from(predvolbaInput.getElementsByTagName('option'))
        .map((optionElement) => optionElement.value)
        .filter((value) => value !== '')

    telefonInput.addEventListener('change', function () {
        const hodnota = this.value
        if (hodnota.match(/^\s*[+]/)) {
            predvolbaInput.value = '' // reset výběru předvolby na "žádná"
        }
    })
    predvolbaInput.addEventListener('change', function () {
        const predvolba = this.value.trim()
        if (predvolba === '') {
            return
        }
        moznePredvolby.forEach(function (moznaPredvolba) {
            // účastník vybral předvolbu a přitom už nějakou má napsanou v telefonu, smažeme ji z telefonu
            telefonInput.value = telefonInput.value.replace(moznaPredvolba, '')
        })
    })
</script>
