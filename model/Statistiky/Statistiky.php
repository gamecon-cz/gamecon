<?php declare(strict_types=1);

namespace Gamecon\Statistiky;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Zidle;

class Statistiky
{
    /**
     * @var int[]
     */
    private $roky;

    /**
     * @param int[]|string[] $roky
     */
    public function __construct(array $roky) {
        $this->roky = $roky;
    }

    /**
     * @param \DateTimeInterface $doChvile
     * @return array
     */
    public function data(\DateTimeImmutable $doChvile): array {
        $data = [];
        foreach ($this->roky as $rok) {
            $data[$rok] = $this->dataZaRok((int)$rok, $doChvile);
        }
        return $data;
    }

    private function dataZaRok(int $rok, \DateTimeImmutable $doChvile): array {
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
}
