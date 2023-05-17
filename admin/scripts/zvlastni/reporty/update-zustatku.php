<?php

/**
 * Vygenerování SQL dotazů pro update zůstatku gamecorun u letošních uživatelů.
 * Měl by být prováděn synchronně s překlopením proměnné "rok" v nastavení a
 * překlopením webu. Není jak zjistit, jestli byl nebo nebyl proveden, a zároveň
 * je potřeba ho provést jen jednou - je dobré udělat záznam někde (ideálně
 * na více místech), že se tak stalo.
 */

require __DIR__ . '/sdilene-hlavicky.php';

$sqlParts = [];
foreach (Uzivatel::vsichni() as $uzivatel) {
    $finance    = $uzivatel->finance();
    $sqlParts[] = <<<SQL
UPDATE uzivatele_hodnoty
SET zustatek={$uzivatel->finance()->stav()} /* původní zůstatek z předchozích ročníků {$finance->zustatekZPredchozichRocniku()} */,
    poznamka='',
    ubytovan_s=''
WHERE id_uzivatele={$uzivatel->id()};
SQL;
}

?>

<h1>SQL update pro uzavření financí</h1>

<p>Sled příkazů v okně po provedení na databázi aktualizuje zůstatky u jednotlivých uživatelů tak, jak reálně vypadají
    po skončení aktuálního gameconu (tj. ročník <?php echo ROCNIK ?>).</p>

<p>Po provedení dotazu všechny výsledky výpočtu peněz <strong>už nebudou platit</strong> až do okamžiku překlopení webu
    (tj. hlavně konstanty ROCNIK) na další ročník.</p>

<textarea style="width:650px;height:400px">
<?= implode("\n", $sqlParts) ?>
</textarea>
