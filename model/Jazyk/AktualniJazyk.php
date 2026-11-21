<?php

declare(strict_types=1);

namespace Gamecon\Jazyk;

/**
 * Jazyk, ve kterém se vykresluje právě zpracovávaný požadavek.
 *
 * Nastavuje ho web podle prefixu URL ({@see JazykPozadavku}). Admin, cron a CLI ho nenastavují,
 * takže tam platí čeština. Kód, který píše konkrétnímu uživateli mimo jeho požadavek
 * (e-mail z cronu), má jazyk předat explicitně, ne spoléhat na tenhle stav.
 */
final class AktualniJazyk
{
    private static ?Jazyk $jazyk = null;

    public static function jazyk(): Jazyk
    {
        return self::$jazyk ?? Jazyk::vychozi();
    }

    public static function nastav(Jazyk $jazyk): void
    {
        self::$jazyk = $jazyk;
    }

    /**
     * Provede $akce v daném jazyce a pak vrátí jazyk, který platil předtím (i po výjimce).
     * Pro data sdílená všemi jazyky (statický JSON programu), aby do nich nepronikl jazyk návštěvníka.
     *
     * @template T
     * @param callable(): T $akce
     * @return T
     */
    public static function provedVJazyce(Jazyk $jazyk, callable $akce): mixed
    {
        $puvodni = self::$jazyk;
        self::$jazyk = $jazyk;
        try {
            return $akce();
        } finally {
            self::$jazyk = $puvodni;
        }
    }

    /**
     * Pro testy, aby si jazyk nepřenášely mezi sebou.
     */
    public static function reset(): void
    {
        self::$jazyk = null;
    }
}
