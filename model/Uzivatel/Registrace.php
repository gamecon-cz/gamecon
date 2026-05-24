<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Chyby;
use Gamecon\Stat;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Uzivatel;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;

class Registrace
{

    public const FORM_DATA_KEY = 'registraceFormData';

    private string | null | array $formData = 'undefined';
    private null | Chyby          $chyby    = null;

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private ?Uzivatel                   $u,
    ) {
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
        // pro případy kdy se chyby vypíší až po redirectu
        \Chyba::nastavZChyb($chyby, \Chyba::VALIDACE);
        if ($chyby->globalniChyba()) {
            \Chyba::nastav($chyby->globalniChyba());
        }
    }

    public function zpracujUpravu()
    {
        if (!post('upravit')) {
            return;
        }
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

            $formData = post(self::FORM_DATA_KEY);
            if ($formData === null) {
                return false;
            }

            $idUlozenehoUzivatele = $this->u->uprav((array)$formData);

            return (bool)$idUlozenehoUzivatele;
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
        ?bool  $required = null,
        string $inputCss = '',
        string $predrazeneHtml = '',
        string $placeholder = ' ', // schválně mezera
    ): string
    {
        $predvyplneno = $this->formData()[$klic] ?? '';

        $required       ??= array_key_exists($klic, Uzivatel::povinneUdajeProRegistraci());
        $requiredHtml   = $required || $typ == 'date'
            ? 'required'
            : '';
        $disabledHtml   = $this->jeUdajZamcenyPoKontrole($klic)
            ? 'disabled'
            : '';
        $additionalHtml = $typ === 'password'
            ? 'autocomplete="new-password"'
            // aby se nám automaticky nevkládalo heslo
            : '';

        $chybaHtml  = '';
        $chybaTrida = '';
        if ($chyba = $this->chyby()->klic($klic)) {
            $chybaHtml  = '<div class="formular_chyba">' . $chyba . '</div>';
            $chybaTrida = 'formular_polozka-chyba';
        }

        $labelRequired = $this->labelRequired($requiredHtml);

        return <<<HTML
            <label class="formular_polozka {$chybaTrida}">
                {$this->hlavickaPolozky($nazev, $labelRequired)}
                {$predrazeneHtml}
                <input
                    id="input_{$klic}"
                    style="{$inputCss}"
                    type="{$typ}"
                    name="{$this->inputName()}[{$klic}]"
                    value="{$predvyplneno}"
                    placeholder="$placeholder"
                    {$requiredHtml}
                    {$disabledHtml}
                    {$additionalHtml}
                >
                {$chybaHtml}
            </label>
        HTML;
    }

    private function labelRequired(string $requiredHtml): string
    {
        return $requiredHtml !== ''
            ? '<span class="vyzadovane">*</span>'
            : '';
    }

    private function hlavickaPolozky(string $nazev, string $labelRequired, string $tooltip = ''): string
    {
        return <<<HTML
<div class="formular_polozkaNadpis">
    <span class="formular_polozkaTitulek">{$nazev}{$labelRequired}</span>
    {$tooltip}
</div>
HTML;
    }

    private function formData(): ?array
    {
        if ($this->formData === 'undefined') {
            $postData = post(self::FORM_DATA_KEY);
            if ($postData !== null) {
                $this->formData = array_merge($this->dataUzivatele(), (array)$postData);
            } else {
                $this->formData = $this->dataUzivatele();
            }
            $this->formData[Sql::ZPUSOB_ZOBRAZENI_NA_WEBU] ??= ZpusobZobrazeniNaWebu::vychozi()->value;
        }

        return $this->formData;
    }

    private function dataUzivatele(): array
    {
        $dataUzivatele = (array)($this->u?->rawDb() ?? []);
        if (!empty(($dataUzivatele[Sql::OP]))) {
            $dataUzivatele[Sql::OP] = \Sifrovatko::desifruj($dataUzivatele[Sql::OP]);
        }

        return $dataUzivatele;
    }

    public function zobrazHtml()
    {
        ?>
      <form method="post" class="formular_stranka">
        <div class="bg"></div>

          <?php
          if ($this->u) { ?>
            <div class="formular_strankaNadpis">Nastavení</div>
              <?php
          } else { ?>
            <div class="formular_strankaNadpis">Registrace</div>
            <div class="formular_strankaPodtitul">
              <div style="max-width: 250px">
                Jsi jenom krok od toho stát se součástí naprosto boží akce!
              </div>
            </div>

            <div class="formular_prihlasit formular_duleziteInfo">
              Již mám účet <a href="prihlaseni">přihlásit se</a>
            </div>

              <?php
              if (pred($this->systemoveNastaveni->prihlasovaniUcastnikuOd())) { ?>
                <div class="formular_infobox">
                  <b>Přihlašování na GameCon se spustí <?= $this->zacatekPrihlasovani() ?></b>
                  Můžeš si předem vytvořit účet a přihlašování ti připomeneme e-mailem.
                </div>
                  <?php
              } ?>
              <?php
          } ?>

        <div>
          <h2 class="formular_sekceNadpis" style="float: left">Osobní</h2>

          <div class="formular_tooltip">
            <div class="gc_tooltip">
              Proč potřebujeme osobní údaje?
              <div class="tooltip_obsah">
                Potřebujeme je, abychom:<br>
                <ul>
                  <li>tě identifikovali při registraci na místě</li>
                  <li>urychlili Tvé odbavení</li>
                  <li>tě mohli ubytovat a splnit související zákonné povinnosti</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
          <?= $this->input('E-mailová adresa', 'email', Sql::EMAIL1_UZIVATELE) ?>

          <?= $this->input('Telefonní číslo', 'text', Sql::TELEFON_UZIVATELE, true, '', '', 'např. +420 789 123 456') ?>

          <?= $this->povinneUdajeProUbytovaniHtml(vyzadovatAdresuADoklad: false) ?>

        <h2 class="formular_sekceNadpis">Ostatní</h2>

        <div class="formular_sloupce">
            <?= $this->input('Přezdívka', 'text', Sql::LOGIN_UZIVATELE) ?>
            <?= $this->select(
                nazev: 'Zobrazení na webu',
                klic: Sql::ZPUSOB_ZOBRAZENI_NA_WEBU,
                moznosti: ZpusobZobrazeniNaWebu::proSelect(),
                tooltip: <<<HTML
<div class="formular_tooltip">
  <div class="gc_tooltip">
    Kde se použije?
    <div class="tooltip_obsah">
      Tohle nastavení ovlivní jen veřejný web, například detail aktivity, medailonky vypravěčů a veřejné seznamy.
      V administraci a interních seznamech se zobrazení jména nemění.
    </div>
  </div>
</div>
HTML
            ) ?>
          <div style="float:left">
              <?= $this->select(
                  nazev: 'Pohlaví',
                  klic: Sql::POHLAVI,
                  moznosti: Pohlavi::seznamProSelect(),
                  tooltip: <<<HTML
<div class="formular_tooltip">
  <div class="gc_tooltip">
    Proč potřebujeme znát pohlaví?
    <div class="tooltip_obsah">
      Informace slouží pouze interně pro<br>
      <ul>
        <li>přidělování ubytování bez preference spolubydlících,</li>
        <li>zpřístupnění genderově omezených míst pro aktivity.</li>
      </ul>
      <div>
        Informaci vyplň co nejvíce autentickým/komfortním způsobem nejen pro sebe, ale také pro případné
        spolubydlící a spoluhráče.
      </div>
    </div>
  </div>
</div>
HTML
              ) ?>
          </div>
        </div>

        <div class="formular_sloupce">
            <?= $this->input('Heslo', 'password', 'heslo', $this->u === null) ?>
            <?= $this->input('Heslo pro kontrolu', 'password', 'heslo_kontrola', $this->u === null) ?>
        </div>

        <div style="margin: 30px 0 40px" class="formular_polozka-checkbox">
          <label style="float: left; margin-right: 1em">
            <input type="checkbox" required <?= $this->souhlasilSeZpracovanimOsobnichUdaju()
                ? 'checked'
                : '' ?>>
            <span class="formular_duleziteInfo">
                  Souhlasím se <a href="legal" target="_blank">zpracováním osobních údajů</a>
            </span>
          </label>
          <div class="formular_tooltip">
            <div class="gc_tooltip">
              Shrnutí souhlasu
              <div class="tooltip_obsah">
                Tvé údaje předáme jen těm, komu to ze zákona či z provozní potřeby musíme doložit (např. poskytovateli ubytování či vypravěčům).<br><br>
                Kontaktovat Tě budeme pouze v rozumné míře a v souvislosti s GameConem.<br><br>
                Plné právní znění najdeš <a href="legal" target="_blank">zde</a>.
              </div>
            </div>
          </div>
        </div>

        <div class="clearfix"></div>

          <?php
          if ($this->u) { ?>
            <input type="hidden" name="upravit" value="true">
            <input type="submit" value="Uložit" class="formular_primarni">
              <?php
          } else { ?>
            <input type="hidden" name="registrovat" value="true">
              <?php
              if ($this->systemoveNastaveni->prihlasovaniUcastnikuSpusteno()) { ?>
                <input type="submit" name="aPrihlasit" value="Přihlásit na GameCon" class="formular_primarni">
                <input type="submit" value="Jen vytvořit účet" class="formular_sekundarni">
                  <?php
              } else { ?>
                <div class="gc_tooltip">
                  <input type="submit" name="aPrihlasit" value="Přihlásit na GameCon" class="formular_primarni"
                         disabled>
                  <div class="tooltip_obsah">
                    Přihlašování na GameCon se spustí <?= $this->zacatekPrihlasovani() ?>. Můžeš si předem
                    vytvořit
                    účet a přihlašování ti připomeneme e-mailem.
                  </div>
                </div>
                <input type="submit" value="Jen vytvořit účet" class="formular_sekundarni">
                  <?php
              } ?>
              <?php
          } ?>

        <!-- workaround: rezervace místa pro tooltip souhlasu -->
        <div style="height: 30px"></div>
      </form>

        <?php
    }

    private function zacatekPrihlasovani(): string
    {
        return $this->systemoveNastaveni->prihlasovaniUcastnikuOd()->format('j.&#160;n. Y \v\e H:i');
    }

    private function souhlasilSeZpracovanimOsobnichUdaju(): bool
    {
        return $this->u || post('registrovat');
    }

    private function select(
        string $nazev,
        string $klic,
        array  $moznosti,
        bool   $required = true,
        string $tooltip = '',
    ): string {
        $vybranaHodnota = $this->formData()[$klic] ?? '';
        $maVybranouHodnotu = $vybranaHodnota !== '' && $vybranaHodnota !== null;

        $moznostiHtml = '<option disabled value ' . ($maVybranouHodnotu
                ? ''
                : 'selected') . '></option>';
        foreach ($moznosti as $hodnota => $popis) {
            $selected     = $vybranaHodnota == $hodnota;
            $selectedHtml = $selected
                ? 'selected'
                : '';
            $moznostiHtml .= '<option value="' . $hodnota . '" ' . $selectedHtml . '>' . $popis . '</option>';
        }

        $chybaHtml  = '';
        $chybaTrida = '';
        if ($chyba = $this->chyby()->klic($klic)) {
            $chybaHtml  = '<div class="formular_chyba">' . $chyba . '</div>';
            $chybaTrida = 'formular_polozka-chyba';
        }

        $requiredHtml = $required
            ? 'required'
            : '';
        $disabledHtml = $this->jeUdajZamcenyPoKontrole($klic)
            ? 'disabled'
            : '';

        $labelRequired = $this->labelRequired($requiredHtml);

        return <<<HTML
            <label class="formular_polozka {$chybaTrida}">
                {$this->hlavickaPolozky($nazev, $labelRequired, $tooltip)}
                <select name="{$this->inputName()}[{$klic}]" {$requiredHtml} {$disabledHtml}>
                {$moznostiHtml}
                </select>
                {$chybaHtml}
            </label>
        HTML;
    }

    /**
     * Vrací true, pokud je daný údaj zamčený kvůli proběhlé kontrole na infopultu.
     * Ve webovém formuláři se takové pole renderuje jako disabled a změna přes Uzivatel::uprav se neuloží.
     */
    private function jeUdajZamcenyPoKontrole(string $klic): bool
    {
        if (!$this->u || !$this->u->maZkontrolovaneUdaje()) {
            return false;
        }

        return in_array($klic, Uzivatel::zamceneUdajePoKontroleNaInfopultu(), true);
    }

    public function povinneUdajeProUbytovaniHtml(
        string $nadpis = '',
        string $tooltip = '',
        bool $vyzadovatAdresuADoklad = true,
    ): string
    {
        $nadpisHtml = '';
        if ($nadpis !== '') {
            $nadpisHtml = "<h2 class='formular_sekceNadpis'>{$nadpis}</h2>";
        }
        $tooltipHtml = '';
        if ($tooltip !== '') {
            $tooltipHtml = <<<HTML
<div style="float: right">
    <div class="gc_tooltip" style="position: relative; top: -4em;">
        ℹ️
        <div class="tooltip_obsah" style="right: -247px; top: 2em;">
            {$tooltip}
        </div>
    </div>
</div>
HTML;
        }

        return <<<HTML
{$nadpisHtml}

{$tooltipHtml}

<div class="formular_sloupce">
    {$this->input('Jméno', 'text', Sql::JMENO_UZIVATELE)}
    {$this->input('Příjmení', 'text', Sql::PRIJMENI_UZIVATELE)}
    {$this->input('Datum narození', 'date', Sql::DATUM_NAROZENI)}
    {$this->input(
            nazev: 'Státní občanství',
            typ: 'text',
            klic: Sql::STATNI_OBCANSTVI,
            required: true,
            placeholder: 'například ČR',
        )}
</div>

<div>
    <h2 class="formular_sekceNadpis" style="float: left">Adresa trvalého pobytu</h2>

    <div class="formular_tooltip">
        <div class="gc_tooltip">
            Proč potřebujeme adresu?
            <div class="tooltip_obsah">
                Informaci o trvalém bydlišti jsme bohužel ze zákona povinni odevzdávat poskytovateli ubytování.<br><br>
                Tím je <b>Vysoká škola báňská - Technická univerzita Ostrava, Koleje Poruba Studentská 1770/1 708 00 Ostrava-Poruba</b>.<br><br>
                Chybné vyplnění polí může vést u infopultu k vykázání na konec fronty, aby náprava nezdržovala odbavení ostatních.
            </div>
        </div>
    </div>
</div>
<div class="clearfix"></div>

<div class="formular_sloupce">
    {$this->input('Ulice a číslo popisné', 'text', Sql::ULICE_A_CP_UZIVATELE, $vyzadovatAdresuADoklad)}
    {$this->input('Město', 'text', Sql::MESTO_UZIVATELE, $vyzadovatAdresuADoklad)}
    {$this->input('PSČ', 'text', Sql::PSC_UZIVATELE, $vyzadovatAdresuADoklad)}
    {$this->select(
            'Země',
            Sql::STAT_UZIVATELE,
            [
                Stat::CZ_ID   => 'Česká republika',
                Stat::SK_ID   => 'Slovenská republika',
                Stat::JINY_ID => '(jiný stát)',
            ],
            required: $vyzadovatAdresuADoklad,
        )}
</div>

<div>
    <h2 class="formular_sekceNadpis" style="float: left">Platný doklad totožnosti</h2>

    <div class="formular_tooltip">
        <div class="gc_tooltip">
            Proč potřebujeme doklad?
            <div class="tooltip_obsah">
                Číslo identifikačního dokladu jsme bohužel ze zákona povinni odevzdávat poskytovateli ubytování.<br><br>
                Tím je <b>Vysoká škola báňská - Technická univerzita Ostrava, Koleje Poruba Studentská 1770/1 708 00 Ostrava-Poruba</b>.<br><br>
                Chybné vyplnění polí může vést u infopultu k vykázání na konec fronty, aby náprava nezdržovala odbavení ostatních.
            </div>
        </div>
    </div>
</div>
<div class="clearfix"></div>

<div class="formular_sloupce">
    {$this->select(
            'Typ dokladu',
            Sql::TYP_DOKLADU_TOTOZNOSTI,
            [
                Uzivatel::TYP_DOKLADU_OP   => 'Občanský průkaz',
                Uzivatel::TYP_DOKLADU_PAS  => 'Cestovní pas',
                Uzivatel::TYP_DOKLADU_JINY => 'Jiný',
            ],
            required: $vyzadovatAdresuADoklad,
        )}
    {$this->input('Číslo dokladu', 'text', Sql::OP, $vyzadovatAdresuADoklad)}
</div>
HTML;
    }

    private function chyby(): Chyby
    {
        if ($this->chyby === null) {
            // vyzvednutí z cookie pro případy kdy se chyby vykreslují až po redirectu
            $this->chyby = Chyby::zPole(\Chyba::vyzvedniVsechnyValidace());
        }

        return $this->chyby;
    }
}
