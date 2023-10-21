<?php

declare(strict_types=1);

namespace Gamecon\Shop;

enum KontextZobrazeni
{
    case WEB;
    case ADMIN;
    case CLI;

    public static function vytvorZGlobals(): self
    {
        $requestUrl = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        if (str_starts_with($requestUrl, URL_WEBU)) {
            return self::WEB;
        }
        if (str_starts_with($requestUrl, URL_ADMIN)) {
            return self::ADMIN;
        }
        if (!empty($_SERVER['REQUEST_URI'])) {
            throw new \LogicException('Nelze rozpoznat kontext zobrazení');
        }
        return self::CLI;
    }
}
