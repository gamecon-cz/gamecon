<?php

/**
 * Generuje SQL příkazy pro uzavření financí ročníku (před překlopením ROCNIK).
 *
 * nazev: SQL update pro uzavření financí
 * pravo: 113
 * submenu_group: 2
 * submenu_order: 2
 */

const GENEROVAT_KLIC = 'generovat_update_zustatku';

$zobrazVysledek = !empty($_POST[GENEROVAT_KLIC]);

?>
<h2>SQL update pro uzavření financí (překlápění ročníku)</h2>

<?php if (!$zobrazVysledek): ?>
    <form method="post">
        <button type="submit" value="1" name="<?= GENEROVAT_KLIC ?>">
            Vygenerovat SQL příkazy pro uzavření financí ročníku <?= ROCNIK ?>
        </button>
    </form>
<?php else: ?>
    <p>
        SQL příkazy pro uzavření ročníku <?= ROCNIK ?>. Po provedení na DB
        všechny výsledky výpočtu peněz <strong>už nebudou platit</strong>
        až do překlopení <code>ROCNIK</code> na další ročník.
    </p>
    <?php
    $sqlParts = [
        <<<SQL
-- smazat všechny místnosti, aby se mohly nahrát každý rok znovu a nehrozilo, že to někdo začne zadávat k aktivitám, když to ještě není nahrané
DELETE FROM akce_lokace WHERE TRUE;
DELETE FROM lokace WHERE TRUE;
SQL,
    ];
    $vsechnaIds = dbOneArray('SELECT DISTINCT id_uzivatele FROM uzivatele_hodnoty');
    foreach (array_chunk($vsechnaIds, 100) as $chunkIds) {
        foreach (\Uzivatel::zIds($chunkIds) as $uzivatel) {
            $finance    = $uzivatel->finance();
            $sqlParts[] = <<<SQL
UPDATE uzivatele_hodnoty
SET zustatek={$finance->stav()} /* původní zůstatek z předchozích ročníků {$finance->zustatekZPredchozichRocniku()} */,
    poznamka='',
    ubytovan_s='',
    nechce_ubytovani=0,
    infopult_poznamka='',
    pomoc_typ='',
    pomoc_vice='',
    op=''
WHERE id_uzivatele={$uzivatel->id()};
SQL;
        }
        \Uzivatel::smazCache();
    }
    ?>
    <textarea style="width:100%;height:400px"><?= htmlspecialchars(implode("\n", $sqlParts)) ?></textarea>
<?php endif; ?>
