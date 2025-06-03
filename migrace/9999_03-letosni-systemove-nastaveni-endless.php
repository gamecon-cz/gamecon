<?php
/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;
use Gamecon\SystemoveNastaveni\SqlStruktura\SystemoveNastaveniSqlStruktura as NastaveniSql;

require_once __DIR__ . '/pomocne/rocnik_z_promenne_mysql.php';

$this->q(<<<SQL
CREATE TEMPORARY TABLE systemove_nastaveni_letosni_chtene_tmp (
    klic VARCHAR(128) NOT NULL PRIMARY KEY
)
SQL,
);

$letosniChteneKlice    = SystemoveNastaveniKlice::jednorocniKlice();
$letosniChteneKliceSql = implode(
    ',',
    array_map(
        static fn(string $klic) => '(' . dbQv($klic) . ')',
        $letosniChteneKlice,
    ),
);

$this->q(<<<SQL
INSERT INTO systemove_nastaveni_letosni_chtene_tmp(klic)
VALUES {$letosniChteneKliceSql}
SQL,
);

$rocnik                  = rocnik_z_promenne_mysql();
$chybejiciKliceNastaveni = $this->q(<<<SQL
SELECT tmp.klic
FROM systemove_nastaveni_letosni_chtene_tmp AS tmp
LEFT JOIN systemove_nastaveni ON tmp.klic = systemove_nastaveni.klic COLLATE utf8_bin
    AND systemove_nastaveni.rocnik_nastaveni = $rocnik
WHERE systemove_nastaveni.id_nastaveni IS NULL
SQL,
)->fetch_all();

$this->q(<<<SQL
DROP TEMPORARY TABLE systemove_nastaveni_letosni_chtene_tmp
SQL,
);

if ($chybejiciKliceNastaveni) {
    $lonskyRok                 = $rocnik - 1;
    $lonskeSystemoveNastaveni  = SystemoveNastaveni::zGlobals(rocnik: $lonskyRok);
    $lonskeZaznamy             = $lonskeSystemoveNastaveni->dejVsechnyZaznamyNastaveni();
    $letosniSystemoveNastaveni = SystemoveNastaveni::zGlobals(rocnik: $rocnik);
    $systemUzivatelId = Uzivatel::SYSTEM;
    foreach ($chybejiciKliceNastaveni as $klicWrapped) {
        $klic = reset($klicWrapped);
        if (empty($lonskeZaznamy[$klic])) {
            throw new LogicException("Chybí loňský záznam pro nastavení '$klic' (zřejmě se měnil ROCNIK, exportuj novou testovací databázi)");
        }
        $lonskyZaznam = $lonskeZaznamy[$klic];
        /**
         * odstraníme přidané klíče které nepochází z původní tabulky,
         * například @see \Gamecon\SystemoveNastaveni\SystemoveNastaveniStruktura::ID_UZIVATELE
         * nebo @see SystemoveNastaveni::pridejVychoziHodnoty
         */
        $letosniZaznam = array_intersect_key(
            $lonskyZaznam,
            array_fill_keys(NastaveniSql::sloupce(), ''),
        );
        unset($letosniZaznam[NastaveniSql::ID_NASTAVENI]); // záznam budeme ukládat jako nový, ID původního se nám nehodí
        $letosniHodnota                                = $letosniSystemoveNastaveni->spocitejHodnotu($klic);
        $letosniZaznam[NastaveniSql::HODNOTA]          = $letosniHodnota;
        $letosniZaznam[NastaveniSql::POUZE_PRO_CTENI]  = 1;
        $letosniZaznam[NastaveniSql::ROCNIK_NASTAVENI] = $rocnik;

        $setSql = implode(
            ',',
            array_map(
                function ($klic, $hodnota) {
                    $hodnota = $this->connection->real_escape_string($hodnota);
                    return "`$klic` = '$hodnota'";
                },
                array_keys($letosniZaznam),
                $letosniZaznam,
            ),
        );
        $this->q(<<<SQL
INSERT INTO systemove_nastaveni
SET {$setSql}
SQL,
        );
        $letosniHodnotaEscaped = $this->connection->real_escape_string($letosniHodnota);
        $this->q(<<<SQL
INSERT INTO systemove_nastaveni_log 
SET id_nastaveni_log = NULL, id_uzivatele = {$systemUzivatelId}, id_nastaveni = LAST_INSERT_ID(), hodnota = '{$letosniHodnotaEscaped}', vlastni = null, kdy = NOW()
SQL,
        );
    }
}
