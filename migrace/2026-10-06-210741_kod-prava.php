<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// The code of a right is the name of its `Gamecon\Pravo` constant. The list is spelled out and
// not read from `Pravo`, so that this migration gives the same result whatever happens to the
// constants later.
$kodyPodleId = [
       4 => 'PORADANI_AKTIVIT',
       5 => 'PREKRYVANI_AKTIVIT',
       8 => 'ZMENA_HISTORIE_AKTIVIT',
       9 => 'PRIHLASOVANI_NA_DOSUD_NEOTEVRENE',
     100 => 'ADMINISTRACE_INFOPULT',
     101 => 'ADMINISTRACE_UBYTOVANI',
     102 => 'ADMINISTRACE_AKCE',
     103 => 'ADMINISTRACE_PREZENCE',
     104 => 'ADMINISTRACE_REPORTY',
     105 => 'ADMINISTRACE_WEB',
     106 => 'ADMINISTRACE_PRAVA',
     107 => 'ADMINISTRACE_STATISTIKY',
     108 => 'ADMINISTRACE_FINANCE',
     109 => 'ADMINISTRACE_MOJE_AKTIVITY',
     110 => 'ADMINISTRACE_NASTAVENI',
     111 => 'ADMINISTRACE_PENIZE',
     112 => 'ADMINISTRACE_WEB_LOGA',
     113 => 'ADMINISTRACE_DEV',
     114 => 'PREPNUTI_NA_UZIVATELE',
    1002 => 'PLACKA_ZDARMA',
    1003 => 'KOSTKA_ZDARMA',
    1004 => 'JIDLO_SE_SLEVOU',
    1005 => 'JIDLO_ZDARMA',
    1008 => 'UBYTOVANI_ZDARMA',
    1012 => 'MODRE_TRICKO_ZDARMA',
    1015 => 'UBYTOVANI_STREDECNI_NOC_ZDARMA',
    1016 => 'NERUSIT_AUTOMATICKY_OBJEDNAVKY',
    1018 => 'UBYTOVANI_NEDELNI_NOC_ZDARMA',
    1019 => 'CASTECNA_SLEVA_NA_AKTIVITY',
    1020 => 'DVE_JAKAKOLI_TRICKA_ZDARMA',
    1021 => 'MUZE_OBJEDNAVAT_MODRA_TRICKA',
    1022 => 'MUZE_OBJEDNAVAT_CERVENA_TRICKA',
    1023 => 'AKTIVITY_ZDARMA',
    1024 => 'ZOBRAZOVAT_VE_STATISTIKACH_V_TABULCE_UCASTI',
    1025 => 'VYPISOVAT_V_REPORTU_NEUBYTOVANYCH',
    1026 => 'TITUL_ORGANIZATOR',
    1027 => 'UNIKATNI_ROLE',
    1028 => 'BEZ_BONUSU_ZA_VEDENI_AKTIVIT',
    1029 => 'UBYTOVANI_CTVRTECNI_NOC_ZDARMA',
    1030 => 'UBYTOVANI_PATECNI_NOC_ZDARMA',
    1031 => 'UBYTOVANI_SOBOTNI_NOC_ZDARMA',
    1032 => 'HROMADNA_AKTIVACE_AKTIVIT',
    1033 => 'ZMENA_PRAV',
    1034 => 'PROVADI_KOREKCE',
    1035 => 'JAKEKOLIV_TRICKO_ZDARMA',
    1036 => 'UBYTOVANI_NEDELNI_NOC_NABIZET',
    1037 => 'UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC',
    1038 => 'MUZE_RUSIT_NAKUPY',
    1039 => 'JEDNA_AKTIVITA_ZDARMA',
    1040 => 'MUZE_PRETIZIT_UBYTOVANI',
    1041 => 'MUZE_ZAMYKAT_TYMY',
    1042 => 'NEMUSI_POTVRZOVAT_NA_INFOPULTU',
];

$sloupecExistuje = (int) $this->q(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'r_prava_soupis' AND COLUMN_NAME = 'kod_prava'",
)->fetchColumn();
if ($sloupecExistuje === 0) {
    $this->q('ALTER TABLE r_prava_soupis ADD kod_prava VARCHAR(64) NULL AFTER id_prava');
}

foreach ($kodyPodleId as $id => $kod) {
    $this->q("UPDATE r_prava_soupis SET kod_prava = '{$kod}' WHERE id_prava = {$id}");
}

// Participation rights of the years before roles took over (GC2009 .. GC2023): -<yy>01 is
// "přihlášen", -<yy>02 is "přítomen", the same convention the roles use.
$this->q(
    "UPDATE r_prava_soupis
     SET kod_prava = CONCAT(
         'GC', 2000 + FLOOR(ABS(id_prava) / 100),
         IF(MOD(ABS(id_prava), 100) = 1, '_PRIHLASEN', '_PRITOMEN')
     )
     WHERE id_prava < 0 AND MOD(ABS(id_prava), 100) IN (1, 2) AND kod_prava IS NULL",
);

// Deploy migrations do not run in strict mode, so a NOT NULL over a leftover NULL would not fail
// but turn it into an empty string. Better to stop here and say which right was missed.
$bezKodu = $this->q('SELECT GROUP_CONCAT(id_prava) FROM r_prava_soupis WHERE kod_prava IS NULL')->fetchColumn();
if ($bezKodu) {
    throw new \RuntimeException("Rights without a code, add them to the list in this migration: {$bezKodu}");
}

$this->q(
    "ALTER TABLE r_prava_soupis
     MODIFY kod_prava VARCHAR(64) NOT NULL,
     ADD CONSTRAINT UNIQ_kod_prava UNIQUE (kod_prava),
     ADD CONSTRAINT CHK_kod_prava_neprazdny CHECK (kod_prava <> '')",
);
