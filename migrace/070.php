<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET nazev = '<span id="bfgr" class="hinted">BFGR (celkový report) {ROK}<span class="hint"><em>Big f**king Gandalf report</em> určený pro Gandalfovu Excelentní magii</span></span>'
WHERE skript = 'celkovy-report'
SQL
);
