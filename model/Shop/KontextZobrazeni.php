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
        $requestUrl = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off'
                ? 'http'
                : 'https') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        // ADMIN must be checked before WEB: when URL_ADMIN is a sub-path of
        // URL_WEBU (e.g. https://2025.gamecon.cz/admin under
        // https://2025.gamecon.cz — the single-subdomain archive layout),
        // URL_WEBU's str_starts_with would also match an admin request and
        // misclassify it as WEB. The longer/more-specific prefix has to win.
        // In the historical sub-subdomain layout (admin.YYYY vs YYYY) the
        // hosts differ, so either order works; flipping is safe.
        if (str_starts_with($requestUrl, URL_ADMIN)) {
            return self::ADMIN;
        }
        if (str_starts_with($requestUrl, URL_WEBU)) {
            return self::WEB;
        }
        throw new \LogicException('Nelze rozpoznat kontext zobrazení podle URL: ' . $requestUrl);
    }
}
