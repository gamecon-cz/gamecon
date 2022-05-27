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

    public function __construct(array $roky) {
        $this->roky = $roky;
    }

    /**
     * @param int[]|string[] $roky
     * @return array
     */
    public function data(): array {
        $data = [];
        foreach ($this->roky as $rok) {
            $data[$rok] = $this->dataZaRok((int)$rok);
        }
        return $data;
    }

    private function dataZaRok(int $rok): array {
        // graf účasti
        $ucastResult = dbQuery(<<<SQL
SELECT
    DATE(log.kdy) as den,
    SUM(CASE log.zmena WHEN $1 THEN 1 WHEN $2 THEN -1 ELSE 0 END) as prihlasenych
  FROM r_uzivatele_zidle_log AS log
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE log.id_zidle = $0 AND log.kdy BETWEEN $3 AND $4
  GROUP BY DATE(log.kdy)
  ORDER BY log.kdy
SQL,
            [
                Zidle::prihlasenNaGcRoku($rok),
                \Uzivatel::POSAZEN,
                \Uzivatel::SESAZEN,
                new \DateTimeImmutable($rok . '-01-01 00:00:00'),
                DateTimeGamecon::spocitejKonecGameconu($rok),
            ]
        );
        $prihlaseniCelkem = 0;
        $prihlaseniPoDnech = [];
        $den = 0;
        while ($row = mysqli_fetch_assoc($ucastResult)) {
            $prihlaseniCelkem += $row['prihlasenych'];
            $den++;
            $prihlaseniPoDnech[] = $prihlaseniCelkem;
        }

        return $prihlaseniPoDnech;
    }
}
