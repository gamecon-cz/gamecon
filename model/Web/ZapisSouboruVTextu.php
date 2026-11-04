<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Jak se odkaz na soubor ke stažení vkládá do textů stránek, novinek a blogu.
 *
 * @see ZapisObrazkuVTextu stejná dvojice zápisů pro obrázek.
 */
class ZapisSouboruVTextu
{
    /**
     * @param string $cestaVuciWebu cesta souboru od kořene webu, nezakódovaná
     */
    public static function markdown(string $cestaVuciWebu): string
    {
        return '[' . self::popis($cestaVuciWebu) . '](' . ZpracovaneObrazky::zakodujCestu($cestaVuciWebu) . ')';
    }

    /**
     * @param string $cestaVuciWebu cesta souboru od kořene webu, nezakódovaná
     */
    public static function html(string $cestaVuciWebu): string
    {
        return '<a href="' . ZpracovaneObrazky::zakodujCestu($cestaVuciWebu) . '">'
            . htmlspecialchars(self::popis($cestaVuciWebu)) . '</a>';
    }

    private static function popis(string $cestaVuciWebu): string
    {
        return pathinfo($cestaVuciWebu, PATHINFO_FILENAME);
    }
}
