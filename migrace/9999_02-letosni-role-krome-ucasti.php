<?php
/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\Role\Zidle;
use Granam\RemoveDiacritics\RemoveDiacritics;

// jen malý, neškodný hack, aby se tahle migrace pouštěla pořád
$this->setEndless(true);

require_once __DIR__ . '/pomocne/rocnik_z_promenne_mysql.php';

// ZIDLE
$rocnikoveZidle     = Zidle::vsechnyRocnikoveZidle(ROCNIK);
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
    $rocnik        = rocnik_z_promenne_mysql();
    $letosniPrefix = Zidle::prefixRocniku($rocnik);
    foreach ($chybejiciRocnikoveZidle as $idChybejiciRocnikoveZidle => $nazevChybejiciRocnikoveZidle) {
        $result = $this->q(<<<SQL
SELECT rocnik FROM r_zidle_soupis
WHERE jmeno_zidle = '$nazevChybejiciRocnikoveZidle'
SQL
        );
        if ($result) {
            $rocnikZidlePredchozihoRocniku = $result->fetch_column();
            $result->close();
            if ($rocnikZidlePredchozihoRocniku) {
                $prefixProZidliPredchozihoRocniku = Zidle::prefixRocniku($rocnikZidlePredchozihoRocniku);
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
INSERT INTO r_zidle_soupis (id_zidle, kod_zidle, jmeno_zidle, popis_zidle, rocnik, typ_zidle, vyznam)
VALUES ($idChybejiciRocnikoveZidle, '$kodZidle', '$nazevChybejiciRocnikoveZidle', '$nazevChybejiciRocnikoveZidle', $rocnik, '$rocnikova', '$vyznam')
SQL
        );
    }
}
