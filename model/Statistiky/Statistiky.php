<?php
declare(strict_types=1);

namespace Gamecon\Statistiky;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Pravo;
use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class Statistiky
{
    public const ZAROVNANI_K_ZACATKU_REGISTRACI = 'zacatekRegistaci';
    public const ZAROVNANI_KE_KONCI_GC          = 'konecGc';

    private int $soucasnyRocnik;

    /**
     * @param int[]|string[] $roky
     */
    public function __construct(
        private readonly array              $roky,
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
        $this->soucasnyRocnik = $this->systemoveNastaveni->rocnik();
    }

    public function dataProGrafUcasti(\DateTimeImmutable $doChvile): array
    {
        $data = [];
        foreach ($this->roky as $rok) {
            $data[$rok] = $this->dataProGrafUcastiZaRok((int)$rok, $doChvile);
        }

        return $data;
    }

    private function dataProGrafUcastiZaRok(int $rok, \DateTimeImmutable $doChvile): array
    {
        /** @var \DateTimeImmutable|DateTimeGamecon $zacatekRegistraci */
        $zacatekRegistraci = min(DateTimeGamecon::spocitejPrihlasovaniUcastnikuOd($rok), $doChvile);
        /** @var \DateTimeImmutable|DateTimeGamecon $konecGc */
        $konecGc     = DateTimeGamecon::spocitejKonecGameconu($rok);
        $posledniDen = min($konecGc, $doChvile);

        $ucastResult         = dbQuery(<<<SQL
SELECT
    SUBDATE(DATE($3), 1) AS den, -- všichni přihlášení před začátkem registrací nahloučení v jednom "dni"
    SUM(CASE log.zmena WHEN $1 THEN 1 WHEN $2 THEN -1 ELSE 0 END) AS prihlasenych
  FROM uzivatele_role_log AS log
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE log.id_role = $0 AND log.kdy < $3
UNION ALL
SELECT
    DATE(log.kdy) AS den,
    SUM(CASE log.zmena WHEN $1 THEN 1 WHEN $2 THEN -1 ELSE 0 END) AS prihlasenych
FROM uzivatele_role_log AS log
JOIN uzivatele_hodnoty u USING(id_uzivatele)
WHERE log.id_role = $0 AND log.kdy BETWEEN $3 AND $4
GROUP BY DATE(log.kdy)
UNION ALL
SELECT
    ADDDATE(DATE($4), 1) AS den, -- všichni přihlášení po GC nahloučení v jednom "dni"
    SUM(CASE log.zmena WHEN $1 THEN 1 WHEN $2 THEN -1 ELSE 0 END) AS prihlasenych
  FROM uzivatele_role_log AS log
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE log.id_role = $0 AND log.kdy > $4
ORDER BY den
SQL,
            [
                0 => Role::PRIHLASEN_NA_LETOSNI_GC($rok),
                1 => \Uzivatel::POSAZEN,
                2 => \Uzivatel::SESAZEN,
                $zacatekRegistraci,
                $posledniDen,
            ],
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

    public function tabulkaUcastiHtml(): string
    {
        $sledovaneRole = [
            ...[
                Role::PRIHLASEN_NA_LETOSNI_GC($this->soucasnyRocnik),
                Role::PRITOMEN_NA_LETOSNIM_GC($this->soucasnyRocnik),
            ],
            ...dbOneArray(
                'SELECT id_role FROM prava_role WHERE id_prava = $0',
                [
                    0 => Pravo::ZOBRAZOVAT_VE_STATISTIKACH_V_TABULCE_UCASTI,
                ],
            ),
        ];

        return tabMysql(
            dbQuery(
                <<<SQL
SELECT
    role_seznam.nazev_role AS "Role",
    COUNT(DISTINCT uzivatele_role.id_uzivatele) AS `<span class="hinted">Celkem<span class="hint">Všech uživatelů s rolí i bez přihlášení</span></span>`,
    COUNT(DISTINCT letos_prihlasen.id_uzivatele) AS `<span class="hinted">Přihlášen<span class="hint">Letos přihlášených uživatelů s rolí</span></span>`
FROM role_seznam
LEFT JOIN uzivatele_role
    ON role_seznam.id_role = uzivatele_role.id_role
LEFT JOIN uzivatele_role AS letos_prihlasen
    ON letos_prihlasen.id_role = $0
        AND letos_prihlasen.id_uzivatele = uzivatele_role.id_uzivatele
WHERE role_seznam.id_role IN ($1)
GROUP BY role_seznam.id_role, role_seznam.nazev_role
ORDER BY SUBSTR(role_seznam.nazev_role, 1, 10), role_seznam.id_role
SQL,
                [
                    0 => Role::PRIHLASEN_NA_LETOSNI_GC($this->soucasnyRocnik),
                    1 => $sledovaneRole,
                ],
            ),
            'Účast',
        );
    }

    public function tabulkaPredmetuHtml(): string
    {
        return tabMysql(
            dbQuery(
                <<<SQL
SELECT
    shop_predmety.nazev AS Název,
    shop_predmety.model_rok AS Model,
    COUNT(shop_nakupy.id_predmetu) AS Počet
FROM shop_nakupy
JOIN shop_predmety
    ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
WHERE shop_nakupy.rok = $0
    AND shop_predmety.typ IN ($1)
GROUP BY shop_nakupy.id_predmetu
SQL,
                [
                    0 => $this->soucasnyRocnik,
                    1 => [
                        TypPredmetu::PREDMET,
                        TypPredmetu::TRICKO,
                    ],
                ],
            ),
            'Předměty',
        );
    }

    public function tabulkaUbytovaniHtml(): string
    {
        return tabMysql(
            dbQuery(
                <<<SQL
SELECT Název, Počet
FROM (
  SELECT
    predmety.nazev AS Název,
    COUNT(nakupy.id_predmetu) AS Počet,
    FIND_IN_SET(
        SUBSTR(TRIM(predmety.nazev), 1, 6),
        'Jednol,Dvojlů,Trojlů,Spacák'
    ) AS ubytovani_sort_nazev,
    predmety.ubytovani_den
  FROM shop_nakupy AS nakupy
  JOIN shop_predmety AS predmety ON nakupy.id_predmetu = predmety.id_predmetu
  WHERE nakupy.rok = $0 AND predmety.typ = $1
  GROUP BY nakupy.id_predmetu
) AS seskupeno
ORDER BY ubytovani_sort_nazev, ubytovani_den
SQL,
                [
                    0 => $this->soucasnyRocnik,
                    1 => TypPredmetu::UBYTOVANI,
                ],
            ),
            'Ubytování dny a místa',
        );
    }

    public function tabulkaUbytovaniKratce(): string
    {

        return tabMysql(
            dbQuery(
                <<<SQL
SELECT Den, Počet FROM (
    SELECT
        SUBSTR(predmety.nazev,11) AS Den,
        COUNT(nakupy.id_predmetu) AS Počet,
        predmety.ubytovani_den
    FROM shop_nakupy AS nakupy
    JOIN shop_predmety AS predmety
        ON nakupy.id_predmetu=predmety.id_predmetu
    WHERE nakupy.rok=$0
        AND predmety.typ=$1
    GROUP BY predmety.ubytovani_den
UNION ALL
    SELECT 'neubytovaní' AS Den,
         COUNT(*) AS Počet,
         'zzz' AS ubytovani_den
    FROM uzivatele_role AS uzivatele_role
    LEFT JOIN(
        SELECT nakupy.id_uzivatele
        FROM shop_nakupy AS nakupy
        JOIN shop_predmety AS predmety
            ON nakupy.id_predmetu=predmety.id_predmetu
                AND predmety.typ=$1
        WHERE nakupy.rok=$0
        GROUP BY nakupy.id_uzivatele
    ) nn ON nn.id_uzivatele=uzivatele_role.id_uzivatele
    WHERE id_role=$2 AND ISNULL(nn.id_uzivatele)
ORDER BY ubytovani_den
) AS serazeno
SQL,
                [
                    0 => $this->soucasnyRocnik,
                    1 => TypPredmetu::UBYTOVANI,
                    2 => Role::PRIHLASEN_NA_LETOSNI_GC($this->soucasnyRocnik),
                ],
            ),
            'Ubytování dny',
        );
    }

    public function tabulkaJidlaHtml(): string
    {

        return tabMysql(
            dbQuery(
                <<<SQL
SELECT Název,Cena,Počet,Slev FROM (
  SELECT
    TRIM(predmety.nazev) AS Název,
    predmety.cena_aktualni AS Cena, -- například v roce 2022 jsme část jídla prodali za menší cenu a část za větší - mohlo by se to stát u čehokoliv
    COUNT(nakupy.id_predmetu) AS Počet,
    COUNT(slevy.id_uzivatele) AS Slev, -- počet slev
    predmety.ubytovani_den,
    nakupy.id_predmetu
  FROM shop_nakupy AS nakupy
  JOIN shop_predmety AS predmety ON nakupy.id_predmetu = predmety.id_predmetu
  LEFT JOIN (
    SELECT uz.id_uzivatele -- id uživatelů s právy uvedenými níž
    FROM uzivatele_role uz
    JOIN prava_role pz ON pz.id_role = uz.id_role AND pz.id_prava IN($0)
    GROUP BY uz.id_uzivatele
  ) AS slevy ON slevy.id_uzivatele = nakupy.id_uzivatele
  WHERE nakupy.rok = $1 AND predmety.typ = $2
  GROUP BY nakupy.id_predmetu
) AS seskupeno
ORDER BY ubytovani_den, Název, id_predmetu
SQL,
                [
                    0 => [Pravo::JIDLO_ZDARMA, Pravo::JIDLO_SE_SLEVOU],
                    1 => $this->soucasnyRocnik,
                    2 => TypPredmetu::JIDLO,
                ],
            ),
            'Jídlo',
        );
    }

    public function tabulkaZastoupeniPohlaviHtml(): string
    {
        return tabMysqlR(
            dbQuery(
                <<<SQL
    SELECT
    'Počet' AS ' ', -- formátování
    COALESCE(SUM(IF(uzivatele.pohlavi='m',1,0)), 0) AS Muži,
    COALESCE(SUM(IF(uzivatele.pohlavi='f',1,0)), 0) AS Ženy,
    COALESCE(ROUND(SUM(IF(uzivatele.pohlavi='f',1,0))/COUNT(1),2), 0) AS Poměr
    FROM uzivatele_role
    JOIN uzivatele_hodnoty AS uzivatele ON uzivatele_role.id_uzivatele=uzivatele.id_uzivatele
    WHERE uzivatele_role.id_role = $0
SQL,
                [
                    0 => Role::PRIHLASEN_NA_LETOSNI_GC($this->soucasnyRocnik),
                ],
            ),
            'Pohlaví',
        );
    }

    public function pripravDataProGraf(array $prihlaseniData, array $vybraneRoky, string $zarovnaniGrafu): array
    {
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

        $indexLetosnihoRoku = -1;
        $indexDnesnihoDne   = -1;

        $indexZpracovavanehoRoku = 0;
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
            $zacatekRegistraciJednohoRoku = DateTimeGamecon::spocitejPrihlasovaniUcastnikuOd($rok)->formatDatumDb();
            $zacatekGcJednohoRoku         = DateTimeGamecon::spocitejZacatekGameconu($rok)->formatDatumDb();
            $konecGcJednohoRoku           = DateTimeGamecon::spocitejKonecGameconu($rok)->formatDatumDb();
            $dnes                         = $this->systemoveNastaveni->ted()->formatDatumDb();

            if ($rok === $this->soucasnyRocnik) {
                $indexLetosnihoRoku = $indexZpracovavanehoRoku;
            }

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
                if ($rok === $this->soucasnyRocnik && $denJednohoRoku === $dnes) {
                    $indexDnesnihoDne = $indexDne;
                }
            }
            array_pop($nazvyDnuJednohoRoku);
            $nazvyDnuJednohoRoku[] = 'po GC'; // všechny dny po GC smrsknute do jediného, posledního sloupce v grafu
            $nazvyDnu              = array_unique([...$nazvyDnu, ...$nazvyDnuJednohoRoku]);
            $indexZpracovavanehoRoku++;
        }

        return [
            'nazvyDnu'           => $nazvyDnu,
            'zacatkyRegistaci'   => $zacatkyRegistaci,
            'zacatkyGc'          => $zacatkyGc,
            'konceGc'            => $konceGc,
            'indexDnesnihoDne'   => $indexDnesnihoDne,
            'indexLetosnihoRoku' => $indexLetosnihoRoku,
            'prihlaseniProJs'    => $prihlaseniProJs,
        ];
    }

    public function tabulkaHistorieRegistrovaniVsDoraziliHtml(): string
    {
        $vyznamPrihlasen  = Role::VYZNAM_PRIHLASEN;
        $vyznamPritomen   = Role::VYZNAM_PRITOMEN;
        $letosniVypravec  = Role::LETOSNI_VYPRAVEC;
        $letosniZazemi    = Role::LETOSNI_ZAZEMI;
        $roleOrganizatoru = implode(',', [Role::ORGANIZATOR, Role::PUL_ORG_BONUS_TRICKO, Role::PUL_ORG_BONUS_UBYTKO]);
        $vyznamZazemi     = Role::VYZNAM_ZAZEMI;
        $vyznamVypravec   = Role::VYZNAM_VYPRAVEC;

        return tabMysqlR(
            dbQuery(
                <<<SQL
SELECT
    rocnik_role AS ' ', -- formátování
    Registrovaných,
    `registrovaných nováčků` AS ` &zwnj;z toho nováčků`,
    Dorazilo,
    `přihlášených nováčků` AS ` z toho nováčků`,
    studenti AS ` z toho studenti`,
    IF (studenti = '', '', Dorazilo - studenti) AS ` z toho ostatní`,
    `Podpůrný tým`,
    organizátoři AS ` organizátoři`,
    zázemí AS ` zázemí`,
    vypravěči AS ` vypravěči`
FROM (
    SELECT
        rocnik_role,
        SUM(IF(registrovan, 1, 0)) AS Registrovaných,
        SUM(IF(dorazil, 1, 0)) AS Dorazilo,
        CASE rocnik_role
            WHEN 2013 THEN 149
            WHEN 2014 THEN 172
            WHEN 2015 THEN 148
            WHEN 2016 THEN 175
            WHEN 2017 THEN 153
            ELSE '' END
        AS studenti,
        CASE rocnik_role
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
            WHEN 2022 THEN 177
            WHEN {$this->soucasnyRocnik}
                THEN SUM(
                    IF(
                        dorazil
                        AND EXISTS(
                            SELECT *
                            FROM uzivatele_role
                            WHERE uzivatele_role.id_uzivatele = ucast_podle_roku.id_uzivatele
                                AND uzivatele_role.id_role IN ({$roleOrganizatoru}, {$letosniZazemi}, {$letosniVypravec})
                        ),
                        1,
                        0
                    )
                )
            ELSE SUM(
                IF(
                    dorazil
                    AND EXISTS(
                        SELECT *
                        FROM uzivatele_role_podle_rocniku AS podpurny_tym
                        JOIN role_seznam AS podpurny_tym_detail_role
                            ON podpurny_tym.id_role = podpurny_tym_detail_role.id_role
                        WHERE podpurny_tym.id_uzivatele = ucast_podle_roku.id_uzivatele
                            AND podpurny_tym.rocnik = ucast_podle_roku.rocnik_role
                            AND (
                                podpurny_tym.id_role IN ({$roleOrganizatoru})
                                OR podpurny_tym_detail_role.vyznam_role IN ('{$vyznamZazemi}', '{$vyznamVypravec}')
                            )
                    ),
                    1,
                    0
                )
            ) END
        AS `Podpůrný tým`,
        CASE rocnik_role
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
            WHEN 2022 THEN 39
            WHEN {$this->soucasnyRocnik}
                THEN SUM(
                    IF(
                        dorazil
                        AND EXISTS(
                            SELECT *
                            FROM uzivatele_role AS pouze_organizatori
                            WHERE pouze_organizatori.id_uzivatele = ucast_podle_roku.id_uzivatele
                                AND pouze_organizatori.id_role IN ({$roleOrganizatoru})
                        ),
                        1,
                        0
                    )
                )
            ELSE SUM(
                IF(
                    dorazil
                    AND EXISTS(
                        SELECT *
                        FROM uzivatele_role_podle_rocniku AS pouze_organizatori
                        WHERE pouze_organizatori.id_uzivatele = ucast_podle_roku.id_uzivatele
                            AND pouze_organizatori.rocnik = ucast_podle_roku.rocnik_role
                            AND pouze_organizatori.id_role IN ('{$roleOrganizatoru}')
                    ),
                    1,
                    0
                )
            ) END
        AS organizátoři,
        CASE rocnik_role
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
            WHEN 2022 THEN 10
            ELSE SUM(
                    IF(
                        dorazil
                        AND EXISTS(
                            SELECT *
                            FROM uzivatele_role_podle_rocniku AS zazemi
                            JOIN role_seznam AS zazemi_detail_role
                                ON zazemi.id_role = zazemi_detail_role.id_role
                            WHERE zazemi.id_uzivatele = ucast_podle_roku.id_uzivatele
                                AND zazemi.rocnik = ucast_podle_roku.rocnik_role
                                AND zazemi_detail_role.vyznam_role = '{$vyznamZazemi}'
                        ),
                        1,
                        0
                    )
                )
            END
            AS zázemí,
        CASE rocnik_role
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
            WHEN 2022 THEN 116
            ELSE SUM(
                    IF(
                        dorazil
                        AND EXISTS(
                            SELECT *
                            FROM uzivatele_role_podle_rocniku AS vypravec
                            JOIN role_seznam AS vypravec_detail_role
                                ON vypravec.id_role = vypravec_detail_role.id_role
                            WHERE vypravec.id_uzivatele = ucast_podle_roku.id_uzivatele
                                AND vypravec.rocnik = ucast_podle_roku.rocnik_role
                                AND vypravec_detail_role.vyznam_role = '{$vyznamVypravec}'
                        ),
                        1,
                        0
                    )
            ) END
        AS vypravěči,
        CASE
            WHEN rocnik_role <= 2021 THEN ''
            ELSE SUM(
                    IF(
                        EXISTS(
                            SELECT *
                            FROM uzivatele_role AS prihlaseni_na_rocnik
                            JOIN role_seznam AS prihlaseni_na_rocnik_role
                                ON prihlaseni_na_rocnik.id_role = prihlaseni_na_rocnik_role.id_role
                                    AND prihlaseni_na_rocnik.id_uzivatele = ucast_podle_roku.id_uzivatele
                            WHERE prihlaseni_na_rocnik_role.rocnik_role = ucast_podle_roku.rocnik_role
                                AND prihlaseni_na_rocnik_role.vyznam_role = '{$vyznamPrihlasen}'
                        )
                        AND NOT EXISTS(
                            SELECT *
                            FROM uzivatele_role AS prihlaseni_na_starsi_rocnik
                            JOIN role_seznam AS prihlaseni_na_starsi_rocnik_role
                                ON prihlaseni_na_starsi_rocnik.id_role = prihlaseni_na_starsi_rocnik_role.id_role
                                    AND prihlaseni_na_starsi_rocnik.id_uzivatele = ucast_podle_roku.id_uzivatele
                            -- nemá žádné přihlášení ze strarších ročníků
                            WHERE prihlaseni_na_starsi_rocnik_role.rocnik_role < ucast_podle_roku.rocnik_role
                                AND prihlaseni_na_starsi_rocnik_role.vyznam_role = '{$vyznamPrihlasen}'
                        )
                        AND NOT EXISTS(
                            SELECT *
                            FROM uzivatele_role AS prihlaseni_na_novejsi_rocnik
                            JOIN role_seznam AS prihlaseni_na_novejsi_rocnik_role
                                ON prihlaseni_na_novejsi_rocnik.id_role = prihlaseni_na_novejsi_rocnik_role.id_role
                                    AND prihlaseni_na_novejsi_rocnik.id_uzivatele = ucast_podle_roku.id_uzivatele
                            -- nemá žádné přihlášení z novějších ročníků
                            WHERE prihlaseni_na_novejsi_rocnik_role.rocnik_role > ucast_podle_roku.rocnik_role
                                AND prihlaseni_na_novejsi_rocnik_role.vyznam_role = '{$vyznamPrihlasen}'
                        ),
                        1,
                        0
                    )
                ) END
        AS `registrovaných nováčků`,
        CASE
            WHEN rocnik_role <= 2021 THEN ''
            ELSE SUM(
                    IF(
                        EXISTS(
                            SELECT *
                            FROM uzivatele_role AS pritomen_na_rocniku
                            JOIN role_seznam AS pritomen_na_rocniku_role
                                ON pritomen_na_rocniku.id_role = pritomen_na_rocniku_role.id_role
                                    AND pritomen_na_rocniku.id_uzivatele = ucast_podle_roku.id_uzivatele
                            WHERE pritomen_na_rocniku_role.rocnik_role = ucast_podle_roku.rocnik_role
                                AND pritomen_na_rocniku_role.vyznam_role = '{$vyznamPritomen}'
                        )
                        AND NOT EXISTS(
                            SELECT *
                            FROM uzivatele_role AS pritomen_na_starsim_rocniku
                            JOIN role_seznam AS pritomen_na_starsim_rocniku_role
                                ON pritomen_na_starsim_rocniku.id_role = pritomen_na_starsim_rocniku_role.id_role
                                    AND pritomen_na_starsim_rocniku.id_uzivatele = ucast_podle_roku.id_uzivatele
                            -- nemá žádné přihlášení ze strarších ročníků
                            WHERE pritomen_na_starsim_rocniku_role.rocnik_role < ucast_podle_roku.rocnik_role
                                AND pritomen_na_starsim_rocniku_role.vyznam_role = '{$vyznamPritomen}'
                        )
                        AND NOT EXISTS(
                            SELECT *
                            FROM uzivatele_role AS pritomen_na_novejsim_rocniku
                            JOIN role_seznam AS pritomen_na_novejsim_rocniku_role
                                ON pritomen_na_novejsim_rocniku.id_role = pritomen_na_novejsim_rocniku_role.id_role
                                    AND pritomen_na_novejsim_rocniku.id_uzivatele = ucast_podle_roku.id_uzivatele
                            -- nemá žádné přihlášení z novějších ročníků
                            WHERE pritomen_na_novejsim_rocniku_role.rocnik_role > ucast_podle_roku.rocnik_role
                                AND pritomen_na_novejsim_rocniku_role.vyznam_role = '{$vyznamPrihlasen}'
                        ),
                        1,
                        0
                    )
                ) END
        AS `přihlášených nováčků`
    FROM (
        SELECT
            role_seznam.rocnik_role,
            uzivatele_role.id_role,
            uzivatele_role.id_uzivatele,
            role_seznam.vyznam_role = '$vyznamPrihlasen' AS registrovan,
            role_seznam.vyznam_role = '$vyznamPritomen' AS dorazil
            FROM uzivatele_role
            JOIN role_seznam
                ON uzivatele_role.id_role = role_seznam.id_role
            WHERE role_seznam.vyznam_role IN ('$vyznamPrihlasen', '$vyznamPritomen')
    ) AS ucast_podle_roku
    GROUP BY rocnik_role
) AS pocty
SQL
            ),
            'Registrovaní vs Dorazili',
        );
    }

    public function tabulkaLidiNaGcCelkemHtml(): string
    {
        return tabMysqlR(
            dbQuery(
                <<<SQL
SELECT
    rok AS ' ', -- formátování
    celkem AS `Dorazilo na GC celkem`,
    muzu AS ` z toho muži`,
    zen AS ` z toho ženy`,
    CONCAT(CAST(zen / celkem * 100 AS UNSIGNED), ' %') AS ` podíl žen`
FROM (
    SELECT
        rok,
        COUNT(*) AS celkem,
        SUM(IF(pohlavi = 'm', 1, 0)) AS muzu,
        SUM(IF(pohlavi= 'f', 1, 0)) AS zen
    FROM (
        SELECT
            2000 - (uzivatele_role.id_role DIV 100) AS rok,
            uzivatele_hodnoty.pohlavi
            FROM uzivatele_role AS uzivatele_role
            JOIN uzivatele_hodnoty ON uzivatele_role.id_uzivatele = uzivatele_hodnoty.id_uzivatele
            WHERE uzivatele_role.id_role % 100 = -2
    ) AS ucast_podle_roku
    GROUP BY rok
) AS pohlavi
SQL,
            ),
            'Lidé na GC celkem',
        );
    }

    public function tabulkaHistorieProdanychPredmetuHtml(): string
    {
        return tabMysqlR(
            dbQuery(
                <<<SQL
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
    SUM(shop_predmety.nazev LIKE 'Tričko%' AND shop_nakupy.rok = shop_predmety.model_rok) AS 'Prodaná trička'
FROM shop_nakupy
JOIN shop_predmety ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
WHERE shop_nakupy.rok >= 2014 /* starší data z DB nesedí, jsou vložena fixně */
    AND shop_nakupy.rok != 2020 /* Call of covid */
GROUP BY shop_nakupy.rok
ORDER BY ''
SQL,
            ),
            'Prodané předměty',
        );
    }

    public function tabulkaHistorieUbytovaniHtml(): string
    {
        $ubytovani = TypPredmetu::UBYTOVANI;

        return tabMysqlR(
            dbQuery(
                <<<SQL
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
WHERE shop_predmety.typ = {$ubytovani}
GROUP BY shop_nakupy.rok
ORDER BY shop_nakupy.rok
SQL
            ),
            'Ubytování',
        );
    }
}
