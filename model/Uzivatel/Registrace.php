<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Chyby;
use Gamecon\Stat;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;
use Uzivatel;

class Registrace
{
    public const FORM_DATA_KEY = 'registraceFormData';

    private string|array|null $formData = 'undefined';
    private ?Chyby $chyby = null;

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private ?Uzivatel $u,
    ) {
    }

    public function zpracujRegistraci()
    {
        try {
            if (! post('registrovat')) {
                return;
            }
            if (! \Gamecon\Antibot\Altcha::zGlobals()->overReseni((string) post('altcha'))) {
                throw Chyby::jedna(htmlspecialchars(\t('ucet.nejsi_robot')));
            }
            if ($this->u) {
                throw Chyby::jedna(htmlspecialchars(\t('registrace.chyba.jiny_uzivatel_prihlasen')));
            }

            $id = Uzivatel::registruj((array) post(self::FORM_DATA_KEY));
            $this->u = Uzivatel::prihlasId($id);

            if (post('aPrihlasit')) {
                oznameniPresmeruj(htmlspecialchars(\t('registrace.ok_a_prihlaska')), 'prihlaska');
            } else {
                oznameni(htmlspecialchars(\t('registrace.ok')));
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
        if (! post('upravit')) {
            return;
        }
        if ($this->ulozZmeny()) {
            oznameni(htmlspecialchars(\t('registrace.uprava_ok')));
        }
    }

    public function zpracujOdhlaseniOstatnichZarizeni(): void
    {
        if (! $this->u || ! post('odhlasitOstatniZarizeni')) {
            return;
        }
        $this->u->odhlasNaOstatnichZarizenich();
        oznameni(htmlspecialchars(\t('registrace.zarizeni.odhlaseno', ['pohlavi' => $this->u->pohlavi()])));
    }

    public function ulozZmeny(): bool
    {
        try {
            if (! $this->u) {
                throw Chyby::jedna(htmlspecialchars(\t('registrace.chyba.odhlaseno')));
            }

            $formData = post(self::FORM_DATA_KEY);
            if ($formData === null) {
                return false;
            }

            $idUlozenehoUzivatele = $this->u->uprav((array) $formData);

            return (bool) $idUlozenehoUzivatele;
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
     *
     * @param string $nazev prostý text
     * @param string $placeholder prostý text
     */
    private function input(
        string $nazev,
        string $typ,
        string $klic,
        ?bool $required = null,
        string $inputCss = '',
        string $predrazeneHtml = '',
        string $placeholder = ' ', // schválně mezera
    ): string {
        // údaje z DB i z odeslaného formuláře, přezdívka s uvozovkou nesmí ukončit atribut
        $predvyplneno = htmlspecialchars((string) ($this->formData()[$klic] ?? ''));

        $required ??= array_key_exists($klic, Uzivatel::povinneUdajeProRegistraci());
        $requiredHtml = $required || $typ === 'date'
            ? 'required'
            : '';
        $disabledHtml = $this->jeUdajZamcenyPoKontrole($klic)
            ? 'disabled'
            : '';
        $additionalHtml = $typ === 'password'
            ? 'autocomplete="new-password"'
            // aby se nám automaticky nevkládalo heslo
            : '';

        $chybaHtml = '';
        $chybaTrida = '';
        if ($chyba = $this->chyby()->klic($klic)) {
            $chybaHtml = '<div class="formular_chyba">' . $chyba . '</div>';
            $chybaTrida = 'formular_polozka-chyba';
        }

        $labelRequired = $this->labelRequired($requiredHtml);
        $placeholderHtml = htmlspecialchars($placeholder);

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
                    placeholder="{$placeholderHtml}"
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

    /**
     * @param string $nazev prostý text
     * @param string $idPole s ním je titulek <label for>, pro položku, kterou celou neobaluje <label>
     */
    private function hlavickaPolozky(string $nazev, string $labelRequired, string $tooltip = '', string $idPole = ''): string
    {
        $nazevHtml = htmlspecialchars($nazev);
        $titulek = $idPole !== ''
            ? "<label class=\"formular_polozkaTitulek\" for=\"{$idPole}\">{$nazevHtml}{$labelRequired}</label>"
            : "<span class=\"formular_polozkaTitulek\">{$nazevHtml}{$labelRequired}</span>";

        return <<<HTML
<div class="formular_polozkaNadpis">
    {$titulek}
    {$tooltip}
</div>
HTML;
    }

    private function formData(): ?array
    {
        if ($this->formData === 'undefined') {
            $postData = post(self::FORM_DATA_KEY);
            if ($postData !== null) {
                $this->formData = array_merge($this->dataUzivatele(), (array) $postData);
            } else {
                $this->formData = $this->dataUzivatele();
            }
            $this->formData[Sql::ZPUSOB_ZOBRAZENI_NA_WEBU] ??= ZpusobZobrazeniNaWebu::vychozi()->value;
        }

        return $this->formData;
    }

    private function dataUzivatele(): array
    {
        $dataUzivatele = (array) ($this->u?->rawDb() ?? []);
        if (! empty($dataUzivatele[Sql::OP])) {
            $dataUzivatele[Sql::OP] = \Sifrovatko::desifruj($dataUzivatele[Sql::OP]);
        }

        return $dataUzivatele;
    }

    /**
     * @param string $medailonekHtml sekce medailonku pro vypravěče (@see MedailonekVNastaveni), u ostatních prázdná
     */
    public function zobrazHtml(string $medailonekHtml = '')
    {
        ?>
      <div class="formular_stranka">
      <form method="post">
        <div class="bg"></div>

          <?php
          if ($this->u) { ?>
            <div class="formular_strankaNadpis"><?php echo htmlspecialchars(\t('registrace.nastaveni')); ?></div>
              <?php
          } else { ?>
            <div class="formular_strankaNadpis"><?php echo htmlspecialchars(\t('registrace.nazev')); ?></div>
            <div class="formular_strankaPodtitul">
              <div style="max-width: 250px">
                <?php echo htmlspecialchars(\t('registrace.podtitul')); ?>
              </div>
            </div>

            <div class="formular_prihlasit formular_duleziteInfo">
              <?php echo htmlspecialchars(\t('registrace.mam_ucet')); ?> <a href="prihlaseni"><?php echo htmlspecialchars(\t('registrace.prihlasit_se')); ?></a>
            </div>

              <?php
              if (pred($this->systemoveNastaveni->prihlasovaniUcastnikuOd())) { ?>
                <div class="formular_infobox">
                  <b><?php echo $this->zacatekPrihlasovaniHtml(); ?></b>
                  <?php echo htmlspecialchars(\t('registrace.ucet_predem')); ?>
                </div>
                  <?php
              } ?>
              <?php
          } ?>

        <div>
          <h2 class="formular_sekceNadpis" style="float: left"><?php echo htmlspecialchars(\t('registrace.osobni.nadpis')); ?></h2>

          <div class="formular_tooltip">
            <div class="gc_tooltip" tabindex="0">
              <?php echo htmlspecialchars(\t('registrace.osobni.proc')); ?>
              <div class="tooltip_obsah">
                <?php echo htmlspecialchars(\t('registrace.osobni.potrebujeme_je')); ?><br>
                <ul>
                  <li><?php echo htmlspecialchars(\t('registrace.osobni.identifikace')); ?></li>
                  <li><?php echo htmlspecialchars(\t('registrace.osobni.odbaveni')); ?></li>
                  <li><?php echo htmlspecialchars(\t('registrace.osobni.ubytovani')); ?></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
          <?php echo $this->input(\t('registrace.email'), 'email', Sql::EMAIL1_UZIVATELE); ?>

          <?php echo $this->input(\t('registrace.telefon'), 'text', Sql::TELEFON_UZIVATELE, true, '', '', \t('registrace.telefon_priklad')); ?>

          <?php echo $this->povinneUdajeProUbytovaniHtml(vyzadovatAdresuADoklad: false); ?>

        <h2 class="formular_sekceNadpis"><?php echo htmlspecialchars(\t('registrace.ostatni')); ?></h2>

        <div class="formular_sloupce">
            <?php echo $this->input(\t('registrace.prezdivka'), 'text', Sql::LOGIN_UZIVATELE); ?>
            <?php echo $this->select(
                nazev: \t('registrace.zobrazeni_na_webu.nazev'),
                klic: Sql::ZPUSOB_ZOBRAZENI_NA_WEBU,
                moznosti: ZpusobZobrazeniNaWebu::proSelect(),
                tooltip: $this->tooltipHtml(
                    \t('registrace.zobrazeni_na_webu.kde'),
                    htmlspecialchars(\t('registrace.zobrazeni_na_webu.jen_verejny_web'))
                    . "\n      " . htmlspecialchars(\t('registrace.zobrazeni_na_webu.administrace')),
                ),
            ); ?>
          <div style="float:left">
              <?php echo $this->select(
                  nazev: \t('registrace.pohlavi.nazev'),
                  klic: Sql::POHLAVI,
                  moznosti: Pohlavi::seznamProSelect(),
                  tooltip: $this->tooltipHtml(
                      \t('registrace.pohlavi.proc'),
                      htmlspecialchars(\t('registrace.pohlavi.jen_interne')) . '<br>
      <ul>
        <li>' . htmlspecialchars(\t('registrace.pohlavi.ubytovani')) . '</li>
        <li>' . htmlspecialchars(\t('registrace.pohlavi.mista')) . '</li>
      </ul>
      <div>
        ' . htmlspecialchars(\t('registrace.pohlavi.vyplneni')) . '
      </div>',
                  ),
              ); ?>
          </div>
        </div>

        <div class="formular_sloupce">
            <?php echo $this->input(\t('registrace.heslo'), 'password', 'heslo', $this->u === null); ?>
            <?php echo $this->input(\t('registrace.heslo_kontrola'), 'password', 'heslo_kontrola', $this->u === null); ?>
        </div>

        <div style="margin: 30px 0 40px" class="formular_polozka-checkbox">
          <label style="float: left; margin-right: 1em">
            <input type="checkbox" required <?php echo $this->souhlasilSeZpracovanimOsobnichUdaju()
                ? 'checked'
                : ''; ?>>
            <span class="formular_duleziteInfo">
                  <?php echo htmlSVlozkami(
                      \t('registrace.souhlas.veta', ['odkaz' => znackaVlozky('odkaz')]),
                      ['odkaz' => '<a href="legal" target="_blank">' . htmlspecialchars(\t('registrace.souhlas.odkaz')) . '</a>'],
                  ); ?>
            </span>
          </label>
          <div class="formular_tooltip">
            <div class="gc_tooltip" tabindex="0">
              <?php echo htmlspecialchars(\t('registrace.souhlas.shrnuti')); ?>
              <div class="tooltip_obsah">
                <?php echo htmlspecialchars(\t('registrace.souhlas.komu_predame')); ?><br><br>
                <?php echo htmlspecialchars(\t('registrace.souhlas.kontakt')); ?><br><br>
                <?php echo htmlSVlozkami(
                    \t('registrace.souhlas.plne_zneni', ['odkaz' => znackaVlozky('odkaz')]),
                    ['odkaz' => '<a href="legal" target="_blank">' . htmlspecialchars(\t('registrace.souhlas.plne_zneni_odkaz')) . '</a>'],
                ); ?>
              </div>
            </div>
          </div>
        </div>

        <div class="clearfix"></div>

          <?php
          if ($this->u) { ?>
            <input type="hidden" name="upravit" value="true">
            <input type="submit" value="<?php echo htmlspecialchars(\t('registrace.ulozit')); ?>" class="formular_primarni">
              <?php
          } else { ?>
            <input type="hidden" name="registrovat" value="true">
            <?php echo altchaWidget(); ?>
              <?php
              if ($this->systemoveNastaveni->prihlasovaniUcastnikuSpusteno()) { ?>
                <input type="submit" name="aPrihlasit" value="<?php echo htmlspecialchars(\t('registrace.prihlasit_na_gc')); ?>" class="formular_primarni">
                <input type="submit" value="<?php echo htmlspecialchars(\t('registrace.jen_vytvorit_ucet')); ?>" class="formular_sekundarni">
                  <?php
              } else { ?>
                <div class="gc_tooltip" tabindex="0">
                  <input type="submit" name="aPrihlasit" value="<?php echo htmlspecialchars(\t('registrace.prihlasit_na_gc')); ?>" class="formular_primarni"
                         disabled>
                  <div class="tooltip_obsah">
                    <?php echo $this->zacatekPrihlasovaniHtml(); ?>. <?php echo htmlspecialchars(\t('registrace.ucet_predem')); ?>
                  </div>
                </div>
                <input type="submit" value="<?php echo htmlspecialchars(\t('registrace.jen_vytvorit_ucet')); ?>" class="formular_sekundarni">
                  <?php
              } ?>
              <?php
          } ?>

        <!-- workaround: rezervace místa pro tooltip souhlasu -->
        <div style="height: 30px"></div>
      </form>

        <?php
        echo $medailonekHtml;
        if ($this->u) { ?>
          <!-- vlastní formulář pod tlačítkem Uložit, aby se nastavení neukládalo ani nekontrolovalo -->
          <form method="post">
            <h2 class="formular_sekceNadpis"><?php echo htmlspecialchars(\t('registrace.zarizeni.nadpis')); ?></h2>
            <p>
              <?php echo htmlspecialchars(\t('registrace.zarizeni.popis', ['pohlavi' => $this->u->pohlavi()])); ?>
            </p>
            <input type="hidden" name="odhlasitOstatniZarizeni" value="true">
            <button type="submit" class="formular_sekundarni" style="margin-left: 0">
              <?php echo htmlspecialchars(\t('registrace.zarizeni.odhlasit_ostatni')); ?>
            </button>
          </form>

          <h2 class="formular_sekceNadpis"><?php echo htmlspecialchars(\t('registrace.jine_zarizeni.nadpis')); ?></h2>
          <p>
            <?php echo htmlSVlozkami(
                \t('registrace.jine_zarizeni.popis', ['tlacitko' => znackaVlozky('tlacitko')]),
                ['tlacitko' => '<b>' . htmlspecialchars(\t('prihlaseni.qr_kodem')) . '</b>'],
            ); ?>
          </p>
          <?php
          // texty čtečky (soubory/ctecka-qr.js) v jazyce stránky
          $zpravyCtecky = [
              'nacteno'           => \t('registrace.jine_zarizeni.nacteno'),
              'ciziKod'           => \t('registrace.jine_zarizeni.cizi_kod'),
              'bezFotoaparatu'    => \t('registrace.jine_zarizeni.bez_fotoaparatu'),
              'namir'             => \t('registrace.jine_zarizeni.namir'),
              'fotoaparatZakazan' => \t('registrace.jine_zarizeni.fotoaparat_zakazan'),
              'fotoaparatSelhal'  => \t('registrace.jine_zarizeni.fotoaparat_selhal'),
          ];
          ?>
          <button type="button" class="formular_sekundarni" style="margin-left: 0"
                  data-ctecka-qr
                  data-adresa-schvaleni="<?php echo htmlspecialchars(URL_WEBU . '/' . PrihlaseniQrKodem::STRANKA_SCHVALENI); ?>"
                  data-jsqr="<?php echo htmlspecialchars(URL_WEBU . '/soubory/jsqr/jsQR.js?version=' . md5_file(WWW . '/soubory/jsqr/jsQR.js')); ?>"
                  data-zpravy="<?php echo htmlspecialchars(json_encode($zpravyCtecky, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); ?>">
            <?php echo htmlspecialchars(\t('registrace.jine_zarizeni.nacist_qr')); ?>
          </button>
          <div class="cteckaQr" data-ctecka-qr-okno hidden>
            <video class="cteckaQr_video" playsinline muted></video>
            <p class="cteckaQr_zprava" data-ctecka-qr-zprava aria-live="polite"></p>
            <button type="button" class="formular_sekundarni" style="margin-left: 0" data-ctecka-qr-zavrit>
              <?php echo htmlspecialchars(\t('registrace.jine_zarizeni.zavrit')); ?>
            </button>
          </div>
            <?php
        } ?>
      </div>
        <?php
    }

    /**
     * Ikonka s otazkou a vysvětlením v hlavičce položky.
     *
     * @param string $otazka prostý text
     * @param string $obsahHtml HTML
     */
    private function tooltipHtml(string $otazka, string $obsahHtml): string
    {
        $otazkaHtml = htmlspecialchars($otazka);

        return <<<HTML
<div class="formular_tooltip">
  <div class="gc_tooltip" tabindex="0">
    {$otazkaHtml}
    <div class="tooltip_obsah">
      {$obsahHtml}
    </div>
  </div>
</div>
HTML;
    }

    /**
     * „Přihlašování na GameCon se spustí 15. 5. 2026 ve 20:00“, mezi číslem dne a měsíce nezlomitelná mezera.
     */
    private function zacatekPrihlasovaniHtml(): string
    {
        return htmlspecialchars(\t(
            'registrace.prihlasovani_se_spusti',
            ['zacatek' => $this->systemoveNastaveni->prihlasovaniUcastnikuOd()],
        ));
    }

    private function souhlasilSeZpracovanimOsobnichUdaju(): bool
    {
        return $this->u || post('registrovat');
    }

    /**
     * @param string $nazev prostý text
     * @param array<int|string, string> $moznosti hodnota => popis (prostý text)
     */
    private function select(
        string $nazev,
        string $klic,
        array $moznosti,
        bool $required = true,
        string $tooltip = '',
    ): string {
        $vybranaHodnota = $this->formData()[$klic] ?? '';
        $maVybranouHodnotu = $vybranaHodnota !== '' && $vybranaHodnota !== null;

        $moznostiHtml = '<option disabled value ' . ($maVybranouHodnotu
                ? ''
                : 'selected') . '></option>';
        foreach ($moznosti as $hodnota => $popis) {
            $selected = (string) $vybranaHodnota === (string) $hodnota;
            $selectedHtml = $selected
                ? 'selected'
                : '';
            $moznostiHtml .= '<option value="' . $hodnota . '" ' . $selectedHtml . '>' . htmlspecialchars($popis) . '</option>';
        }

        $chybaHtml = '';
        $chybaTrida = '';
        if ($chyba = $this->chyby()->klic($klic)) {
            $chybaHtml = '<div class="formular_chyba">' . $chyba . '</div>';
            $chybaTrida = 'formular_polozka-chyba';
        }

        $requiredHtml = $required
            ? 'required'
            : '';
        $disabledHtml = $this->jeUdajZamcenyPoKontrole($klic)
            ? 'disabled'
            : '';

        $labelRequired = $this->labelRequired($requiredHtml);
        $idPole = "select_{$klic}";

        // Položku neobaluje <label>, jen titulek je <label for>: ťuknutí na tooltip v hlavičce by jinak
        // předalo klik selectu a na iPhonu otevřelo výběr, který tooltip u spodního okraje zakryje.
        return <<<HTML
            <div class="formular_polozka {$chybaTrida}">
                {$this->hlavickaPolozky($nazev, $labelRequired, $tooltip, $idPole)}
                <select id="{$idPole}" name="{$this->inputName()}[{$klic}]" {$requiredHtml} {$disabledHtml}>
                {$moznostiHtml}
                </select>
                {$chybaHtml}
            </div>
        HTML;
    }

    /**
     * Vrací true, pokud je daný údaj zamčený kvůli proběhlé kontrole na infopultu.
     * Ve webovém formuláři se takové pole renderuje jako disabled a změna přes Uzivatel::uprav se neuloží.
     */
    private function jeUdajZamcenyPoKontrole(string $klic): bool
    {
        if (! $this->u || ! $this->u->maZkontrolovaneUdaje()) {
            return false;
        }

        return in_array($klic, Uzivatel::zamceneUdajePoKontroleNaInfopultu(), true);
    }

    /**
     * @param string $nadpis prostý text
     * @param string $tooltip HTML
     */
    public function povinneUdajeProUbytovaniHtml(
        string $nadpis = '',
        string $tooltip = '',
        bool $vyzadovatAdresuADoklad = true,
    ): string {
        $nadpisHtml = '';
        if ($nadpis !== '') {
            $nadpisHtml = "<h2 class='formular_sekceNadpis'>" . htmlspecialchars($nadpis) . '</h2>';
        }
        $tooltipHtml = '';
        if ($tooltip !== '') {
            $tooltipHtml = <<<HTML
<div style="float: right">
    <div class="gc_tooltip" tabindex="0" style="position: relative; top: -4em;">
        ℹ️
        <div class="tooltip_obsah" style="right: -247px; top: 2em;">
            {$tooltip}
        </div>
    </div>
</div>
HTML;
        }

        $h = htmlspecialchars(...);

        return <<<HTML
{$nadpisHtml}

{$tooltipHtml}

<div class="formular_sloupce">
    {$this->input(\t('registrace.povinne_udaje.jmeno'), 'text', Sql::JMENO_UZIVATELE)}
    {$this->input(\t('registrace.povinne_udaje.prijmeni'), 'text', Sql::PRIJMENI_UZIVATELE)}
    {$this->input(\t('registrace.povinne_udaje.datum_narozeni'), 'date', Sql::DATUM_NAROZENI)}
    {$this->input(
            nazev: \t('registrace.povinne_udaje.statni_obcanstvi'),
            typ: 'text',
            klic: Sql::STATNI_OBCANSTVI,
            required: true,
            placeholder: \t('registrace.povinne_udaje.statni_obcanstvi_priklad'),
        )}
</div>

<div>
    <h2 class="formular_sekceNadpis" style="float: left">{$h(\t('registrace.povinne_udaje.adresa.nadpis'))}</h2>

    <div class="formular_tooltip">
        <div class="gc_tooltip" tabindex="0">
            {$h(\t('registrace.povinne_udaje.adresa.proc'))}
            <div class="tooltip_obsah">
                {$this->proUbytovateleHtml(\t('registrace.povinne_udaje.adresa.ze_zakona'))}
            </div>
        </div>
    </div>
</div>
<div class="clearfix"></div>

<div class="formular_sloupce">
    {$this->input(\t('registrace.povinne_udaje.adresa.ulice'), 'text', Sql::ULICE_A_CP_UZIVATELE, $vyzadovatAdresuADoklad)}
    {$this->input(\t('registrace.povinne_udaje.adresa.mesto'), 'text', Sql::MESTO_UZIVATELE, $vyzadovatAdresuADoklad)}
    {$this->input(\t('registrace.povinne_udaje.adresa.psc'), 'text', Sql::PSC_UZIVATELE, $vyzadovatAdresuADoklad)}
    {$this->select(
            \t('registrace.povinne_udaje.adresa.zeme'),
            Sql::STAT_UZIVATELE,
            [
                Stat::CZ_ID   => \t('registrace.povinne_udaje.adresa.cesko'),
                Stat::SK_ID   => \t('registrace.povinne_udaje.adresa.slovensko'),
                Stat::JINY_ID => \t('registrace.povinne_udaje.adresa.jiny_stat'),
            ],
            required: $vyzadovatAdresuADoklad,
        )}
</div>

<div>
    <h2 class="formular_sekceNadpis" style="float: left">{$h(\t('registrace.povinne_udaje.doklad.nadpis'))}</h2>

    <div class="formular_tooltip">
        <div class="gc_tooltip" tabindex="0">
            {$h(\t('registrace.povinne_udaje.doklad.proc'))}
            <div class="tooltip_obsah">
                {$this->proUbytovateleHtml(\t('registrace.povinne_udaje.doklad.ze_zakona'))}
            </div>
        </div>
    </div>
</div>
<div class="clearfix"></div>

<div class="formular_sloupce">
    {$this->select(
            \t('registrace.povinne_udaje.doklad.typ'),
            Sql::TYP_DOKLADU_TOTOZNOSTI,
            [
                Uzivatel::TYP_DOKLADU_OP   => \t('registrace.povinne_udaje.doklad.op'),
                Uzivatel::TYP_DOKLADU_PAS  => \t('registrace.povinne_udaje.doklad.pas'),
                Uzivatel::TYP_DOKLADU_JINY => \t('registrace.povinne_udaje.doklad.jiny'),
            ],
            required: $vyzadovatAdresuADoklad,
        )}
    {$this->input(\t('registrace.povinne_udaje.doklad.cislo'), 'text', Sql::OP, $vyzadovatAdresuADoklad)}
</div>
HTML;
    }

    /**
     * Proč údaj potřebuje ubytovatel, komu ho předáme a co se stane, když je chybně.
     *
     * @param string $zeZakona prostý text
     */
    private function proUbytovateleHtml(string $zeZakona): string
    {
        $ubytovatel = htmlSVlozkami(
            \t('registrace.povinne_udaje.ubytovatel_veta', ['ubytovatel' => znackaVlozky('ubytovatel')]),
            ['ubytovatel' => '<b>' . htmlspecialchars(\t('registrace.povinne_udaje.ubytovatel')) . '</b>'],
        );
        $chybneVyplneni = htmlspecialchars(\t('registrace.povinne_udaje.chybne_vyplneni'));

        return htmlspecialchars($zeZakona) . "<br><br>\n                {$ubytovatel}<br><br>\n                {$chybneVyplneni}";
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
