<?php

use Gamecon\Cas\DateTimeCz;
use Gamecon\SystemoveNastaveni\AnonymizovanaDatabaze;

/**
 * Export anonymizované databáze pro vývojáře.
 *
 * Generuje se automaticky cronem jednou denně (admin/cron/anonymizace_databaze.php);
 * tato stránka jen zpřístupní poslední vygenerovaný soubor ke stažení.
 *
 * nazev: Anonymizovaná databáze
 * pravo: 113
 * submenu_group: 2
 * submenu_order: 1
 */

const EXPORT_KLIC = 'exportovat_anonymizovanou';

if (!empty($_POST[EXPORT_KLIC])) {
    try {
        AnonymizovanaDatabaze::vytvorZGlobals()->exportuj();
        exit;
    } catch (\RuntimeException $exception) {
        chyba($exception->getMessage());
    }
}

$datumExportu = AnonymizovanaDatabaze::datumPoslednihoExportu();
?>
<h2>Anonymizovaná databáze</h2>

<?php if ($datumExportu): ?>
    <form method="post">
        <button type="submit" value="1" name="<?= EXPORT_KLIC ?>" class="without-safety-unlock">
            Stáhnout anonymizovanou databázi z
            <?= htmlspecialchars(
                $datumExportu->format(DateTimeCz::FORMAT_DB)
                . ' (' . DateTimeCz::createFromInterface($datumExportu)->stari() . ')',
            ) ?>
        </button>
    </form>
<?php else: ?>
    <p>Anonymizovaná databáze zatím nebyla vygenerována. Generuje se automaticky jednou denně.</p>
<?php endif; ?>
