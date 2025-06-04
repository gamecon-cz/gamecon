<?php
declare(strict_types=1);

namespace Gamecon\Shop;

use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as Sql;

/**
 * @method static Predmet|null zId($id, bool $zCache = false)
 */
class Predmet extends \DbObject
{
    protected static $tabulka         = Sql::SHOP_PREDMETY_TABULKA;
    protected static $pk              = Sql::ID_PREDMETU;
    protected static $letosniPredmety = [];

    public static function jeToKostka(string $nazev): bool
    {
        return mb_stripos($nazev, 'Kostka') !== false;
    }

    public static function jeToPlacka(string $nazev): bool
    {
        return mb_stripos($nazev, 'Placka') !== false;
    }

    public static function jeToTaska(string $nazev): bool
    {
        return mb_stripos($nazev, 'Taška') !== false;
    }

    public static function jeToModre(string $nazev): bool
    {
        return mb_stripos($nazev, 'modré') !== false;
    }

    public static function letosniKostka(int $rocnik): ?static
    {
        return self::letosniPredmet('Kostka', $rocnik);
    }

    private static function letosniPredmet(
        string $castNazvu,
        int    $rocnik,
    ): ?static {
        if (!array_key_exists($castNazvu, self::$letosniPredmety)) {
            $typPredmet       = TypPredmetu::PREDMET;
            $castNazvuSql     = dbQRaw($castNazvu);
            $letosniPredmetId = dbFetchSingle(<<<SQL
SELECT id_predmetu
FROM shop_predmety
WHERE
    -- letošní je z aktuálního ročníku a veřejný, v případě více výsledků vezmeme ten s popiskem, v krajním případě ten novější
    model_rok = {$rocnik} AND
    stav = 1 AND
    typ = {$typPredmet} AND
    nazev COLLATE utf8_czech_ci LIKE '%{$castNazvuSql}%'
ORDER BY model_rok DESC, cena_aktualni DESC, popis DESC, id_predmetu Desc
LIMIT 1 -- pro jistotu
SQL,
            );
            $letosniPredmet   = $letosniPredmetId
                ? static::zId((int)$letosniPredmetId, true)
                : null;
        }

        return $letosniPredmet;
    }

    public static function letosniPlacka(int $rocnik): ?static
    {
        return self::letosniPredmet('Placka', $rocnik);
    }

    public function kusuVyrobeno(int $kusuVyrobeno = null): int
    {
        if ($kusuVyrobeno !== null) {
            $this->r['kusu_vyrobeno'] = $kusuVyrobeno;
        }

        return (int)$this->r['kusu_vyrobeno'];
    }

    public function nazev(): string
    {
        return (string)$this->r[Sql::NAZEV];
    }

    public function cenaAktualni(): float
    {
        return (float)$this->r[Sql::CENA_AKTUALNI];
    }

    public function stav(int $stav = null): int
    {
        if ($stav !== null) {
            $this->r[Sql::STAV] = $stav;
        }

        return (int)$this->r[Sql::STAV];
    }

    public function modelRok(): ?int
    {
        if ($this->r[Sql::MODEL_ROK] === null) {
            return null;
        }

        return (int)$this->r[Sql::MODEL_ROK];
    }

    public function typ(): ?int
    {
        if ($this->r[Sql::TYP] === null) {
            return null;
        }

        return (int)$this->r[Sql::TYP];
    }
}
