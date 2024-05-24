<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\XTemplate\XTemplate;

/**
 * Nástroje potřebné rpo infopult před začátkem GC
 *
 * nazev: Info věci před GC
 * pravo: 111
 * submenu_group: 1
 */

require __DIR__ . '/_kfcMrizkovyProdej.php';

$reportUbytovaniBasePath = basename(__DIR__ . '/../zvlastni/reporty/finance-report-ubytovani.php', '.php');
echo <<<HTML
<div class="aBox" style="width:100%; overflow: auto;">
    <h3>Reporty</h3>
    <ul>
        <li><a href="reporty/{$reportUbytovaniBasePath}?format=xlsx">Report ubytování</a></li>
    </ul>
</div>
HTML;

require __DIR__ . '/../_ubytovani-a-dalsi-obcasne-infopultakoviny-import-balicku.php';
