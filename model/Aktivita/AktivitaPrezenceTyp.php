<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class AktivitaPrezenceTyp
{
    public const PRIHLASENI = 'prihlaseni';
    public const ODHLASENI = 'odhlaseni';
    public const DORAZIL = 'dorazil';
    public const NEDOSTAVENI_SE = 'nedostaveni_se';
    public const ODHLASENI_HROMADNE = 'odhlaseni_hromadne';
    public const DORAZIL_JAKO_NAHRADNIK = 'prihlaseni_nahradnik'; // TODO zmenit enum v databázi a hodnotu téhle konstanty, aby to odpovídalo tomu, co logujeme
    public const NAHRADNIK_NEDORAZIL = 'zruseni_prihlaseni_nahradnik';
    public const PRIHLASENI_SLEDUJICI = 'prihlaseni_watchlist'; // TODO zmenit enum v databázi a hodnotu téhle konstanty, aby to odpovídalo více používanému českému názvu
    public const ODHLASENI_SLEDUJICI = 'odhlaseni_watchlist'; // TODO zmenit enum v databázi a hodnotu téhle konstanty, aby to odpovídalo více používanému českému názvu

    public static function jeZnamy(string $typ): bool {
        static $konstanty;
        if (!isset($konstanty)) {
            $konstanty = (new \ReflectionClass(AktivitaPrezenceTyp::class))->getConstants();
        }
        return in_array($typ, $konstanty, true);
    }

    public static function dorazilNejak(string $typ) {
        $typ = strtolower(trim($typ));
        if (in_array($typ, self::dejTypySDorazenim(), true)) {
            return true;
        }
        if (in_array($typ, self::dejTypySNedorazenim(), true)) {
            return false;
        }
        throw new \LogicException('Neznámý typ ' . var_export($typ, true));
    }

    /**
     * @return array|string[]
     */
    public static function dejTypySDorazenim(): array {
        return [
            self::DORAZIL,
            self::DORAZIL_JAKO_NAHRADNIK,
        ];
    }

    /**
     * @return array|string[]
     */
    public static function dejTypySNedorazenim(): array {
        return [
            self::PRIHLASENI,
            self::ODHLASENI,
            self::NEDOSTAVENI_SE,
            self::ODHLASENI_HROMADNE,
            self::NAHRADNIK_NEDORAZIL,
            self::PRIHLASENI_SLEDUJICI,
            self::ODHLASENI_SLEDUJICI,
        ];
    }
}
