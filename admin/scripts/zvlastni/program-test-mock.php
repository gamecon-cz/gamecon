<?php

declare(strict_types=1);

// Sdílená mock data pro program-test.php a mock API endpointy.

$_mockTz   = new DateTimeZone('Europe/Prague');
$_mockDen1 = (new DateTimeImmutable(PROGRAM_OD, $_mockTz))->setTime(0, 0);
$_mockDen2 = $_mockDen1->modify('+1 day');
$_mockDen3 = $_mockDen1->modify('+2 day');

$_mockTs  = static fn(DateTimeImmutable $d, int $h): int => $d->setTime($h, 0)->getTimestamp() * 1000;
$_mockCas = static fn(int $od, int $do): string => "{$od}:00&ndash;{$do}:00";

$_mockLokace = [
    'l08' => ['id' => 8, 'poradi' => 8, 'nazev' => 'Místnost 08'],
    'l07' => ['id' => 7, 'poradi' => 7, 'nazev' => 'Místnost 07'],
    'l01' => ['id' => 1, 'poradi' => 1, 'nazev' => 'Místnost 01'],
    'l03' => ['id' => 3, 'poradi' => 3, 'nazev' => 'Místnost 03'],
    'l05' => ['id' => 5, 'poradi' => 5, 'nazev' => 'Místnost 05'],
    'l06' => ['id' => 6, 'poradi' => 6, 'nazev' => 'Místnost 06'],
    'l04' => ['id' => 4, 'poradi' => 4, 'nazev' => 'Místnost 04'],
    'l02' => ['id' => 2, 'poradi' => 2, 'nazev' => 'Místnost 02'],
];

// -------------------------------------------------------------------
// Statické soubory: aktivity (interní 1098, 1099 se nevkládají)
// Název = číslo + stav pro rychlou orientaci
// -------------------------------------------------------------------

$programTestMockAktivity = [
    [
        'id'             => 1001,
        'nazev'          => '01 – volná',
        'kratkyPopis'    => 'prihlasovatelna, stavPrihlaseni=null',
        'popisId'        => 1001,
        'obrazek'        => '',
        'vypraveci'      => ['Jan Novák'],
        'stitkyId'       => [1],
        'cenaZaklad'     => 50,
        'casText'        => $_mockCas(10, 12),
        'cas'            => ['od' => $_mockTs($_mockDen1, 10), 'do' => $_mockTs($_mockDen1, 12)],
        'linie'          => 'RPG',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => false,
        'probehnuta'     => false,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> true,
        'tymova'         => false,
    ],
    [
        'id'             => 1002,
        'nazev'          => '02 – přihlášen, plno',
        'kratkyPopis'    => 'stavPrihlaseni=prihlasen, kapacita plná',
        'popisId'        => 1002,
        'obrazek'        => '',
        'vypraveci'      => ['Petra Kovářová'],
        'stitkyId'       => [2],
        'cenaZaklad'     => 0,
        'casText'        => $_mockCas(14, 17),
        'cas'            => ['od' => $_mockTs($_mockDen1, 14), 'do' => $_mockTs($_mockDen1, 17)],
        'linie'          => 'Deskovky',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => false,
        'probehnuta'     => false,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> true,
        'tymova'         => false,
    ],
    [
        'id'             => 1003,
        'nazev'          => '03 – proběhlá, dorazil',
        'kratkyPopis'    => 'probehnuta=true, stavPrihlaseni=prihlasenADorazil',
        'popisId'        => 1003,
        'obrazek'        => '',
        'vypraveci'      => ['Organizátor GameCon'],
        'stitkyId'       => [],
        'cenaZaklad'     => 0,
        'casText'        => $_mockCas(8, 10),
        'cas'            => ['od' => $_mockTs($_mockDen1, 8), 'do' => $_mockTs($_mockDen1, 10)],
        'linie'          => 'Přednáška',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => false,
        'probehnuta'     => true,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> false,
        'tymova'         => false,
    ],
    [
        'id'             => 1004,
        'nazev'          => '04 – příští vlna, sledující',
        'kratkyPopis'    => 'vdalsiVlne=true, stavPrihlaseni=sledujici',
        'popisId'        => 1004,
        'obrazek'        => '',
        'vypraveci'      => ['Marie Vesmírná'],
        'stitkyId'       => [1],
        'cenaZaklad'     => 80,
        'casText'        => $_mockCas(10, 13),
        'cas'            => ['od' => $_mockTs($_mockDen2, 10), 'do' => $_mockTs($_mockDen2, 13)],
        'linie'          => 'RPG',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => true,
        'probehnuta'     => false,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> true,
        'tymova'         => false,
    ],
    [
        'id'             => 1005,
        'nazev'          => '05 – týmová',
        'kratkyPopis'    => 'tymova=true, stavPrihlaseni=null',
        'popisId'        => 1005,
        'obrazek'        => '',
        'vypraveci'      => ['Lukáš Meč', 'Jakub Štít'],
        'stitkyId'       => [1, 3],
        'cenaZaklad'     => 100,
        'casText'        => $_mockCas(14, 18),
        'cas'            => ['od' => $_mockTs($_mockDen2, 14), 'do' => $_mockTs($_mockDen2, 18)],
        'linie'          => 'RPG',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => false,
        'probehnuta'     => false,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> true,
        'tymova'         => true,
    ],
    [
        'id'             => 1006,
        'nazev'          => '06 – brigáda, přihlášen',
        'kratkyPopis'    => 'jeBrigadnicka=true, stavPrihlaseni=prihlasen',
        'popisId'        => 1006,
        'obrazek'        => '',
        'vypraveci'      => ['Hlavní Org'],
        'stitkyId'       => [4],
        'cenaZaklad'     => 0,
        'casText'        => $_mockCas(9, 12),
        'cas'            => ['od' => $_mockTs($_mockDen3, 9), 'do' => $_mockTs($_mockDen3, 12)],
        'linie'          => 'Brigáda',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => false,
        'probehnuta'     => false,
        'jeBrigadnicka'  => true,
        'prihlasovatelna'=> true,
        'tymova'         => false,
    ],
    [
        'id'             => 1007,
        'nazev'          => '07 – nepřihlašovatelná',
        'kratkyPopis'    => 'prihlasovatelna=false, stavPrihlaseni=null',
        'popisId'        => 1007,
        'obrazek'        => '',
        'vypraveci'      => [],
        'stitkyId'       => [],
        'cenaZaklad'     => 0,
        'casText'        => $_mockCas(11, 13),
        'cas'            => ['od' => $_mockTs($_mockDen1, 11), 'do' => $_mockTs($_mockDen1, 13)],
        'linie'          => 'Přednáška',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => false,
        'probehnuta'     => false,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> false,
        'tymova'         => false,
    ],
    [
        'id'             => 1008,
        'nazev'          => '08 – dorazil jako náhradník',
        'kratkyPopis'    => 'stavPrihlaseni=dorazilJakoNahradnik',
        'popisId'        => 1008,
        'obrazek'        => '',
        'vypraveci'      => ['Noční Průvodce'],
        'stitkyId'       => [1],
        'cenaZaklad'     => 60,
        'casText'        => $_mockCas(22, 1),
        'cas'            => ['od' => $_mockTs($_mockDen1, 22), 'do' => $_mockTs($_mockDen2, 1)],
        'linie'          => 'RPG',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => false,
        'probehnuta'     => false,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> true,
        'tymova'         => false,
    ],
    [
        'id'             => 1009,
        'nazev'          => '09 – vedu, přihlášen',
        'kratkyPopis'    => 'vedu=true, stavPrihlaseni=prihlasen, cenaZaklad=0',
        'popisId'        => 1009,
        'obrazek'        => '',
        'vypraveci'      => ['Mistr Štětec'],
        'stitkyId'       => [3],
        'cenaZaklad'     => 0,
        'casText'        => $_mockCas(10, 12),
        'cas'            => ['od' => $_mockTs($_mockDen3, 10), 'do' => $_mockTs($_mockDen3, 12)],
        'linie'          => 'Workshop',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => false,
        'probehnuta'     => false,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> true,
        'tymova'         => false,
    ],
    [
        'id'             => 1010,
        'nazev'          => '10 – vBudoucnu',
        'kratkyPopis'    => 'vBudoucnu=true, prihlasovatelna=false, stavPrihlaseni=null',
        'popisId'        => 1010,
        'obrazek'        => '',
        'vypraveci'      => ['Budoucí Vypravěč'],
        'stitkyId'       => [1],
        'cenaZaklad'     => 50,
        'casText'        => $_mockCas(8, 10),
        'cas'            => ['od' => $_mockTs($_mockDen2, 8), 'do' => $_mockTs($_mockDen2, 10)],
        'linie'          => 'RPG',
        'vBudoucnu'      => true,
        'vdalsiVlne'     => false,
        'probehnuta'     => false,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> false,
        'tymova'         => false,
    ],
    [
        'id'             => 1011,
        'nazev'          => '11 – pozdě zrušil',
        'kratkyPopis'    => 'stavPrihlaseni=pozdeZrusil',
        'popisId'        => 1011,
        'obrazek'        => '',
        'vypraveci'      => ['Rychlý Vypravěč'],
        'stitkyId'       => [2],
        'cenaZaklad'     => 30,
        'casText'        => $_mockCas(11, 12),
        'cas'            => ['od' => $_mockTs($_mockDen2, 11), 'do' => $_mockTs($_mockDen2, 12)],
        'linie'          => 'Deskovky',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => false,
        'probehnuta'     => false,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> true,
        'tymova'         => false,
    ],
    [
        'id'             => 1012,
        'nazev'          => '12 – se slevou, přihlášen',
        'kratkyPopis'    => 'stavPrihlaseni=prihlasen, slevaNasobic=0.5',
        'popisId'        => 1012,
        'obrazek'        => '',
        'vypraveci'      => ['Kreativní Vedoucí'],
        'stitkyId'       => [3],
        'cenaZaklad'     => 80,
        'casText'        => $_mockCas(15, 18),
        'cas'            => ['od' => $_mockTs($_mockDen1, 15), 'do' => $_mockTs($_mockDen1, 18)],
        'linie'          => 'Workshop',
        'vBudoucnu'      => false,
        'vdalsiVlne'     => false,
        'probehnuta'     => false,
        'jeBrigadnicka'  => false,
        'prihlasovatelna'=> true,
        'tymova'         => false,
    ],
];

// Interní aktivity (jdou pouze do aktivitySkryte)
$_mockSkryta1099 = [
    'id'             => 1099,
    'nazev'          => '99 – interní, přihlášen, vedu',
    'kratkyPopis'    => 'interni=true, vedu=true, stavPrihlaseni=prihlasen',
    'popisId'        => 1099,
    'obrazek'        => '',
    'vypraveci'      => ['Hlavní Org'],
    'stitkyId'       => [],
    'cenaZaklad'     => 0,
    'casText'        => $_mockCas(14, 16),
    'cas'            => ['od' => $_mockTs($_mockDen3, 14), 'do' => $_mockTs($_mockDen3, 16)],
    'linie'          => 'Interní',
    'vBudoucnu'      => false,
    'vdalsiVlne'     => false,
    'probehnuta'     => false,
    'jeBrigadnicka'  => false,
    'prihlasovatelna'=> false,
    'tymova'         => false,
];

$_mockSkryta1098 = [
    'id'             => 1098,
    'nazev'          => '98 – interní, nepřihlášen, nevedu',
    'kratkyPopis'    => 'interni=true, vedu=false, stavPrihlaseni=null',
    'popisId'        => 1098,
    'obrazek'        => '',
    'vypraveci'      => ['Bezpečnostní Šéf'],
    'stitkyId'       => [],
    'cenaZaklad'     => 0,
    'casText'        => $_mockCas(11, 13),
    'cas'            => ['od' => $_mockTs($_mockDen3, 11), 'do' => $_mockTs($_mockDen3, 13)],
    'linie'          => 'Interní',
    'vBudoucnu'      => false,
    'vdalsiVlne'     => false,
    'probehnuta'     => false,
    'jeBrigadnicka'  => false,
    'prihlasovatelna'=> false,
    'tymova'         => false,
];

// -------------------------------------------------------------------
// Obsazenosti
// -------------------------------------------------------------------

$programTestMockObsazenosti = [
    ['idAktivity' => 1001, 'obsazenost' => ['m' => 2,  'f' => 1, 'km' => 5,  'kf' => 5,  'ku' => 0]],
    ['idAktivity' => 1002, 'obsazenost' => ['m' => 4,  'f' => 4, 'km' => 4,  'kf' => 4,  'ku' => 0]],
    ['idAktivity' => 1003, 'obsazenost' => ['m' => 15, 'f' => 8, 'km' => 20, 'kf' => 15, 'ku' => 0]],
    ['idAktivity' => 1004, 'obsazenost' => ['m' => 0,  'f' => 0, 'km' => 5,  'kf' => 5,  'ku' => 0]],
    ['idAktivity' => 1005, 'obsazenost' => ['m' => 5,  'f' => 3, 'km' => 0,  'kf' => 0,  'ku' => 20]],
    ['idAktivity' => 1006, 'obsazenost' => ['m' => 0,  'f' => 0, 'km' => 0,  'kf' => 0,  'ku' => 10]],
    ['idAktivity' => 1007, 'obsazenost' => ['m' => 0,  'f' => 0, 'km' => 0,  'kf' => 0,  'ku' => 0]],
    ['idAktivity' => 1008, 'obsazenost' => ['m' => 6,  'f' => 4, 'km' => 8,  'kf' => 6,  'ku' => 0]],
    ['idAktivity' => 1009, 'obsazenost' => ['m' => 2,  'f' => 3, 'km' => 0,  'kf' => 0,  'ku' => 8]],
    ['idAktivity' => 1010, 'obsazenost' => ['m' => 0,  'f' => 0, 'km' => 6,  'kf' => 6,  'ku' => 0]],
    ['idAktivity' => 1011, 'obsazenost' => ['m' => 3,  'f' => 2, 'km' => 5,  'kf' => 5,  'ku' => 0]],
    ['idAktivity' => 1012, 'obsazenost' => ['m' => 2,  'f' => 1, 'km' => 5,  'kf' => 5,  'ku' => 0]],
];

// -------------------------------------------------------------------
// Popisy: generují se automaticky z dat aktivity jako JSON
// -------------------------------------------------------------------

$_mockJsonPopis = static fn(array $a): string =>
    '<pre style="font-size:11px;line-height:1.4;white-space:pre-wrap;word-break:break-all">'
    . htmlspecialchars(json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), ENT_QUOTES | ENT_HTML5)
    . '</pre>';

$programTestMockPopisy = array_map(
    static fn(array $a) => ['id' => $a['popisId'], 'popis' => $_mockJsonPopis($a)],
    [...$programTestMockAktivity, $_mockSkryta1098, $_mockSkryta1099],
);

// -------------------------------------------------------------------
// Tagy
// -------------------------------------------------------------------

$programTestMockTagy = [
    ['id' => 1, 'nazev' => 'RPG',             'nazevKategorie' => 'Typ aktivity'],
    ['id' => 2, 'nazev' => 'Deskovky',        'nazevKategorie' => 'Typ aktivity'],
    ['id' => 3, 'nazev' => 'Workshop',        'nazevKategorie' => 'Typ aktivity'],
    ['id' => 4, 'nazev' => 'Brigáda',         'nazevKategorie' => 'Typ aktivity'],
    ['id' => 5, 'nazev' => 'Pro dospělé 18+', 'nazevKategorie' => 'Věková skupina'],
    ['id' => 6, 'nazev' => 'Pro děti',        'nazevKategorie' => 'Věková skupina'],
];

// -------------------------------------------------------------------
// prihlasenyUzivatel
// -------------------------------------------------------------------

$programTestMockPrihlasenyUzivatel = [
    'ucastnik' => ['id' => 9999, 'pohlavi' => 'm', 'gcStav' => 'přítomen', 'role' => ['organizator' => true]],
    'operator' => ['id' => 9999, 'pohlavi' => 'm', 'gcStav' => 'přítomen', 'role' => ['organizator' => true]],
];

// -------------------------------------------------------------------
// aktivityUzivatel
// -------------------------------------------------------------------

$programTestMockAktivityUzivatel = [
    'hash' => 'mock-hash-abc123',
    'data' => [
        'aktivityUzivatel' => [
            ['id' => 1001, 'stavPrihlaseni' => null,                   'slevaNasobic' => 1.0, 'mistnosti' => [$_mockLokace['l01']],  'vedu' => false, 'interni' => false],
            ['id' => 1002, 'stavPrihlaseni' => 'prihlasen',            'slevaNasobic' => 1.0, 'mistnosti' => [$_mockLokace['l02']],  'vedu' => false, 'interni' => false],
            ['id' => 1003, 'stavPrihlaseni' => 'prihlasenADorazil',    'slevaNasobic' => 1.0, 'mistnosti' => [$_mockLokace['l03']],  'vedu' => false, 'interni' => false],
            ['id' => 1004, 'stavPrihlaseni' => 'sledujici',            'slevaNasobic' => 1.0, 'mistnosti' => null,                   'vedu' => false, 'interni' => false],
            ['id' => 1005, 'stavPrihlaseni' => null,                   'slevaNasobic' => 1.0, 'mistnosti' => [$_mockLokace['l04']],  'vedu' => false, 'interni' => false],
            ['id' => 1006, 'stavPrihlaseni' => 'prihlasen',            'slevaNasobic' => 1.0, 'mistnosti' => [$_mockLokace['l04']],  'vedu' => false, 'interni' => false],
            ['id' => 1007, 'stavPrihlaseni' => null,                   'slevaNasobic' => 1.0, 'mistnosti' => null,                   'vedu' => false, 'interni' => false],
            ['id' => 1008, 'stavPrihlaseni' => 'dorazilJakoNahradnik', 'slevaNasobic' => 1.0, 'mistnosti' => null,                   'vedu' => false, 'interni' => false],
            ['id' => 1009, 'stavPrihlaseni' => 'prihlasen',            'slevaNasobic' => 1.0, 'mistnosti' => [$_mockLokace['l05']],  'vedu' => true,  'interni' => false],
            ['id' => 1010, 'stavPrihlaseni' => null,                   'slevaNasobic' => 1.0, 'mistnosti' => null,                   'vedu' => false, 'interni' => false],
            ['id' => 1011, 'stavPrihlaseni' => 'pozdeZrusil',          'slevaNasobic' => 1.0, 'mistnosti' => null,                   'vedu' => false, 'interni' => false],
            ['id' => 1012, 'stavPrihlaseni' => 'prihlasen',            'slevaNasobic' => 0.5, 'mistnosti' => [$_mockLokace['l06']],  'vedu' => false, 'interni' => false],
            ['id' => 1099, 'stavPrihlaseni' => 'prihlasen',            'slevaNasobic' => 1.0, 'mistnosti' => [$_mockLokace['l07']],  'vedu' => true,  'interni' => true],
            ['id' => 1098, 'stavPrihlaseni' => null,                   'slevaNasobic' => 1.0, 'mistnosti' => [$_mockLokace['l08']],  'vedu' => false, 'interni' => true],
        ],
        'aktivitySkryte' => [$_mockSkryta1099, $_mockSkryta1098],
    ],
];

// -------------------------------------------------------------------
// Manifest
// -------------------------------------------------------------------

$programTestMockManifest = [
    'aktivity'    => 'aktivity-mock.json',
    'popisy'      => 'popisy-mock.json',
    'obsazenosti' => 'obsazenosti-mock.json',
    'tagy'        => 'tagy-mock.json',
];
