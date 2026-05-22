<?php
declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Vloží `uzivatel:heslo@` do URL pro HTTP Basic Auth.
 *
 * Caddy bránu před preview / archive prostředími chrání basic auth;
 * admin rozcestník vykresluje odkazy už s vloženými přihlašovacími údaji,
 * aby uživatel klikem rovnou prošel přes bránu.
 *
 * Jméno i heslo se URL-encodují (kvůli speciálním znakům), aby `@` nebo
 * `:` v hesle nerozbily strukturu URL.
 */
final class UrlWithBasicAuth
{
    public static function inject(string $url, string $user, string $password): string
    {
        if ($user === '' || $password === '') {
            return $url;
        }
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return $url;
        }
        $scheme   = $parts['scheme'] ?? 'https';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path     = $parts['path'] ?? '';
        $query    = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return sprintf(
            '%s://%s:%s@%s%s%s%s%s',
            $scheme,
            rawurlencode($user),
            rawurlencode($password),
            $parts['host'],
            $port,
            $path,
            $query,
            $fragment,
        );
    }
}
