<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle_log
ADD UNIQUE KEY Unique_everything(id_uzivatele, id_zidle, id_zmenil, zmena, kdy)
SQL
);

$soubor = __DIR__ . '/pomocne/historicke-logy-zmen/r_uzivatele_zidle_log_2019.csv.zip';
$zip = new ZipArchive();
$vysledekOtevreni = $zip->open($soubor, ZipArchive::CHECKCONS /* | ZipArchive::RDONLY od PHP 7.4*/);
if ($vysledekOtevreni !== true) {
    throw new RuntimeException("Nelze otevřít ZIP archiv $soubor", $vysledekOtevreni);
}
$resource = $zip->getStream('r_uzivatele_zidle_log_2019.csv');
while (($row = fgetcsv($resource)) !== false) {
    $this->q(<<<SQL
INSERT IGNORE /*nechceme duplicitní záznamy - proto tu žonglujeme s tím unikátním klíčem*/
INTO r_uzivatele_zidle_log(id_uzivatele, id_zidle, id_zmenil, zmena, kdy)
VALUES ('{$row[0]}', '{$row[1]}','{$row[2]}', '{$row[3]}', '{$row[4]}')
SQL
    );

    $coze = $row;
}

$this->q(<<<SQL
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE r_uzivatele_zidle_log
DROP KEY Unique_everything; -- Tohle je divné - předtím MySQL po tomhle indexu ani neštěklo a když na chvíli existuje, ta si najednou stěžuje, že ho potřebuje na cizí klíče, což je blbost
SET FOREIGN_KEY_CHECKS = 1;
SQL
);
