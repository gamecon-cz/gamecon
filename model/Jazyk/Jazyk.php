<?php

declare(strict_types=1);

namespace Gamecon\Jazyk;

/**
 * Jazyk rozhraní veřejného webu a e-mailů účastníkovi. Admin je jen česky.
 *
 * Hodnota backingu je kód jazyka: je v URL (`/en/…`), v cookie, v `uzivatele_hodnoty.jazyk`
 * a v názvech katalogů překladů (`symfony/translations/messages+intl-icu.{kod}.yaml`).
 */
enum Jazyk: string
{
    case CESTINA = 'cs';

    case ANGLICTINA = 'en';

    public static function vychozi(): self
    {
        return self::CESTINA;
    }

    public function jeVychozi(): bool
    {
        return $this === self::vychozi();
    }

    /**
     * Prefix cesty na webu: čeština je bez prefixu, ostatní jazyky mají `kod/`.
     */
    public function prefixCesty(): string
    {
        return $this->jeVychozi()
            ? ''
            : $this->value . '/';
    }

    /**
     * Název jazyka v něm samém, jak ho ukazuje přepínač jazyka.
     */
    public function vlastniNazev(): string
    {
        return match ($this) {
            self::CESTINA    => 'Česky',
            self::ANGLICTINA => 'English',
        };
    }

    /**
     * Locale pro `og:locale` a pro formátování přes intl (data, čísla).
     */
    public function locale(): string
    {
        return match ($this) {
            self::CESTINA    => 'cs_CZ',
            self::ANGLICTINA => 'en_GB',
        };
    }
}
