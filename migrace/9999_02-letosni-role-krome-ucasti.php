<?php
/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\Role\Zidle;
use Granam\RemoveDiacritics\RemoveDiacritics;

// jen malý, neškodný hack, aby se tahle migrace pouštěla pořád
$this->setEndless(true);

require_once __DIR__ . '/pomocne/rocnik_z_promenne_mysql.php';

// ZIDLE
$rocnikoveZidle     = Zidle::vsechnyRocnikoveZidle(ROK);
$idRocnikovychZidli = array_keys($rocnikoveZidle);
$idZidliUcastiSql   = implode(',', $idRocnikovychZidli);
$resultZidli        = $this->q(<<<SQL
    SELECT id_zidle
    FROM r_zidle_soupis
    WHERE id_zidle IN ($idZidliUcastiSql)
    SQL
);

$chybejiciRocnikoveZidle = $rocnikoveZidle;
if ($resultZidli) {
    while ($idExistujiciZidle = $resultZidli->fetch_column()) {
        unset($chybejiciRocnikoveZidle[(int)$idExistujiciZidle]);
    }
}

if ($chybejiciRocnikoveZidle) {
    $rok           = rocnik_z_promenne_mysql();
    $letosniPrefix = Zidle::prefixRocniku($rok);
    foreach ($chybejiciRocnikoveZidle as $idChybejiciRocnikoveZidle => $nazevChybejiciRocnikoveZidle) {
        $result = $this->q(<<<SQL
SELECT rok FROM r_zidle_soupis
WHERE jmeno_zidle = '$nazevChybejiciRocnikoveZidle'
SQL
        );
        if ($result) {
            $rokZidlePredchozihoRocniku = $result->fetch_column();
            $result->close();
            if ($rokZidlePredchozihoRocniku) {
                $prefixProZidliPredchozihoRocniku = Zidle::prefixRocniku($rokZidlePredchozihoRocniku);
                $this->q(<<<SQL
UPDATE r_zidle_soupis
SET jmeno_zidle = CONCAT('$prefixProZidliPredchozihoRocniku', ' ', jmeno_zidle)
WHERE jmeno_zidle = '$nazevChybejiciRocnikoveZidle'
SQL
                );
            }
        }

        // 'Herman' v "letos" roce 2023 = GC2023_HERMAN
        $kodZidle  = RemoveDiacritics::toConstantLikeName($letosniPrefix . ' ' . $nazevChybejiciRocnikoveZidle);
        $vyznam    = Zidle::vyznamPodleKodu($kodZidle);
        $rocnikova = Zidle::TYP_ROCNIKOVA;
        $this->q(<<<SQL
INSERT INTO r_zidle_soupis (id_zidle, kod_zidle, jmeno_zidle, popis_zidle, rok, typ, vyznam)
VALUES ($idChybejiciRocnikoveZidle, '$kodZidle', '$nazevChybejiciRocnikoveZidle', '$nazevChybejiciRocnikoveZidle', $rok, '$rocnikova', '$vyznam')
SQL
        );
    }
}
