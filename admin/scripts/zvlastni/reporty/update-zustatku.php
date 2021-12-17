<?php

/**
 * Vygenerování SQL dotazů pro update zůstatku gamecorun u letošních uživatelů.
 * Měl by být prováděn synchronně s překlopením proměnné "rok" v nastavení a
 * překlopením webu. Není jak zjistit, jestli byl nebo nebyl proveden, a zároveň
 * je potřeba ho provést jen jednou - je dobré udělat záznam někde (ideálně
 * na více místech), že se tak stalo.
 */

require_once __DIR__ . '/sdilene-hlavicky.php';

$uzivateleQuery = dbQuery('
  SELECT u.*
  FROM r_uzivatele_zidle z
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE z.id_zidle=' . ZIDLE_PRIHLASEN);
$poradi = 0;
$sqlNaPrepocetZustatku = [];
while ($uzivatelData = mysqli_fetch_assoc($uzivateleQuery)) {
    $uzivatel = new Uzivatel($uzivatelData);
    $uzivatel->nactiPrava(); //sql subdotaz, zlo
    $sqlNaPrepocetZustatku[] = <<<SQL
UPDATE uzivatele_hodnoty SET zustatek={$uzivatel->finance()->stav()}, poznamka='' WHERE id_uzivatele={$uzivatel->id()}
SQL;
    $poradi++;
}

?>

<h1>Sql update pro uzavření financí</h1>

<p>Sled příkazů v okně po provedení na databázi aktualizuje zůstatky u jednotlivých uživatelů tak, jak reálně vypadají
    po skončení aktuálního gameconu (tj. ročník <?php echo ROK ?>).</p>

<p>Po provedení dotazu všechny výsledky výpočtu peněz <strong>už nebudou platit</strong> až do okamžiku překlopení webu
    (tj. hlavně konstanty ROK) na další ročník.</p>

<textarea style="width:650px;height:400px">
<?= implode("\n", $sqlNaPrepocetZustatku) ?>
</textarea>
