<?php

declare(strict_types=1);

use Gamecon\Uzivatel\PrepnutiNaUzivatele;
use Gamecon\Uzivatel\PrihlaseniQrKodem;

/**
 * Schválení přihlášení jiného zařízení QR kódem, sem vede odkaz z kódu. Schvaluje přihlášený uživatel za sebe,
 * vybráním čísla, které ukazuje zařízení. Viz PrihlaseniQrKodem.
 *
 * @var Modul         $this
 * @var Uzivatel|null $u
 */

// Schvaluje se jedním kliknutím, stránka proto nesmí být v rámu cizí stránky (clickjacking). Platí i pro
// subdomény gamecon.cz, které cookie session dostanou (preview spouští kód libovolné větve).
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");

$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev('Přihlásit zařízení');

$kod = (string) get(PrihlaseniQrKodem::PARAMETR_KODU);
// Po „Přihlásit se jako“ by schválení přihlásilo účet někoho jiného.
$prepnuto = PrepnutiNaUzivatele::zSession()->isPrepnuto();

if ($u !== null && ! $prepnuto) {
    $a = $u->koncovkaDlePohlavi();
    if (post('schvalit')) {
        $vysledek = PrihlaseniQrKodem::schval(
            $kod,
            $u->id(),
            Uzivatel::getRazitkoPrihlaseniZSession(),
            (int) post('cislo'),
        );
        match ($vysledek) {
            PrihlaseniQrKodem::SCHVALENO    => oznameni('Hotovo, zařízení se teď přihlásí.'),
            PrihlaseniQrKodem::SPATNE_CISLO => chyba(
                "Vybral{$a} jsi jiné číslo, než ukazuje zařízení, a tak jsme přihlášení zamítli. Jestli kód nebyl"
                . ' z tvé obrazovky, chtěl se k tvému účtu přihlásit někdo jiný.',
            ),
            default => chyba('Kód mezitím vypršel nebo už je vyřízený. Na zařízení vygeneruj nový.'),
        };
    }
    if (post('zamitnout')) {
        if (PrihlaseniQrKodem::zamitni($kod, $u->id())) {
            oznameni('Přihlášení jsi zamítl' . $a . '.');
        }
        // co se s kódem mezitím stalo (schválení, vypršení), ukáže stránka
        chyba('Zamítnout už nešlo, kód mezitím vypršel nebo ho někdo vyřídil.');
    }
}

?>

<div class="formular formular_stranka formular_stranka-login prihlaseniQr">
  <div class="bg"></div>

  <div class="formular_strankaNadpis">Přihlásit zařízení</div>

  <h1 class="prihlaseniQr_nadpis">Přihlásit jiné zařízení</h1>

    <?php
    if ($u === null) { ?>
      <p>Ke schválení musíš být na tomhle telefonu přihlášený na webu GameConu. Po přihlášení tě vrátíme sem.</p>
      <p>
        <a class="formular_primarni" href="<?php echo htmlspecialchars(PrihlaseniQrKodem::getUrlPrihlaseniPredSchvalenim($kod)); ?>">
          Přihlásit se
        </a>
      </p>
      <p>Kód platí jen <?php echo intdiv(PrihlaseniQrKodem::PLATNOST_SEKUND, 60); ?> minuty. Když mezitím vyprší,
        vygeneruj na zařízení nový a naskenuj ho znovu.</p>
        <?php
    } elseif ($prepnuto) { ?>
      <p>Jsi přepnutý na jiného uživatele. Přihlášení zařízení schval ze svého účtu.</p>
        <?php
    } else {
        $zadost = PrihlaseniQrKodem::najdiProSchvaleni($kod);
        if ($zadost === null) { ?>
          <p>Tenhle kód neznáme. Naskenuj ho prosím znovu.</p>
            <?php
        } elseif ($zadost['stav'] === PrihlaseniQrKodem::STAV_SCHVALENO) { ?>
          <p>Přihlášení je schválené, zařízení se během chvilky přihlásí.</p>
            <?php
        } elseif ($zadost['stav'] === PrihlaseniQrKodem::STAV_ZAMITNUTO) { ?>
          <p>Přihlášení je zamítnuté. Kdo se chce přihlásit, musí vygenerovat nový kód.</p>
            <?php
        } elseif ($zadost['stav'] === PrihlaseniQrKodem::STAV_VYPRSELO) { ?>
          <p>Kód vypršel. Na zařízení vygeneruj nový a naskenuj ho znovu.</p>
            <?php
        } else {
            $predSekundami = $zadost['pred_sekundami'];
            ?>
          <p>
            Přihlásíš <?php echo $zadost['do_adminu']
                  ? 'do administrace GameConu'
                  : 'na web GameConu'; ?> zařízení, které kód ukazuje, a to jako
            <b><?php echo htmlspecialchars((string) $u->jmenoNick()); ?></b>.
          </p>

          <dl class="prihlaseniQr_zarizeni">
            <dt>Prohlížeč</dt>
            <dd><?php echo htmlspecialchars($zadost['prohlizec']); ?></dd>
            <dt>IP adresa</dt>
            <dd><?php echo htmlspecialchars($zadost['ip']); ?></dd>
            <dt>Kód vznikl</dt>
            <dd><?php echo $predSekundami < 60
                    ? "před {$predSekundami} s"
                    : 'před ' . intdiv($predSekundami, 60) . ' min'; ?></dd>
          </dl>

          <p class="prihlaseniQr_varovani">
            Číslo vyber jen podle obrazovky zařízení, které máš před sebou. Když ti kód nebo číslo někdo poslal
            nebo ti říká, které vybrat, chce se dostat k tvému účtu: přihlášení zamítni.
          </p>

          <form method="post">
            <p><b>Vyber číslo, které ukazuje zařízení:</b></p>
            <div class="prihlaseniQr_cisla">
                <?php
                foreach ($zadost['cisla'] as $cislo) { ?>
                  <button type="submit" name="cislo" value="<?php echo $cislo; ?>"
                          class="formular_sekundarni prihlaseniQr_volbaCisla"><?php echo $cislo; ?></button>
                    <?php
                } ?>
            </div>
            <input type="hidden" name="schvalit" value="1">
          </form>

          <p>Nemáš to zařízení před sebou, nebo ti kód či číslo někdo poslal? Přihlášení zamítni.</p>
          <form method="post">
            <button type="submit" name="zamitnout" value="1" class="formular_negativni">Zamítnout</button>
          </form>
            <?php
        }
    } ?>
</div>
