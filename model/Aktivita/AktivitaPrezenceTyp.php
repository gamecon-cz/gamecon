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
    public const ZRUSENI_PRIHLASENI_NAHRADNIK = 'zruseni_prihlaseni_nahradnik';
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
        switch (strtolower(trim($typ))) {
            case self::PRIHLASENI :
            case self::ODHLASENI :
            case self::NEDOSTAVENI_SE :
            case self::ODHLASENI_HROMADNE :
            case self::ZRUSENI_PRIHLASENI_NAHRADNIK :
            case self::PRIHLASENI_SLEDUJICI :
            case self::ODHLASENI_SLEDUJICI :
                return false;
            case self::DORAZIL :
            case self::DORAZIL_JAKO_NAHRADNIK :
                return true;
            default :
                throw new \LogicException('Neznámý typ ' . var_export($typ, true));
        }
    }
}
