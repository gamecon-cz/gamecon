<?php
declare(strict_types=1);

namespace Gamecon\Shop;

use App\Enum\ProductTagCode;
use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as Sql;
use Gamecon\Uzivatel\Dto\PolozkaProBfgr;

/**
 * For Doctrine entity equivalent @see \App\Entity\Product
 *
 * @method static Predmet|null zId($id, bool $zCache = false)
 */
class Predmet extends \DbObject
{
    protected static $tabulka = Sql::SHOP_PREDMETY_TABULKA;
    protected static $pk = Sql::ID_PREDMETU;

    public static function jeToVstupneVcas(?ProductTagCode $kategorie, string $kodPredmetu): bool
    {
        return $kategorie === ProductTagCode::VSTUPNE && !self::jeToDleCasti($kodPredmetu, 'pozde');
    }

    public static function jeToVstupnePozde(?ProductTagCode $kategorie, string $kodPredmetu): bool
    {
        return $kategorie === ProductTagCode::VSTUPNE && self::jeToDleCasti($kodPredmetu, 'pozde');
    }

    public static function jeToKostka(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'kostka');
    }

    public static function jeToNicknack(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'nicknack');
    }

    public static function jeToBlok(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'blok');
    }

    public static function jeToPonozka(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'ponozk');
    }

    public static function jeToPlacka(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'placka');
    }

    public static function jeToTaska(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'taska');
    }

    public static function jeToMikina(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'mikina');
    }

    public static function jeToSnidane(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'snidane');
    }

    public static function jeToObed(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'obed');
    }

    public static function jeToVecere(string $kodPredmetu): bool
    {
        return self::jeToDleCasti($kodPredmetu, 'vecere');
    }

    /**
     * Reporty nechtějí barvu, ale hodnost — kolik odznaků které úrovně se rozdalo
     * (výstupní klíče `Nr-TrickaVypravecskaZdarma` apod.). Barva je jen historická
     * náhražka: v roce 2009 a 2010 byla orgovská trička oranžová, takže hledání
     * „červen" v názvu je 14 kusů počítalo jako účastnická.
     */
    public static function jeToVypravecske(PolozkaProBfgr $polozka): bool
    {
        return self::jeToDleCasti($polozka->kodPredmetu, 'vypravecske');
    }

    /**
     * Viz {@see jeToVypravecske()} — hodnost z kódu, ne barva z názvu.
     */
    public static function jeToOrganizatorske(PolozkaProBfgr $polozka): bool
    {
        return self::jeToDleCasti($polozka->kodPredmetu, 'organizatorske');
    }

    public static function jeToTricko(
        string          $kodPredmetu,
        ?ProductTagCode $kategorie,
    ): bool {
        return $kategorie === ProductTagCode::TRICKO && self::jeToDleCasti($kodPredmetu, 'tricko');
    }

    public static function jeToTilko(
        string          $kodPredmetu,
        ?ProductTagCode $kategorie,
    ): bool {
        return $kategorie === ProductTagCode::TRICKO && self::jeToDleCasti($kodPredmetu, 'tilko');
    }

    private static function jeToDleCasti(
        string $cele,
        string $cast,
    ): bool {
        return mb_stripos($cele, $cast) !== false;
    }

    public function nazev(): string
    {
        return (string)$this->r[Sql::NAZEV];
    }

    public function stav(int $stav = null): int
    {
        if ($stav !== null) {
            $this->r[Sql::STAV] = $stav;
        }

        return (int)$this->r[Sql::STAV];
    }
}
