<?php declare(strict_types=1);

namespace Gamecon\Statistiky;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Pravo;
use Gamecon\Role\Zidle;
use Gamecon\Shop\Shop;

class Statistiky
{
    public const ZAROVNANI_K_ZACATKU_REGISTRACI = 'zacatekRegistaci';
    public const ZAROVNANI_KE_KONCI_GC          = 'konecGc';

    /**
     * @var int[]
     */
    private $roky;
    /**
     * @var int
     */
    private $letosniRok;

    /**
     * @param int[]|string[] $roky
     */
    public function __construct(array $roky, int $letosniRok) {
        $this->roky       = $roky;
        $this->letosniRok = $letosniRok;
    }

    /**
     * @param \DateTimeInterface $doChvile
     * @return array
     */
    public function dataProGrafUcasti(\DateTimeImmutable $doChvile): array {
        $data = [];
        foreach ($this->roky as $rok) {
            $data[$rok] = $this->dataProGrafUcastiZaRok((int)$rok, $doChvile);
        }
        return $data;
    }

    private function dataProGrafUcastiZaRok(int $rok, \DateTimeImmutable $doChvile): array {
        /** @var \DateTimeImmutable|DateTimeGamecon $zacatekRegistraci */
        $zacatekRegistraci = min(DateTimeGamecon::spocitejZacatekRegistraciUcastniku($rok), $doChvile);
        /** @var \DateTimeImmutable|DateTimeGamecon $konecGc */
        $konecGc     = DateTimeGamecon::spocitejKonecGameconu($rok);
        $posledniDen = min($konecGc, $doChvile);

        $ucastResult         = dbQuery(<<<SQL
SELECT
    SUBDATE(DATE($3), 1) AS den, -- všichni přihlášení před začátkem registrací nahloučení v jednom "dni"
    SUM(CASE log.zmena WHEN $1 THEN 1 WHEN $2 THEN -1 ELSE 0 END) as prihlasenych
  FROM r_uzivatele_zidle_log AS log
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE log.id_zidle = $0 AND log.kdy < $3
UNION ALL
SELECT
    DATE(log.kdy) AS den,
    SUM(CASE log.zmena WHEN $1 THEN 1 WHEN $2 THEN -1 ELSE 0 END) as prihlasenych
FROM r_uzivatele_zidle_log AS log
JOIN uzivatele_hodnoty u USING(id_uzivatele)
WHERE log.id_zidle = $0 AND log.kdy BETWEEN $3 AND $4
GROUP BY DATE(log.kdy)
UNION ALL
SELECT
    ADDDATE(DATE($4), 1) AS den, -- všichni přihlášení po GC nahloučení v jednom "dni"
    SUM(CASE log.zmena WHEN $1 THEN 1 WHEN $2 THEN -1 ELSE 0 END) as prihlasenych
  FROM r_uzivatele_zidle_log AS log
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE log.id_zidle = $0 AND log.kdy > $4

ORDER BY den
SQL,
            [
                Zidle::PRIHLASEN_NA_LETOSNI_GC($rok),
                \Uzivatel::POSAZEN,
                \Uzivatel::SESAZEN,
                $zacatekRegistraci,
                $posledniDen,
            ]
        );
        $prihlasenychCelkem  = 0;
        $prihlasenychPoDnech = [];
        while ($row = mysqli_fetch_assoc($ucastResult)) {
            $prihlasenychCelkem               += $row['prihlasenych'];
            $prihlasenychPoDnech[$row['den']] = $prihlasenychCelkem;
        }
        if ($rok < 2013) { // před rokem 2013 jsou datumy přihlášení 0000-00-00, respektive neznámé
            // netušíme, kdy se přihlásili, tak je hodíme na poslední den GC
            $prihlasenychPoDnech = [
                (clone $zacatekRegistraci)->modify('-1 day')->format(DateTimeCz::FORMAT_DATUM_DB) => 0,
                $konecGc->format(DateTimeCz::FORMAT_DATUM_DB)                                     => $prihlasenychCelkem,
                (clone $konecGc)->modify('+1 day')->format(DateTimeCz::FORMAT_DATUM_DB)           => $prihlasenychCelkem,
            ];
        }

        $den                    = DateTimeGamecon::createFromInterface($zacatekRegistraci);
        $prihlasenychDenPredtim = reset($prihlasenychPoDnech);
        while ($den < $konecGc) {
            $denString = $den->formatDatumDb();
            // vyplníme případné mezery ve dnech, kdy se nikdo nový nepřihlásil
            $prihlasenychPoDnech[$denString] = $prihlasenychPoDnech[$denString] ?? $prihlasenychDenPredtim;
            $prihlasenychDenPredtim          = $prihlasenychPoDnech[$denString];
            $den                             = $den->modify('+ 1 day');
        }

        ksort($prihlasenychPoDnech); // data potřebujeme od nejstaršího dne

        return $prihlasenychPoDnech;
    }

    public function tabulkaUcastiHtml(): string {
        $sledovaneZidle = array_merge(
            [Zidle::PRIHLASEN_NA_LETOSNI_GC($this->letosniRok), Zidle::PRITOMEN_NA_LETOSNIM_GC($this->letosniRok)],
            dbOneArray(
                'SELECT id_zidle FROM r_prava_zidle WHERE id_prava = $0',
                [Pravo::ZOBRAZOVAT_VE_STATISTIKACH_V_TABULCE_UCASTI]
            )
        );

        return tabMysql(dbQuery(<<<SQL
SELECT
    zidle.jmeno_zidle as "Role",
    COUNT(uzivatele_zidle.id_uzivatele) AS `<span class="hinted">Celkem<span class="hint">Všech uživatelů s rolí i bez přihlášení</span></span>`,
    COUNT(zidle_prihlasen.id_zidle) AS `<span class="hinted">Přihlášen<span class="hint">Letos přihlášených uživatelů s rolí</span></span>`
FROM r_zidle_soupis AS zidle
LEFT JOIN r_uzivatele_zidle AS uzivatele_zidle
    ON zidle.id_zidle = uzivatele_zidle.id_zidle
LEFT JOIN r_uzivatele_zidle AS zidle_prihlasen
    ON zidle_prihlasen.id_zidle = $0
        AND zidle_prihlasen.id_uzivatele = uzivatele_zidle.id_uzivatele
WHERE zidle.id_zidle IN ($1)
GROUP BY zidle.id_zidle, zidle.jmeno_zidle
ORDER BY SUBSTR(zidle.jmeno_zidle, 1, 10), zidle.id_zidle
SQL, [
            Zidle::PRIHLASEN_NA_LETOSNI_GC($this->letosniRok),
            $sledovaneZidle,
        ]), 'Účast');
    }

    public function tabulkaPredmetuHtml(): string {
        return tabMysql(dbQuery(<<<SQL
SELECT
    shop_predmety.nazev Název,
    shop_predmety.model_rok Model,
    COUNT(shop_nakupy.id_predmetu) Počet
FROM shop_nakupy
JOIN shop_predmety
    ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
WHERE shop_nakupy.rok=$0
    AND shop_predmety.typ IN ($1)
GROUP BY shop_nakupy.id_predmetu
SQL, [
            $this->letosniRok,
            [Shop::PREDMET, Shop::TRICKO],
        ]), 'Předměty');
    }

    public function tabulkaUbytovaniHtml(): string {
        return tabMysql(dbQuery(<<<SQL
SELECT Název, Počet FROM (
  SELECT
    predmety.nazev Název,
    COUNT(nakupy.id_predmetu) Počet,
    FIND_IN_SET(
        SUBSTR(predmety.nazev,1,6),
        'Jednol,Dvojlů,Trojlů,Spacák'
    ) AS ubytovani_sort_nazev,
    predmety.ubytovani_den
  FROM shop_nakupy AS nakupy
  JOIN shop_predmety AS predmety ON nakupy.id_predmetu=predmety.id_predmetu
  WHERE nakupy.rok=$0 AND predmety.typ=$1
  GROUP BY nakupy.id_predmetu
) AS seskupeno
ORDER BY ubytovani_sort_nazev, ubytovani_den
SQL, [
            0 => $this->letosniRok,
            1 => Shop::UBYTOVANI,
        ]), 'Ubytování dny a místa');
    }

    public function tabulkaUbytovaniKratce(): string {

        return tabMysql(dbQuery(<<<SQL
SELECT Den, Počet FROM (
    SELECT
        SUBSTR(predmety.nazev,11) Den,
        COUNT(nakupy.id_predmetu) Počet,
        predmety.ubytovani_den
    FROM shop_nakupy AS nakupy
    JOIN shop_predmety AS predmety
        ON nakupy.id_predmetu=predmety.id_predmetu
    WHERE nakupy.rok=$0
        AND predmety.typ=$1
    GROUP BY predmety.ubytovani_den
UNION ALL
    SELECT 'neubytovaní' as Den,
         COUNT(*) as Počet,
         'zzz' AS ubytovani_den
    FROM r_uzivatele_zidle AS uzivatele_zidle
    LEFT JOIN(
        SELECT nakupy.id_uzivatele
        FROM shop_nakupy AS nakupy
        JOIN shop_predmety AS predmety
            ON nakupy.id_predmetu=predmety.id_predmetu
                AND predmety.typ=$1
        WHERE nakupy.rok=$0
        GROUP BY nakupy.id_uzivatele
    ) nn ON nn.id_uzivatele=uzivatele_zidle.id_uzivatele
    WHERE id_zidle=$2 AND ISNULL(nn.id_uzivatele)
ORDER BY ubytovani_den
) AS serazeno
SQL, [
            0 => $this->letosniRok,
            1 => Shop::UBYTOVANI,
            2 => Zidle::PRIHLASEN_NA_LETOSNI_GC($this->letosniRok),
        ]), 'Ubytování dny');
    }

    public function tabulkaJidlaHtml(): string {

        return tabMysql(dbQuery(<<<SQL
SELECT Název,Cena,Počet,Slev FROM (
  SELECT
    TRIM(predmety.nazev) Název,
    predmety.cena_aktualni AS Cena, -- například v roce 2022 jsme část jídla prodali za menší cenu a část za větší - mohlo by se to stát u čehokoliv
    COUNT(nakupy.id_predmetu) Počet,
    COUNT(slevy.id_uzivatele) as Slev, -- počet slev
    predmety.ubytovani_den,
    nakupy.id_predmetu
  FROM shop_nakupy AS nakupy
  JOIN shop_predmety AS predmety ON nakupy.id_predmetu = predmety.id_predmetu
  LEFT JOIN (
    SELECT uz.id_uzivatele -- id uživatelů s právy uvedenými níž
    FROM r_uzivatele_zidle uz
    JOIN r_prava_zidle pz ON pz.id_zidle = uz.id_zidle AND pz.id_prava IN($0)
    GROUP BY uz.id_uzivatele
  ) AS slevy ON slevy.id_uzivatele = nakupy.id_uzivatele
  WHERE nakupy.rok = $1 AND predmety.typ = $2
  GROUP BY nakupy.id_predmetu
) AS seskupeno
ORDER BY ubytovani_den, Název, id_predmetu
SQL,
            [[Pravo::JIDLO_ZDARMA, Pravo::JIDLO_SE_SLEVOU], $this->letosniRok, Shop::JIDLO]
        ), 'Jídlo');
    }

    public function tabulkaZastoupeniPohlaviHtml(): string {
        return tabMysqlR(dbQuery(<<<SQL
    SELECT
    'Počet' AS ' ', -- formátování
    COALESCE(SUM(IF(uzivatele.pohlavi='m',1,0)), 0) as Muži,
    COALESCE(SUM(IF(uzivatele.pohlavi='f',1,0)), 0) as Ženy,
    COALESCE(ROUND(SUM(IF(uzivatele.pohlavi='f',1,0))/COUNT(1),2), 0) as Poměr
    FROM r_uzivatele_zidle AS uzivatele_zidle
    JOIN uzivatele_hodnoty AS uzivatele ON uzivatele_zidle.id_uzivatele=uzivatele.id_uzivatele
    WHERE uzivatele_zidle.id_zidle = $0
SQL,
            [Zidle::PRIHLASEN_NA_LETOSNI_GC($this->letosniRok)]
        ),
            'Pohlaví'
        );
    }

    public function pripravDataProGraf(array $prihlaseniData, array $vybraneRoky, string $zarovnaniGrafu): array {
        $nazvyDnu         = [];
        $zacatkyRegistaci = [];
        $zacatkyGc        = [];
        $konceGc          = [];
        $prihlaseniProJs  = [];

        $dataChtenychRoku     = [];
        $delkaNejdelsihoGrafu = 0;
        foreach ($prihlaseniData as $rok => $dataJednohoRoku) {
            if ((int)$rok === 2020) {
                continue; // Call of Covid
            }
            if (!in_array($rok, $vybraneRoky, false)) {
                continue;
            }
            $dataChtenychRoku[$rok] = $dataJednohoRoku;
            $delkaNejdelsihoGrafu   = max($delkaNejdelsihoGrafu, count($dataJednohoRoku));
        }

        foreach ($dataChtenychRoku as $rok => $dataJednohoRoku) {
            if ($zarovnaniGrafu === self::ZAROVNANI_KE_KONCI_GC) {
                $delkaGrafuJednohoRoku = count($dataJednohoRoku);
                $zarovnaniNulamiZleva  = array_fill(0, $delkaNejdelsihoGrafu - $delkaGrafuJednohoRoku, 0);
                array_unshift($dataJednohoRoku, ...$zarovnaniNulamiZleva); // aby každý graf měl délku jako nejdelší rok
            }
            array_unshift($dataJednohoRoku, 0); // aby každý graf včetně nejdelšího vždy začínal pěkne na nule
            $prihlaseniProJs[]            = [
                'name' => "Přihlášených $rok",
                'data' => array_values($dataJednohoRoku), // JS knihovna vyžaduje číselné indexování
            ];
            $dnyJednohoRoku               = array_keys($dataJednohoRoku);
            $nazvyDnuJednohoRoku          = [];
            $zacatekRegistraciJednohoRoku = \Gamecon\Cas\DateTimeGamecon::spocitejZacatekRegistraciUcastniku($rok)->formatDatumDb();
            $zacatekGcJednohoRoku         = \Gamecon\Cas\DateTimeGamecon::spocitejZacatekGameconu($rok)->formatDatumDb();
            $konecGcJednohoRoku           = \Gamecon\Cas\DateTimeGamecon::spocitejKonecGameconu($rok)->formatDatumDb();
            foreach ($dnyJednohoRoku as $indexDne => $denJednohoRoku) {
                // včetně indexu 0, což je vynucená nula přes array_unshift
                if ($indexDne === 0) {
                    $nazvyDnuJednohoRoku[] = 'před prvním přihlášeným';
                } elseif ($indexDne === 1) {
                    $nazvyDnuJednohoRoku[] = 'před registracemi';
                } else {
                    $denRegistraci         = $indexDne - 1;
                    $nazvyDnuJednohoRoku[] = "den $denRegistraci";
                }
                if ($zacatekRegistraciJednohoRoku === $denJednohoRoku) {
                    $prvniDenRegistraciJednohoRoku = end($nazvyDnuJednohoRoku);
                    $zacatkyRegistaci[$rok]        = $prvniDenRegistraciJednohoRoku;
                } elseif ($zacatekGcJednohoRoku === $denJednohoRoku) {
                    $prvniDenGcRoku  = end($nazvyDnuJednohoRoku);
                    $zacatkyGc[$rok] = $prvniDenGcRoku;
                } elseif ($konecGcJednohoRoku === $denJednohoRoku) {
                    $posledniDenGcRoku = end($nazvyDnuJednohoRoku);
                    $konceGc[$rok]     = $posledniDenGcRoku;
                }
            }
            array_pop($nazvyDnuJednohoRoku);
            $nazvyDnuJednohoRoku[] = 'po GC'; // všechny dny po GC smrsknute do jediného, posledního sloupce v grafu
            $nazvyDnu              = array_unique(array_merge($nazvyDnu, $nazvyDnuJednohoRoku));
        }

        return [
            'nazvyDnu'         => $nazvyDnu,
            'zacatkyRegistaci' => $zacatkyRegistaci,
            'zacatkyGc'        => $zacatkyGc,
            'konceGc'          => $konceGc,
            'prihlaseniProJs'  => $prihlaseniProJs,
        ];
    }

    public function tabulkaHistorieRegistrovaniVsDoraziliHtml(): string {
        $prihlasen = Zidle::VYZNAM_PRIHLASEN;
        $pritomen = Zidle::VYZNAM_PRITOMEN;
        $ucast = Zidle::TYP_UCAST;
        return tabMysqlR(dbQuery(<<<SQL
SELECT
    rok AS ' ', -- formátování
    Registrovaných,
    Dorazilo,
    studenti AS ` z toho studenti`,
    IF (studenti = '', '', Dorazilo - studenti) AS ` z toho ostatní`,
    `Podpůrný tým`,
    organizátoři AS ` organizátoři`,
    zázemí AS ` zázemí`,
    vypravěči AS ` vypravěči`
FROM (
    SELECT
        rok,
        SUM(IF(registrace, 1, 0)) AS Registrovaných,
        SUM(IF(dorazeni, 1, 0)) AS Dorazilo,
        CASE rok
            WHEN 2013 THEN 149
            WHEN 2014 THEN 172
            WHEN 2015 THEN 148
            WHEN 2016 THEN 175
            WHEN 2017 THEN 153
            ELSE '' END
        AS studenti,
        CASE rok
            WHEN 2009 THEN 43
            WHEN 2010 THEN 45
            WHEN 2011 THEN 71
            WHEN 2012 THEN 74
            WHEN 2013 THEN 88
            WHEN 2014 THEN 109
            WHEN 2015 THEN 111
            WHEN 2016 THEN 133
            WHEN 2017 THEN 186
            WHEN 2018 THEN 176
            WHEN 2019 THEN 185
            WHEN 2021 THEN 198
            WHEN {$this->letosniRok} THEN SUM(IF(dorazeni AND EXISTS(SELECT * FROM r_uzivatele_zidle WHERE r_uzivatele_zidle.id_uzivatele = podle_roku.id_uzivatele AND r_uzivatele_zidle.id_zidle IN ($0, $1, $2)), 1 , 0))
            ELSE SUM(IF(dorazeni AND EXISTS(
                SELECT * FROM r_uzivatele_zidle_log AS posazen
                    LEFT JOIN r_uzivatele_zidle_log AS sesazen ON sesazen.id_zidle = posazen.id_zidle AND sesazen.id_uzivatele =posazen.id_uzivatele AND sesazen.kdy > posazen.kdy AND sesazen.zmena = $4
                WHERE posazen.zmena = $3 AND sesazen.id_uzivatele IS NULL /* neexistuje novější záznam */ AND posazen.id_uzivatele = podle_roku.id_uzivatele AND posazen.id_zidle IN ($0, $1, $2)
                ), 1 , 0)) END
        AS `Podpůrný tým`,
        CASE rok
            WHEN 2009 THEN 6
            WHEN 2010 THEN 8
            WHEN 2011 THEN 13
            WHEN 2012 THEN 17
            WHEN 2013 THEN 17
            WHEN 2014 THEN 22
            WHEN 2015 THEN 24
            WHEN 2016 THEN 28
            WHEN 2017 THEN 38
            WHEN 2018 THEN 38
            WHEN 2019 THEN 38
            WHEN 2021 THEN 37
            WHEN {$this->letosniRok} THEN SUM(IF(dorazeni AND EXISTS(SELECT * FROM r_uzivatele_zidle WHERE r_uzivatele_zidle.id_uzivatele = podle_roku.id_uzivatele AND r_uzivatele_zidle.id_zidle = $0), 1 , 0))
            ELSE SUM(IF(dorazeni AND EXISTS(
                SELECT * FROM r_uzivatele_zidle_log AS posazen
                    LEFT JOIN r_uzivatele_zidle_log AS sesazen ON sesazen.id_zidle = posazen.id_zidle AND sesazen.id_uzivatele =posazen.id_uzivatele AND sesazen.kdy > posazen.kdy AND sesazen.zmena = $4
                WHERE posazen.zmena = $3 AND sesazen.id_uzivatele IS NULL /* neexistuje novější záznam */ AND posazen.id_uzivatele = podle_roku.id_uzivatele AND posazen.id_zidle = $0
            ), 1 , 0)) END
        AS organizátoři,
        CASE rok
            WHEN 2009 THEN 7
            WHEN 2010 THEN 7
            WHEN 2011 THEN 6
            WHEN 2012 THEN 10
            WHEN 2013 THEN 8
            WHEN 2014 THEN 1
            WHEN 2015 THEN 3
            WHEN 2016 THEN 1
            WHEN 2017 THEN 8
            WHEN 2018 THEN ''
            WHEN 2019 THEN ''
            WHEN 2021 THEN 15
            WHEN {$this->letosniRok} THEN SUM(IF(dorazeni AND EXISTS(SELECT * FROM r_uzivatele_zidle WHERE r_uzivatele_zidle.id_uzivatele = podle_roku.id_uzivatele AND r_uzivatele_zidle.id_zidle = $1), 1 , 0))
            ELSE SUM(IF(dorazeni AND EXISTS(
                SELECT * FROM r_uzivatele_zidle_log AS posazen
                    LEFT JOIN r_uzivatele_zidle_log AS sesazen ON sesazen.id_zidle = posazen.id_zidle AND sesazen.id_uzivatele =posazen.id_uzivatele AND sesazen.kdy > posazen.kdy AND sesazen.zmena = $4
                WHERE posazen.zmena = $3 AND sesazen.id_uzivatele IS NULL /* neexistuje novější záznam */ AND posazen.id_uzivatele = podle_roku.id_uzivatele AND posazen.id_zidle = $1
            ), 1 , 0)) END
        AS zázemí,
        CASE rok
            WHEN 2009 THEN 30
            WHEN 2010 THEN 30
            WHEN 2011 THEN 52
            WHEN 2012 THEN 47
            WHEN 2013 THEN 63
            WHEN 2014 THEN 86
            WHEN 2015 THEN 95
            WHEN 2016 THEN 122
            WHEN 2017 THEN 168
            WHEN 2018 THEN 138
            WHEN 2019 THEN 147
            WHEN 2021 THEN 146
            WHEN {$this->letosniRok} THEN SUM(IF(dorazeni AND EXISTS(SELECT * FROM r_uzivatele_zidle WHERE r_uzivatele_zidle.id_uzivatele = podle_roku.id_uzivatele AND r_uzivatele_zidle.id_zidle = $2), 1 , 0))
            ELSE SUM(IF(dorazeni AND EXISTS(
                SELECT * FROM r_uzivatele_zidle_log AS posazen
                    LEFT JOIN r_uzivatele_zidle_log AS sesazen ON sesazen.id_zidle = posazen.id_zidle AND sesazen.id_uzivatele = posazen.id_uzivatele AND sesazen.kdy > posazen.kdy AND sesazen.zmena = $4
                WHERE posazen.zmena = $3 AND sesazen.id_uzivatele IS NULL /* neexistuje novější záznam */ AND posazen.id_uzivatele = podle_roku.id_uzivatele AND posazen.id_zidle = $2
            ), 1 , 0)) END
        AS vypravěči
    FROM (
        SELECT
            zidle.rok,
            uzivatele_zidle.id_zidle,
            zidle.vyznam = '$prihlasen' AS registrace,
            zidle.vyznam = '$pritomen' AS dorazeni,
            uzivatele_zidle.id_uzivatele
            FROM r_uzivatele_zidle AS uzivatele_zidle
            JOIN r_zidle_soupis AS zidle
                ON uzivatele_zidle.id_zidle = zidle.id_zidle
            WHERE zidle.typ = '$ucast'
    ) AS podle_roku
    GROUP BY rok
) AS pocty
SQL, [
            0 => Zidle::ORGANIZATOR,
            1 => Zidle::LETOSNI_ZAZEMI,
            2 => Zidle::LETOSNI_VYPRAVEC,
            3 => \Uzivatel::POSAZEN,
            4 => \Uzivatel::SESAZEN,
        ]), 'Registrovaní vs Dorazili');
    }

    public function tabulkaLidiNaGcCelkemHtml(): string {
        return tabMysqlR(dbQuery(<<<SQL
SELECT
    rok AS ' ', -- formátování
    Dorazilo AS `Dorazilo na GC celkem`,
    muzu AS ` z toho muži`,
    zen AS ` z toho ženy`,
    CONCAT(CAST(zen / muzu * 100 AS UNSIGNED), ' %') ` podíl žen`
FROM (
    SELECT
        rok,
        COUNT(*) AS Dorazilo,
        SUM(IF(pohlavi = 'm', 1, 0)) AS muzu,
        SUM(IF(pohlavi= 'f', 1, 0)) AS zen
    FROM (
        SELECT
            2000 - (uzivatele_zidle.id_zidle DIV 100) AS rok,
            uzivatele_hodnoty.pohlavi
            FROM r_uzivatele_zidle AS uzivatele_zidle
            JOIN uzivatele_hodnoty ON uzivatele_zidle.id_uzivatele = uzivatele_hodnoty.id_uzivatele
            WHERE uzivatele_zidle.id_zidle % 100 = -2
    ) AS podle_roku
    GROUP BY rok
) AS pohlavi
SQL
        ), 'Lidé na GC celkem');
    }

    public function tabulkaHistorieProdanychPredmetuHtml(): string {
        return tabMysqlR(dbQuery(<<<SQL
SELECT 2009 AS '', 43 AS 'Prodané placky', 43 AS 'Prodané kostky', 6 AS 'Prodaná trička'
UNION ALL
SELECT 2010 AS '', 45 AS 'Prodané placky', 45 AS 'Prodané kostky', 8 AS 'Prodaná trička'
UNION ALL
SELECT 2011 AS '', 206 AS 'Prodané placky', 247 AS 'Prodané kostky', 104 AS 'Prodaná trička'
UNION ALL
SELECT 2012 AS '', 224 AS 'Prodané placky', 154 AS 'Prodané kostky', 121 AS 'Prodaná trička'
UNION ALL
SELECT 2013 AS '', 207 AS 'Prodané placky', 192 AS 'Prodané kostky', 139 AS 'Prodaná trička'
UNION ALL
SELECT
    shop_nakupy.rok AS '',
    SUM(shop_predmety.nazev LIKE 'Placka%' AND shop_nakupy.rok = shop_predmety.model_rok) AS 'Prodané placky',
    SUM(shop_predmety.nazev LIKE 'Kostka%' AND shop_nakupy.rok = shop_predmety.model_rok) AS 'Prodané kostky',
    SUM(shop_predmety.nazev like 'Tričko%' AND shop_nakupy.rok = shop_predmety.model_rok) AS 'Prodaná trička'
FROM shop_nakupy
JOIN shop_predmety ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
WHERE shop_nakupy.rok >= 2014 /* starší data z DB nesedí, jsou vložena fixně */
    AND shop_nakupy.rok != 2020 /* Call of covid */
GROUP BY shop_nakupy.rok
ORDER BY ''
SQL
        ),
            'Prodané předměty');
    }

    public function tabulkaHistorieUbytovaniHtml(): string {
        return tabMysqlR(dbQuery(<<<SQL
SELECT
    shop_nakupy.rok AS '',
    SUM(nazev LIKE '%lůžák%') AS 'Postel',
    SUM(nazev LIKE '%lůžák%' AND ubytovani_den=0) AS '&emsp;středa',
    SUM(nazev LIKE '%lůžák%' AND ubytovani_den=1) AS '&emsp;čtvrtek',
    SUM(nazev LIKE '%lůžák%' AND ubytovani_den=2) AS '&emsp;pátek',
    SUM(nazev LIKE '%lůžák%' AND ubytovani_den=3) AS '&emsp;sobota',
    SUM(nazev LIKE '%lůžák%' AND ubytovani_den=4) AS '&emsp;neděle',
    SUM(nazev LIKE 'spacák%') AS 'Spacák',
    SUM(nazev LIKE 'spacák%' AND ubytovani_den=0) AS '&emsp;středa ',
    SUM(nazev LIKE 'spacák%' AND ubytovani_den=1) AS '&emsp;čtvrtek ',
    SUM(nazev LIKE 'spacák%' AND ubytovani_den=2) AS '&emsp;pátek ',
    SUM(nazev LIKE 'spacák%' AND ubytovani_den=3) AS '&emsp;sobota ',
    SUM(nazev LIKE 'spacák%' AND ubytovani_den=4) AS '&emsp;neděle ',
    SUM(nazev LIKE 'penzion%') AS 'Penzion',
    SUM(nazev LIKE 'penzion%' AND ubytovani_den=0) AS '&emsp;středa  ',
    SUM(nazev LIKE 'penzion%' AND ubytovani_den=1) AS '&emsp;čtvrtek  ',
    SUM(nazev LIKE 'penzion%' AND ubytovani_den=2) AS '&emsp;pátek  ',
    SUM(nazev LIKE 'penzion%' AND ubytovani_den=3) AS '&emsp;sobota  ',
    SUM(nazev LIKE 'penzion%' AND ubytovani_den=4) AS '&emsp;neděle  ',
    SUM(nazev LIKE 'chata%') AS 'Kemp',
    SUM(nazev LIKE 'chata%' AND ubytovani_den=0) AS '&emsp;středa   ',
    SUM(nazev LIKE 'chata%' AND ubytovani_den=1) AS '&emsp;čtvrtek   ',
    SUM(nazev LIKE 'chata%' AND ubytovani_den=2) AS '&emsp;pátek   ',
    SUM(nazev LIKE 'chata%' AND ubytovani_den=3) AS '&emsp;sobota   ',
    SUM(nazev LIKE 'chata%' AND ubytovani_den=4) AS '&emsp;neděle   '
FROM shop_nakupy
JOIN shop_predmety USING (id_predmetu)
WHERE shop_predmety.typ = $0
GROUP BY shop_nakupy.rok
ORDER BY shop_nakupy.rok
SQL,
            [
                0 => Shop::UBYTOVANI,
            ]
        ), 'Ubytování');
    }
}
