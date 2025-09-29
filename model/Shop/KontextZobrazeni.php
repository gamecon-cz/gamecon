<?php

declare(strict_types=1);

namespace Gamecon\Shop;

enum KontextZobrazeni: string
{
    case WEB   = 'web';
    case ADMIN = 'admin';
    case CLI   = 'cli';

    public static function vytvorZGlobals(): self
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            if (php_sapi_name() === 'cli') {
                return self::CLI;
            }
            throw new \LogicException('Nelze rozpoznat kontext zobrazení');
        }
        $requestUrl = (empty($_SERVER['HTTPS'])
                ? 'http'
                : 'https') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        if (str_starts_with($requestUrl, URL_WEBU)) {
            return self::WEB;
        }
        if (str_starts_with($requestUrl, URL_ADMIN)) {
            return self::ADMIN;
        }
        throw new \LogicException('Nelze rozpoznat kontext zobrazení podle URL: ' . $requestUrl);
    }
}
