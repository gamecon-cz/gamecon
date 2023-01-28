<?php
/** @var \Godric\DbMigrations\Migration $this */

// jen malý, neškodný hack, aby se tahle migrace pouštěla pořád
$this->setEndless(true);

// ZIDLE
$zidleUcasti      = \Gamecon\Zidle::vymysliZidleUcastiProRocnik(ROK);
$idZidliUcasti    = array_keys($zidleUcasti);
$idZidliUcastiSql = implode(',', $idZidliUcasti);
$resultZidli      = $this->q(<<<SQL
    SELECT id_zidle
    FROM r_zidle_soupis
    WHERE id_zidle IN ($idZidliUcastiSql)
    SQL
);

$chybejiciPrava = $zidleUcasti;
if ($resultZidli) {
    foreach ($resultZidli->fetch_all() as $idExistujicihoPravaArray) {
        $idExistujicihoPrava = (int)(reset($idExistujicihoPravaArray));
        unset($chybejiciPrava[$idExistujicihoPrava]);
    }
}

foreach ($chybejiciPrava as $idChybejicihoPrava => $nazevChybejicihoPrava) {
    $this->q(<<<SQL
INSERT INTO r_zidle_soupis (id_zidle, jmeno_zidle, popis_zidle)
VALUES ($idChybejicihoPrava, '$nazevChybejicihoPrava', '$nazevChybejicihoPrava')
SQL
    );
}

// PRAVA
$pravaUcasti     = \Gamecon\Pravo::vymysliPravaUcastiProRocnik(ROK);
$idPravUcasti    = array_keys($pravaUcasti);
$idPravUcastiSql = implode(',', $idPravUcasti);
$resultPrava     = $this->q(<<<SQL
SELECT id_prava
FROM r_prava_soupis
WHERE id_prava IN ($idPravUcastiSql)
SQL
);

$chybejiciPrava = $pravaUcasti;
if ($resultPrava) {
    foreach ($resultPrava->fetch_all() as $idExistujicihoPravaArray) {
        $idExistujicihoPrava = (int)reset($idExistujicihoPravaArray);
        unset($chybejiciPrava[$idExistujicihoPrava]);
    }
}

foreach ($chybejiciPrava as $idChybejicihoPrava => $nazevChybejicihoPrava) {
    $this->q(<<<SQL
INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES ($idChybejicihoPrava, '$nazevChybejicihoPrava', '$nazevChybejicihoPrava')
SQL
    );
    $idZidle = \Gamecon\Pravo::dejIdZidlePodlePravaUcasti($idChybejicihoPrava);
    $this->q(<<<SQL
INSERT INTO r_prava_zidle (id_zidle, id_prava)
VALUES ($idZidle, $idChybejicihoPrava)
SQL
    );
}
