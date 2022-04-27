<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class AktivitaPrezenceTyp
{
    public const PRIHLASENI = 'prihlaseni';
    public const ODHLASENI = 'odhlaseni';
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
}
