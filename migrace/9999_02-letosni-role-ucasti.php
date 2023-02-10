<?php
/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\Role\Zidle;
use Granam\RemoveDiacritics\RemoveDiacritics;

require_once __DIR__ . '/pomocne/rocnik_z_promenne_mysql.php';

// jen malý, neškodný hack, aby se tahle migrace pouštěla pořád
$this->setEndless(true);

// ZIDLE
$zidleUcasti      = Zidle::vsechnyZidleUcastiProRocnik(ROCNIK);
$idZidliUcasti    = array_keys($zidleUcasti);
$idZidliUcastiSql = implode(',', $idZidliUcasti);
$resultZidli      = $this->q(<<<SQL
SELECT id_zidle
FROM r_zidle_soupis
WHERE id_zidle IN ($idZidliUcastiSql)
SQL
);

$chybejiciZidleUcasti = $zidleUcasti;
if ($resultZidli) {
    foreach ($resultZidli->fetch_all() as $idExistujiciZidleArray) {
        $idExistujiciZidle = (int)(reset($idExistujiciZidleArray));
        unset($chybejiciZidleUcasti[$idExistujiciZidle]);
    }
}

if ($chybejiciZidleUcasti) {
    $rocnik = rocnik_z_promenne_mysql();
    $ucast  = Zidle::TYP_UCAST;
    foreach ($chybejiciZidleUcasti as $idChybejiciZidleUcasti => $nazevChybejiciZidleUcasti) {
        $kodZidle = RemoveDiacritics::toConstantLikeName($nazevChybejiciZidleUcasti);
        $vyznam   = Zidle::vyznamPodleKodu($kodZidle);
        $this->q(<<<SQL
INSERT INTO r_zidle_soupis (id_zidle, kod_zidle, jmeno_zidle, popis_zidle, rocnik, typ_zidle, vyznam)
VALUES ($idChybejiciZidleUcasti, '$kodZidle', '$nazevChybejiciZidleUcasti', '$nazevChybejiciZidleUcasti', $rocnik, '$ucast', '$vyznam')
SQL
        );
    }
}
