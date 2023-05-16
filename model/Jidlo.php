<?php declare(strict_types=1);

namespace Gamecon;

class Jidlo
{
    public const SNIDANE = 'snídaně';
    public const OBED    = 'oběd';
    public const VECERE  = 'večeře';

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
}
