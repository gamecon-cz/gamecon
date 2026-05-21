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
        // ADMIN must be checked before WEB: when URL_ADMIN is a sub-path of
        // URL_WEBU (e.g. https://2024.gamecon.cz/admin under
        // https://2024.gamecon.cz — the single-subdomain archive layout),
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
        if (!empty($_SERVER['REQUEST_URI'])) {
            throw new \LogicException('Nelze rozpoznat kontext zobrazení');
        }
        return self::CLI;
    }
}
