<?php declare(strict_types=1);

namespace Gamecon;

class Jidlo
{
    public const SNIDANE = 'snídaně';
    public const OBED    = 'oběd';
    public const VECERE  = 'večeře';

    public const PORADI_SNIDANE = 1;
    public const PORADI_OBED    = 2;
    public const PORADI_VECERE  = 3;

    public static function dejPoradiJidlaBehemDne(string $jidlo): ?int
    {
        $poradi = array_search(mb_strtolower($jidlo), self::dejJidlaBehemDne());
        return $poradi !== false
            ? $poradi
            : null;
    }

    public static function dejJidlaBehemDne(): array
    {
        return [self::SNIDANE, self::OBED, self::VECERE];
    }

    public static function jeToSnidane(string $jidlo): bool
    {
        return static::dejPoradiJidlaBehemDne($jidlo) === static::PORADI_SNIDANE;
    }

    public static function jeToObed(string $jidlo): bool
    {
        return static::dejPoradiJidlaBehemDne($jidlo) === static::PORADI_OBED;
    }

    public static function jeToVecere(string $jidlo): bool
    {
        return static::dejPoradiJidlaBehemDne($jidlo) === static::PORADI_VECERE;
    }
}
