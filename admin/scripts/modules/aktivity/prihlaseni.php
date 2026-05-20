<?php

/**
 * Stránka pro přehled všech přihlášených na aktivity
 *
 * nazev: Seznam přihlášených
 * pravo: 102
 * submenu_group: 3
 * submenu_order: 1
 */

use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;

$xtpl2 = new XTemplate(__DIR__ . '/prihlaseni.xtpl');

$naDateTimeCz = static function (?string $datum): ?DateTimeCz {
    if (!$datum || str_starts_with($datum, '0000-00-00')) {
        return null;
    }

    try {
        return new DateTimeCz($datum);
    } catch (\Throwable) {
        return null;
    }
};

$formatCasAktivity = static function (array $prihlaseni) use ($naDateTimeCz): string {
    $zacatek = $naDateTimeCz($prihlaseni['zacatek'] ?? null);
    if ($zacatek === null) {
        return '';
    }

    $konec = $naDateTimeCz($prihlaseni['konec'] ?? null);

    return $zacatek->format('l G:i') . ($konec ? '–' . $konec->format('G:i') : '');
};

$formatVek = static function (?string $datumNarozeni, ?DateTimeCz $datumAktivity) use ($naDateTimeCz): string {
    if ($datumAktivity === null) {
        return 'Nevyplnil';
    }

    $narozeni = $naDateTimeCz($datumNarozeni);
    if ($narozeni === null) {
        return 'Nevyplnil';
    }

    $vek = $narozeni->diff($datumAktivity)->y;

    return $vek >= 18
        ? '18+'
        : (string)$vek;
};

$formatDatumPrihlaseni = static function (?string $datumPrihlaseni) use ($naDateTimeCz): string {
    $prihlasen = $naDateTimeCz($datumPrihlaseni);

    return $prihlasen
        ? $prihlasen->format('j.n. H:i')
        : '';
};

$o = dbQuery('SELECT id_typu, typ_1pmn FROM akce_typy ORDER BY poradi, typ_1pmn');
while ($r = mysqli_fetch_assoc($o)) {
    $xtpl2->assign($r);
    $xtpl2->parse('prihlaseni.vyber');
}

if (!get('typ')) {
    $xtpl2->parse('prihlaseni');
    $xtpl2->out('prihlaseni');
    return;
}

$odpoved = dbQuery(<<<SQL
SELECT  akce_seznam.nazev_akce AS nazevAktivity, akce_seznam.id_akce AS id,
        (akce_seznam.kapacita + akce_seznam.kapacita_m + akce_seznam.kapacita_f) AS kapacita,
    akce_seznam.zacatek, akce_seznam.konec,
        ucastnik.login_uzivatele AS nick, ucastnik.jmeno_uzivatele AS jmeno, ucastnik.id_uzivatele,
        ucastnik.prijmeni_uzivatele AS prijmeni, ucastnik.email1_uzivatele AS mail, ucastnik.telefon_uzivatele AS telefon,
        ucastnik.datum_narozeni,
        (SELECT GROUP_CONCAT(organizator.login_uzivatele SEPARATOR ', ')
            FROM akce_organizatori
            JOIN uzivatele_hodnoty AS organizator ON organizator.id_uzivatele = akce_organizatori.id_uzivatele
            WHERE akce_organizatori.id_akce = akce_seznam.id_akce
        ) AS orgove,
        (SELECT GROUP_CONCAT(
                lokace.nazev,
                IF (TRIM(lokace.dvere) != '', CONCAT(', ', lokace.dvere), '')
                SEPARATOR '; '
            )
            FROM akce_lokace
            JOIN lokace ON akce_lokace.id_lokace = lokace.id_lokace
            WHERE akce_lokace.id_akce = akce_seznam.id_akce
        ) AS mistnost,
        MAX(log.kdy) AS datum_prihlaseni
FROM akce_seznam
LEFT JOIN akce_prihlaseni ON akce_seznam.id_akce = akce_prihlaseni.id_akce
LEFT JOIN uzivatele_hodnoty AS ucastnik ON akce_prihlaseni.id_uzivatele = ucastnik.id_uzivatele
LEFT JOIN akce_prihlaseni_log AS log ON log.id_akce = akce_seznam.id_akce AND log.id_uzivatele = ucastnik.id_uzivatele
WHERE akce_seznam.rok = $0
    AND akce_seznam.zacatek
    AND akce_seznam.typ = $1
GROUP BY ucastnik.id_uzivatele, akce_seznam.id_akce, akce_seznam.nazev_akce, akce_seznam.zacatek
ORDER BY akce_seznam.zacatek, akce_seznam.nazev_akce, akce_seznam.id_akce, ucastnik.id_uzivatele
SQL,
    [0 => ROCNIK, 1 => get('typ')]
);

$totoPrihlaseni  = mysqli_fetch_assoc($odpoved) ?: [];
$dalsiPrihlaseni = mysqli_fetch_assoc($odpoved) ?: [];
$obsazenost      = 0;
$odd             = 0;
$maily           = [];
while ($totoPrihlaseni) {
    $xtpl2->assign($totoPrihlaseni);
    $xtpl2->assign('datum_prihlaseni', '');
    if ($totoPrihlaseni['id_uzivatele']) {
        $datumAktivity = $naDateTimeCz($totoPrihlaseni['zacatek'] ?? null);
        $xtpl2->assign('vek', $formatVek($totoPrihlaseni['datum_narozeni'] ?? null, $datumAktivity));
        $xtpl2->assign('datum_prihlaseni', $formatDatumPrihlaseni($totoPrihlaseni['datum_prihlaseni'] ?? null));
        $xtpl2->assign('odd', $odd ? $odd = '' : $odd = 'odd');
        $xtpl2->parse('prihlaseni.aktivita.lide.clovek');
        $maily[] = $totoPrihlaseni['mail'];
        $obsazenost++;
    }
    if (($totoPrihlaseni['id'] ?? null) != ($dalsiPrihlaseni['id'] ?? null)) {
        $xtpl2->assign('maily', implode('; ', $maily));
        $xtpl2->assign('cas', $formatCasAktivity($totoPrihlaseni));
        $xtpl2->assign('orgove', $totoPrihlaseni['orgove']);
        $xtpl2->assign('obsazenost', $obsazenost .
            ($totoPrihlaseni['kapacita'] ? '/' . $totoPrihlaseni['kapacita'] : ''));
        $xtpl2->assign('druzina', '');
        if ($obsazenost) {
            $xtpl2->parse('prihlaseni.aktivita.lide');
        }
        $xtpl2->parse('prihlaseni.aktivita');
        $obsazenost = 0;
        $odd        = 0;
        $maily      = [];
    }
    $totoPrihlaseni  = $dalsiPrihlaseni;
    $dalsiPrihlaseni = mysqli_fetch_assoc($odpoved);
}

$xtpl2->parse('prihlaseni');
$xtpl2->out('prihlaseni');
