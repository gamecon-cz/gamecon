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

        return self::vytvorZUrl($requestUrl, URL_WEBU, URL_ADMIN);
    }

    /**
     * Admin is tried first: on previews and year archives it lives under the web's own host
     * (`https://2026.gamecon.cz/admin` under `https://2026.gamecon.cz`), so the web's address is a prefix of every
     * admin request too. On ostra and beta the hosts differ and the order makes no difference.
     */
    public static function vytvorZUrl(
        string $requestUrl,
        string $urlWebu,
        string $urlAdmin,
    ): self {
        if (self::jeNaAdrese($requestUrl, $urlAdmin)) {
            return self::ADMIN;
        }
        if (self::jeNaAdrese($requestUrl, $urlWebu)) {
            return self::WEB;
        }
        throw new \LogicException('Nelze rozpoznat kontext zobrazení podle URL: ' . $requestUrl);
    }

    /**
     * The address has to end where a path, a query or nothing follows, so `/administrace` on the web is not the admin
     * under `/admin` and `https://gamecon.cz.example.com` is not `https://gamecon.cz`.
     */
    private static function jeNaAdrese(
        string $requestUrl,
        string $adresa,
    ): bool {
        $adresa = rtrim($adresa, '/');

        return $requestUrl === $adresa
            || str_starts_with($requestUrl, $adresa . '/')
            || str_starts_with($requestUrl, $adresa . '?');
    }
}
