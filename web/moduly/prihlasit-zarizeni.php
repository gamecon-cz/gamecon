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
$this->info()->nazev(t('prihlasit_zarizeni.nazev'));
$this->setObsahPrelozeny(true);

$kod = (string) get(PrihlaseniQrKodem::PARAMETR_KODU);
// Po „Přihlásit se jako“ by schválení přihlásilo účet někoho jiného.
$prepnuto = PrepnutiNaUzivatele::zSession()->isPrepnuto();

if ($u !== null && ! $prepnuto) {
    if (post('schvalit')) {
        $vysledek = PrihlaseniQrKodem::schval(
            $kod,
            $u->id(),
            Uzivatel::getRazitkoPrihlaseniZSession(),
            (int) post('cislo'),
        );
        match ($vysledek) {
            PrihlaseniQrKodem::SCHVALENO    => oznameni(htmlspecialchars(t('prihlasit_zarizeni.schvaleno'))),
            PrihlaseniQrKodem::SPATNE_CISLO => chyba(htmlspecialchars(t('prihlasit_zarizeni.spatne_cislo', ['pohlavi' => $u->pohlavi()]))),
            default                         => chyba(htmlspecialchars(t('prihlasit_zarizeni.vyrizeno'))),
        };
    }
    if (post('zamitnout')) {
        if (PrihlaseniQrKodem::zamitni($kod, $u->id())) {
            oznameni(htmlspecialchars(t('prihlasit_zarizeni.zamitnuto', ['pohlavi' => $u->pohlavi()])));
        }
        // co se s kódem mezitím stalo (schválení, vypršení), ukáže stránka
        chyba(htmlspecialchars(t('prihlasit_zarizeni.nelze_zamitnout')));
    }
}

?>

<div class="formular formular_stranka formular_stranka-login prihlaseniQr">
  <div class="bg"></div>

  <div class="formular_strankaNadpis"><?php echo htmlspecialchars(t('prihlasit_zarizeni.nazev')); ?></div>

  <h1 class="prihlaseniQr_nadpis"><?php echo htmlspecialchars(t('prihlasit_zarizeni.nadpis')); ?></h1>

    <?php
    if ($u === null) { ?>
      <p><?php echo htmlspecialchars(t('prihlasit_zarizeni.prihlas_se')); ?></p>
      <p>
        <a class="formular_primarni" href="<?php echo htmlspecialchars(PrihlaseniQrKodem::getUrlPrihlaseniPredSchvalenim($kod)); ?>">
          <?php echo htmlspecialchars(t('prihlaseni.prihlasit_se')); ?>
        </a>
      </p>
      <p><?php echo htmlspecialchars(t('prihlasit_zarizeni.platnost', ['minut' => intdiv(PrihlaseniQrKodem::PLATNOST_SEKUND, 60)])); ?></p>
        <?php
    } elseif ($prepnuto) { ?>
      <p><?php echo htmlspecialchars(t('prihlasit_zarizeni.prepnuto')); ?></p>
        <?php
    } else {
        $zadost = PrihlaseniQrKodem::najdiProSchvaleni($kod);
        if ($zadost === null) { ?>
          <p><?php echo htmlspecialchars(t('prihlasit_zarizeni.neznamy_kod')); ?></p>
            <?php
        } elseif ($zadost['stav'] === PrihlaseniQrKodem::STAV_SCHVALENO) { ?>
          <p><?php echo htmlspecialchars(t('prihlasit_zarizeni.stav_schvaleno')); ?></p>
            <?php
        } elseif ($zadost['stav'] === PrihlaseniQrKodem::STAV_ZAMITNUTO) { ?>
          <p><?php echo htmlspecialchars(t('prihlasit_zarizeni.stav_zamitnuto')); ?></p>
            <?php
        } elseif ($zadost['stav'] === PrihlaseniQrKodem::STAV_VYPRSELO) { ?>
          <p><?php echo htmlspecialchars(t('prihlasit_zarizeni.stav_vyprselo')); ?></p>
            <?php
        } else {
            $predSekundami = $zadost['pred_sekundami'];
            ?>
          <p>
            <?php echo htmlSVlozkami(
                t('prihlasit_zarizeni.prihlasis', [
                    'kam'   => $zadost['do_adminu']
                        ? 'admin'
                        : 'web',
                    'jmeno' => znackaVlozky('jmeno'),
                ]),
                ['jmeno' => '<b>' . htmlspecialchars((string) $u->jmenoNick()) . '</b>'],
            ); ?>
          </p>

          <dl class="prihlaseniQr_zarizeni">
            <dt><?php echo htmlspecialchars(t('prihlasit_zarizeni.prohlizec')); ?></dt>
            <dd><?php echo htmlspecialchars($zadost['prohlizec']); ?></dd>
            <dt><?php echo htmlspecialchars(t('prihlasit_zarizeni.ip_adresa')); ?></dt>
            <dd><?php echo htmlspecialchars($zadost['ip']); ?></dd>
            <dt><?php echo htmlspecialchars(t('prihlasit_zarizeni.kod_vznikl')); ?></dt>
            <dd><?php echo htmlspecialchars($predSekundami < 60
                    ? t('prihlasit_zarizeni.pred_sekundami', ['sekund' => $predSekundami])
                    : t('prihlasit_zarizeni.pred_minutami', ['minut' => intdiv($predSekundami, 60)])); ?></dd>
          </dl>

          <p class="prihlaseniQr_varovani">
            <?php echo htmlspecialchars(t('prihlasit_zarizeni.varovani')); ?>
          </p>

          <form method="post">
            <p><b><?php echo htmlspecialchars(t('prihlasit_zarizeni.vyber_cislo')); ?></b></p>
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

          <p><?php echo htmlspecialchars(t('prihlasit_zarizeni.nemas_zarizeni')); ?></p>
          <form method="post">
            <button type="submit" name="zamitnout" value="1" class="formular_negativni"><?php echo htmlspecialchars(t('prihlasit_zarizeni.zamitnout')); ?></button>
          </form>
            <?php
        }
    } ?>
</div>
