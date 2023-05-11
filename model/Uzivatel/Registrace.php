<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Chyby;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Stat;
use Uzivatel;
use Gamecon\Uzivatel\SqlStruktura\UzivatelSqlStruktura as Sql;

class Registrace
{

    public const FORM_DATA_KEY = 'registraceFormData';

    private string|null|array $formData = 'undefined';
    private null|Chyby        $chyby    = null;

    public function __construct(private ?Uzivatel $u)
    {
    }

    public function zpracujRegistraci()
    {
        try {
            if (!post('registrovat')) {
                return;
            }
            if ($this->u) {
                throw Chyby::jedna('Jiný uživatel v tomto prohlížeči už je přihlášený.');
            }

            $id      = Uzivatel::registruj((array)post(self::FORM_DATA_KEY));
            $this->u = Uzivatel::prihlasId($id);

            if (post('aPrihlasit')) {
                oznameniPresmeruj(hlaska('regOkNyniPrihlaska'), 'prihlaska');
            } else {
                oznameni(hlaska('regOk'));
            }
        } catch (Chyby $chyby) {
            $this->zpracujChyby($chyby);
        }
    }

    private function zpracujChyby(Chyby $chyby)
    {
        $this->chyby = $chyby;
        if ($chyby->globalniChyba()) {
            \Chyba::nastav($chyby->globalniChyba());
        }
    }

    public function zpracujUpravu()
    {
        if ($this->ulozZmeny()) {
            oznameni(hlaska('upravaUzivatele'));
        }
    }

    public function ulozZmeny(): bool
    {
        try {
            if (!$this->u) {
                throw Chyby::jedna('Došlo k odhlášení, přihlaš se prosím znovu.');
            }

            $this->u->uprav((array)post(self::FORM_DATA_KEY));
            return true;
        } catch (Chyby $chyby) {
            $this->zpracujChyby($chyby);
            return false;
        }
    }

    private function inputName(): string
    {
        return self::FORM_DATA_KEY;
    }

    /**
     * Pomocná funkce pro inputy
     */
    private function input(
        string $nazev,
        string $typ,
        string $klic,
        bool   $required = false,
        string $inputCss = '',
        string $predrazeneHtml = '',
    ): string
    {
        $predvyplneno = $this->formData()[$klic] ?? '';

        $requiredHtml   = $required || $typ == 'date'
            ? 'required'
            : '';
        $additionalHtml = $typ === 'password'
            ? 'autocomplete="new-password"' // aby se nám automaticky nevkládalo heslo
            : '';

        $chybaHtml  = '';
        $chybaTrida = '';
        if ($this->chyby && ($chyba = $this->chyby->klic($klic))) {
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
                    name="{$this->inputName()}[{$klic}]"
                    value="{$predvyplneno}"
                    placeholder=" "
                    {$requiredHtml}
                    {$additionalHtml}
                >
                {$chybaHtml}
            </label>
        HTML;
    }

    private function formData(): ?array
    {
        if ($this->formData === 'undefined') {
            $postData = post(self::FORM_DATA_KEY);
            if ($postData !== null) {
                $this->formData = $postData;
            } else {
                $dataUzivatele = $this->u?->rawDb();
                if ($dataUzivatele !== null) {
                    if (isset($dataUzivatele[Sql::OP])) {
                        $dataUzivatele[Sql::OP] = \Sifrovatko::desifruj($dataUzivatele[Sql::OP]);
                    }
                }
                $this->formData = $dataUzivatele;
            }
        }
        return $this->formData;
    }

    public function zobrazHtml()
    {
        ?>
        <form method="post" class="formular_stranka">
            <div class="bg"></div>

            <?php if ($this->u) { ?>
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
                        <b>Přihlašování na GameCon se spustí <?= $this->zacatekPrihlasovani() ?></b>
                        Můžeš si předem vytvořit účet a přihlašování ti připomeneme e-mailem.
                    </div>
                <?php } ?>
            <?php } ?>

            <div class="formular_bydlisteTooltip">
                <div class="tooltip">
                    Proč potřebujeme osobní údaje?
                    <div class="tooltip_obsah">
                        Vyplň prosím následující údaje o sobě. Nejsme žádný velký bratr, ale potřebujeme je,
                        abychom:<br>
                        <ul>
                            <li>Tě mohli ubytovat a splnit své další zákonné povinnosti</li>
                            <li>maximálně urychlili tvoji registraci na místě a nemusel(a) jsi dlouho čekat ve frontě
                            </li>
                            <li>věděli, že jsi to ty</li>
                        </ul>
                    </div>
                </div>
            </div>

            <h2 class="formular_sekceNadpis">Osobní</h2>

            <?= $this->input('E-mailová adresa', 'email', 'email1_uzivatele') ?>

            <div class="formular_sloupce">
                <?= $this->input('Telefonní číslo', 'text', Sql::TELEFON_UZIVATELE, false, 'width: 70%; float:right', $this->telefonniPredvolbaInput('predvolba', 'float: left; width: 29%')) ?>
            </div>

            <?= $this->povinneUdajeProUbytovaniHtml() ?>

            <h2 class="formular_sekceNadpis">Ostatní</h2>

            <div class="formular_sloupce">
                <?= $this->input('Přezdívka', 'text', Sql::LOGIN_UZIVATELE) ?>
                <?= $this->select('Pohlaví', Sql::POHLAVI, Pohlavi::seznamProSelect()) ?>
            </div>

            <div class="formular_sloupce">
                <?= $this->input('Heslo', 'password', 'heslo') ?>
                <?= $this->input('Heslo pro kontrolu', 'password', 'heslo_kontrola') ?>
            </div>

            <div class="formular_bydlisteTooltip" style="margin-top: 15px">
                <div class="tooltip">
                    Shrnutí souhlasu
                    <div class="tooltip_obsah">
                        Prosíme o souhlas se zpracováním tvých údajů. Slibujeme, že je předáme jen těm, komu to bude
                        kvůli vyloženě potřeba (např. vypravěčům nebo poskytovateli ubytování). Kontaktovat tě budeme v
                        rozumné míře pouze v souvislosti s GameConem.<br><br>
                        Plné právní znění najdeš <a href="legal" target="_blank">zde</a>
                    </div>
                </div>
            </div>
            <label style="margin: 30px 0 40px; display: block" class="formular_polozka-checkbox">
                <input type="checkbox" required <?= $this->souhlasilSeZpracovanimOsobnichUdaju() ? 'checked' : '' ?>>
                <span class="formular_duleziteInfo">
                    Souhlasím se <a href="legal" target="_blank">zpracováním osobních údajů</a>
                </span>
            </label>

            <?php if ($this->u) { ?>
                <input type="hidden" name="upravit" value="true">
                <input type="submit" value="Uložit" class="formular_primarni">
            <?php } else { ?>
                <input type="hidden" name="registrovat" value="true">
                <?php if (REG_GC) { ?>
                    <input type="submit" name="aPrihlasit" value="Přihlásit na GameCon" class="formular_primarni">
                    <input type="submit" value="Jen vytvořit účet" class="formular_sekundarni">
                <?php } else { ?>
                    <div class="tooltip">
                        <input type="submit" name="aPrihlasit" value="Přihlásit na GameCon" class="formular_primarni"
                               disabled>
                        <div class="tooltip_obsah">
                            Přihlašování na GameCon se spustí <?= $this->zacatekPrihlasovani() ?>. Můžeš si předem
                            vytvořit
                            účet a přihlašování ti připomeneme e-mailem.
                        </div>
                    </div>
                    <input type="submit" value="Jen vytvořit účet" class="formular_sekundarni">
                <?php } ?>
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
        <?php
    }

    private function zacatekPrihlasovani(): string
    {
        return (new DateTimeCz(REG_GC_OD))->format('j.&#160;n. \v\e H:i');
    }

    private function souhlasilSeZpracovanimOsobnichUdaju(): bool
    {
        return $this->u || post('registrovat');
    }

    private function select(string $nazev, string $klic, array $moznosti, bool $required = true): string
    {
        $vybranaHodnota = $this->formData()[$klic] ?? '';

        $moznostiHtml = '<option disabled value selected></option>';
        foreach ($moznosti as $hodnota => $popis) {
            $selected     = $vybranaHodnota == $hodnota;
            $selectedHtml = $selected ? 'selected' : '';
            $moznostiHtml .= '<option value="' . $hodnota . '" ' . $selectedHtml . '>' . $popis . '</option>';
        }

        $chybaHtml  = '';
        $chybaTrida = '';
        if ($this->chyby && ($chyba = $this->chyby->klic($klic))) {
            $chybaHtml  = '<div class="formular_chyba">' . $chyba . '</div>';
            $chybaTrida = 'formular_polozka-chyba';
        }

        $requiredHtml = $required
            ? 'required'
            : '';

        return <<<HTML
            <label class="formular_polozka {$chybaTrida}">
                {$nazev}
                <select name="{$this->inputName()}[{$klic}]" {$requiredHtml}>
                {$moznostiHtml}
                </select>
                {$chybaHtml}
            </label>
        HTML;
    }

    private function telefonniPredvolbaInput(string $klic, string $inputCss = ''): string
    {
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
    <select name="{$this->inputName()}[{$klic}]" style="{$inputCss}" id="input_{$klic}">
      {$optionsHtml}
    </select>
    HTML;
    }

    public function povinneUdajeProUbytovaniHtml(string $nadpis = '', bool $vsePovinne = false): string
    {
        $nadpisHtml = '';
        if ($nadpis !== '') {
            $nadpisHtml = "<h2 class='formular_sekceNadpis'>{$nadpis}</h2>";
        }
        return <<<HTML
{$nadpisHtml}

<div class="formular_sloupce">
    {$this->input('Jméno', 'text', Sql::JMENO_UZIVATELE, $vsePovinne)}
    {$this->input('Příjmení', 'text', Sql::PRIJMENI_UZIVATELE, $vsePovinne)}
    {$this->input('Datum narození', 'date', Sql::DATUM_NAROZENI, $vsePovinne)}
</div>

<h2 class="formular_sekceNadpis">Adresa trvalého pobytu</h2>

<div class="formular_sloupce">
    {$this->input('Ulice a číslo popisné', 'text', Sql::ULICE_A_CP_UZIVATELE, $vsePovinne)}
    {$this->input('Město', 'text', Sql::MESTO_UZIVATELE, $vsePovinne)}
    {$this->input('PSČ', 'text', Sql::PSC_UZIVATELE, $vsePovinne)}
    {$this->select(
            'Země',
            Sql::STAT_UZIVATELE, [
            Stat::CZ_ID   => 'Česká republika',
            Stat::SK_ID   => 'Slovenská republika',
            Stat::JINY_ID => '(jiný stát)',
        ], $vsePovinne)}
</div>

<h2 class="formular_sekceNadpis">Platný doklad totožnosti</h2>

<div class="formular_sloupce">
    {$this->select('Druh dokladu', Sql::TYP_DOKLADU_TOTOZNOSTI, [
            Uzivatel::TYP_DOKLADU_OP   => 'Občanský průkaz',
            Uzivatel::TYP_DOKLADU_PAS  => 'Cestovní pas',
            Uzivatel::TYP_DOKLADU_JINY => 'Jiný',
        ], $vsePovinne)}
    {$this->input('Číslo dokladu', 'text', Sql::OP, $vsePovinne)}
</div>
HTML;

    }
}
