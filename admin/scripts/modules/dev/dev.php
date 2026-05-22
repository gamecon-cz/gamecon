<?php

use Gamecon\Cas\DateTimeCz;
use Gamecon\SystemoveNastaveni\AnonymizovanaDatabaze;

/**
 * Rozcestník pro vývojářské nástroje.
 *
 * Naviguje na Previews / Staré ročníky / SQL update.
 * Anonymizovaná databáze se generuje cronem
 * (admin/cron/anonymizace_databaze.php) jednou denně; tato stránka
 * jen zpřístupní poslední vygenerovaný soubor ke stažení rovnou
 * z rozcestníku (single-action, nemá smysl mít vlastní podstránku).
 *
 * nazev: Dev
 * pravo: 113
 * submenu_group: 1
 * submenu_order: 1
 * submenu_nazev: Přehled
 */
const STAHNOUT_ANONYM_DB_KLIC = 'stahnout_anonymizovanou_db';

if (! empty($_POST[STAHNOUT_ANONYM_DB_KLIC])) {
    try {
        AnonymizovanaDatabaze::vytvorZGlobals()->exportuj();
        exit;
    } catch (RuntimeException $exception) {
        chyba($exception->getMessage());
    }
}

$datumAnonymExportu = AnonymizovanaDatabaze::datumPoslednihoExportu();
?>
<style>
    .dev-rozcestnik {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }
    .dev-rozcestnik > a,
    .dev-rozcestnik > form > button {
        display: block;
        width: 100%;
        padding: 16px;
        border: 1px solid #ddd;
        border-radius: 6px;
        text-decoration: none;
        text-align: left;
        color: inherit;
        background: #fafafa;
        font: inherit;
        cursor: pointer;
    }
    .dev-rozcestnik > a:hover,
    .dev-rozcestnik > form > button:hover {
        background: #f0f0f0;
        border-color: #888;
    }
    .dev-rozcestnik h3 {
        margin: 0 0 8px;
    }
    .dev-rozcestnik p {
        margin: 0;
        color: #555;
        font-size: 0.9em;
    }
    .dev-rozcestnik form {
        margin: 0;
    }
</style>
<div class="dev-rozcestnik">
    <a href="<?php echo URL_ADMIN; ?>/dev/previews">
        <h3>Previews</h3>
        <p>Aktivní preview prostředí (dockerizovaná nasazení feature větví).</p>
    </a>
    <a href="<?php echo URL_ADMIN; ?>/dev/stare-rocniky">
        <h3>Staré ročníky</h3>
        <p>Seznam URL archivovaných ročníků (per-year docker kontejnery).</p>
    </a>
    <form method="post">
        <button type="submit" value="1" name="<?php echo STAHNOUT_ANONYM_DB_KLIC; ?>" class="without-safety-unlock">
            <h3>Anonymizovaná databáze</h3>
            <?php if ($datumAnonymExportu) { ?>
                <p>
                    Stáhnout dump z
                    <?php echo htmlspecialchars(
                        $datumAnonymExportu->format(DateTimeCz::FORMAT_DB)
                        . ' (' . DateTimeCz::createFromInterface($datumAnonymExportu)->stari() . ')',
                    ); ?>
                </p>
            <?php } else { ?>
                <p>Dump zatím nebyl vygenerován. Generuje se automaticky jednou denně.</p>
            <?php } ?>
        </button>
    </form>
    <a href="<?php echo URL_ADMIN; ?>/dev/update-zustatku">
        <h3>SQL update pro uzavření financí</h3>
        <p>Vygenerování SQL pro překlápění ročníku (uzavření financí).</p>
    </a>
</div>
