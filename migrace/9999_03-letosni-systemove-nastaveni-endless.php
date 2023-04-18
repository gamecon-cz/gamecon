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
    $lonskeSystemoveNastaveni  = SystemoveNastaveni::vytvorZGlobals(rocnik: $lonskyRok);
    $lonskeZaznamy             = $lonskeSystemoveNastaveni->dejVsechnyZaznamyNastaveni();
    $letosniSystemoveNastaveni = SystemoveNastaveni::vytvorZGlobals(rocnik: $rocnik);
    foreach ($chybejiciKliceNastaveni as $klicWrapped) {
        $klic = reset($klicWrapped);
        if (empty($lonskeZaznamy[$klic])) {
            throw new LogicException("Chybí loňský záznam pro nastavení '$klic'");
        }
        $lonskyZaznam = $lonskeZaznamy[$klic];
        unset($lonskyZaznam[NastaveniSql::ID_NASTAVENI]); // záznam budeme ukládat jako nový, ID původního se nám nehodí
        $letosniHodnota                               = $letosniSystemoveNastaveni->spocitejHodnotu($klic);
        $lonskyZaznam[NastaveniSql::HODNOTA]          = $letosniHodnota;
        $lonskyZaznam[NastaveniSql::POUZE_PRO_CTENI]  = 1;
        $lonskyZaznam[NastaveniSql::ROCNIK_NASTAVENI] = $rocnik;

        /**
         * odstraníme přidané klíče které nepochází z původní tabulky,
         * například @see \Gamecon\SystemoveNastaveni\SystemoveNastaveniStruktura::ID_UZIVATELE
         * nebo @see SystemoveNastaveni::pridejVychoziHodnoty
         */
        $letosniZaznam = array_intersect_key(
            $lonskyZaznam,
            array_fill_keys(NastaveniSql::sloupce(), ''),
        );

        $setSql = implode(
            ',',
            array_map(
                static function ($klic, $hodnota) {
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
    }
}
