<?php

$reportUbytovaniBasePath = basename(__DIR__ . '/../zvlastni/reporty/finance-report-ubytovani.php', '.php');
echo <<<HTML
<div class="clearfix"></div>

<div class="aBox" style="width:100%; overflow: auto;">
    <h3>Reporty</h3>
    <ul>
        <li><a href="reporty/{$reportUbytovaniBasePath}?format=xlsx">Report ubytování</a></li>
    </ul>
</div>
HTML;
