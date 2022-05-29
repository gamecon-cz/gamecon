<?php declare(strict_types=1);

namespace Gamecon\Statistiky;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Pravo;
use Gamecon\Zidle;

class Statistiky
{
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
        $this->roky = $roky;
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
        $zacatek = min(DateTimeGamecon::spocitejZacatekRegistraciUcastniku($rok), $doChvile);
        $konec = min(DateTimeGamecon::spocitejKonecGameconu($rok), $doChvile);

        $ucastResult = dbQuery(<<<SQL
SELECT
    SUBDATE(DATE($3), 1) AS den, -- pred začátkem registrací
    SUM(CASE log.zmena WHEN $1 THEN 1 WHEN $2 THEN -1 ELSE 0 END) as prihlasenych
  FROM r_uzivatele_zidle_log AS log
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE log.id_zidle = $0 AND log.kdy < $3
  GROUP BY DATE(log.kdy)
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
    ADDDATE(DATE($4), 1) AS den, -- po GC
    SUM(CASE log.zmena WHEN $1 THEN 1 WHEN $2 THEN -1 ELSE 0 END) as prihlasenych
  FROM r_uzivatele_zidle_log AS log
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE log.id_zidle = $0 AND log.kdy > $4
  GROUP BY DATE(log.kdy)

ORDER BY den
SQL,
            [
                Zidle::prihlasenNaGcRoku($rok),
                \Uzivatel::POSAZEN,
                \Uzivatel::SESAZEN,
                $zacatek,
                $konec,
            ]
        );
        $prihlasenychCelkem = 0;
        $prihlasenychPoDnech = [];
        while ($row = mysqli_fetch_assoc($ucastResult)) {
            $prihlasenychCelkem += $row['prihlasenych'];
            $prihlasenychPoDnech[$row['den']] = $prihlasenychCelkem;
        }

        $den = $zacatek;
        $prihlasenychDenPredtim = reset($prihlasenychPoDnech);
        while ($den < $konec) {
            $denString = $den->formatDatumDb();
            // vyplníme případné mezery ve dnech, kdy se nikdo nový nepřihlásil
            $prihlasenychPoDnech[$denString] = $prihlasenychPoDnech[$denString] ?? $prihlasenychDenPredtim;
            $prihlasenychDenPredtim = $prihlasenychPoDnech[$denString];
            $den = $den->modify('+ 1 day');
        }
        ksort($prihlasenychPoDnech); // data potřebujeme od nejstaršího dne

        return $prihlasenychPoDnech;
    }

    public function tabulkaUcastiHtml(): string {
        $sledovaneZidle = array_merge(
            [Zidle::prihlasenNaGcRoku($this->letosniRok), Zidle::pritomenNaGcRoku($this->letosniRok)],
            dbOneArray(
                'SELECT id_zidle FROM r_prava_zidle WHERE id_prava = $0',
                [Pravo::ZOBRAZOVAT_VE_STATISTIKACH_V_TABULCE_UCASTI]
            )
        );

        return tabMysql(dbQuery(<<<SQL
  SELECT
    jmeno_zidle as " ",
    COUNT(uzivatele_zidle.id_uzivatele) as Celkem,
    COUNT(z_prihlasen.id_zidle) as Přihlášen
  FROM r_zidle_soupis AS zidle
  LEFT JOIN r_uzivatele_zidle AS uzivatele_zidle ON zidle.id_zidle = uzivatele_zidle.id_zidle
  LEFT JOIN r_uzivatele_zidle AS z_prihlasen ON
    z_prihlasen.id_zidle = $0 AND
    z_prihlasen.id_uzivatele = uzivatele_zidle.id_uzivatele
  WHERE zidle.id_zidle IN ($1)
  GROUP BY zidle.id_zidle, zidle.jmeno_zidle
  ORDER BY SUBSTR(zidle.jmeno_zidle, 1, 10), zidle.id_zidle
SQL, [
            Zidle::prihlasenNaGcRoku($this->letosniRok),
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
  JOIN shop_predmety ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
  WHERE shop_nakupy.rok=$0 AND shop_predmety.typ IN ($1)
  GROUP BY shop_nakupy.id_predmetu
SQL, [
            $this->letosniRok,
            [\Shop::PREDMET, \Shop::TRICKO],
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
            $this->letosniRok,
            \Shop::UBYTOVANI,
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
  JOIN shop_predmety AS predmety ON nakupy.id_predmetu=predmety.id_predmetu
  WHERE nakupy.rok=$0 AND predmety.typ=$1
  GROUP BY predmety.ubytovani_den
UNION ALL
  SELECT 'neubytovaní' as Den,
         COUNT(*) as Počet,
         'zzz' AS ubytovani_den
  FROM r_uzivatele_zidle AS uzivatele_zidle
  LEFT JOIN(
    SELECT nakupy.id_uzivatele
    FROM shop_nakupy AS nakupy
    JOIN shop_predmety AS predmety ON nakupy.id_predmetu=predmety.id_predmetu AND predmety.typ=$1
    WHERE nakupy.rok=$0
    GROUP BY nakupy.id_uzivatele
  ) nn ON nn.id_uzivatele=uzivatele_zidle.id_uzivatele
  WHERE id_zidle=$2 AND ISNULL(nn.id_uzivatele)
ORDER BY ubytovani_den
) AS serazeno
SQL, [
            $this->letosniRok,
            \Shop::UBYTOVANI,
            Zidle::prihlasenNaGcRoku($this->letosniRok),
        ]), 'Ubytování dny');
    }

    public function tabulkaJidlaHtml(): string {

        return tabMysql(dbQuery(<<<SQL
SELECT Název,Cena,Počet,Sleva FROM (
  SELECT
    TRIM(predmety.nazev) Název,
    predmety.cena_aktualni AS Cena, -- například v roce 2022 jsme část jídla prodali za menší cenu a část za větší - mohlo by se to stát u čehokoliv
    COUNT(nakupy.id_predmetu) Počet,
    COUNT(slevy.id_uzivatele) as Sleva,
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
            [[Pravo::JIDLO_ZDARMA, Pravo::JIDLO_SE_SLEVOU], $this->letosniRok, \Shop::JIDLO]
        ), 'Jídlo');
    }

    public function tabulkaZastoupeniPohlaviHtml(): string {
        return tabMysqlR(dbQuery(<<<SQL
  SELECT
    'Počet' as ' ', -- formátování
    SUM(IF(uzivatele.pohlavi='m',1,0)) as Muži,
    SUM(IF(uzivatele.pohlavi='f',1,0)) as Ženy,
    ROUND(SUM(IF(uzivatele.pohlavi='f',1,0))/COUNT(1),2) as Poměr
  FROM r_uzivatele_zidle AS uzivatele_zidle
  JOIN uzivatele_hodnoty AS uzivatele ON uzivatele_zidle.id_uzivatele=uzivatele.id_uzivatele
  WHERE uzivatele_zidle.id_zidle = $0
SQL, [
            Zidle::prihlasenNaGcRoku($this->letosniRok),
        ]), 'Pohlaví');

    }
}
