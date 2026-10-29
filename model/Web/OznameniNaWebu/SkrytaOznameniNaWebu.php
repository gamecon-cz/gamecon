<?php

declare(strict_types=1);

namespace Gamecon\Web\OznameniNaWebu;

/**
 * Announcements a visitor closed. Most visitors are not logged in, so the browser remembers them (a cookie), like the look of the web.
 * Closing is a plain link handled by the server, so it works without JavaScript and the closed announcement never flashes.
 */
class SkrytaOznameniNaWebu
{
    /**
     * ?skrytOznameni=<klíč> closes the announcement and remembers it
     */
    public const string GET_KLIC = 'skrytOznameni';
    public const string COOKIE = 'skrytaOznameni';
    /**
     * Enough for a festival full of announcements, small enough for a cookie (a key has some 45 characters)
     */
    public const int MAX_ZAPAMATOVANYCH = 20;
    private const int PLATNOST_COOKIE_SEKUND = 365 * 24 * 3600;
    private const string ODDELOVAC = '.';

    /**
     * @var list<string>
     */
    private array $klice;

    /**
     * @param array<string, mixed> $cookies typically $_COOKIE
     */
    public function __construct(array $cookies)
    {
        $this->klice = self::rozloz((string) ($cookies[self::COOKIE] ?? ''));
    }

    public function jeSkryte(OznameniNaWebu $oznameni): bool
    {
        return $oznameni->typ->lzeSkryt()
               && in_array($oznameni->getKlicSkryti(), $this->klice, true);
    }

    /**
     * @param list<OznameniNaWebu> $oznameni
     *
     * @return list<OznameniNaWebu> those the visitor has not closed
     */
    public function neskryta(array $oznameni): array
    {
        return array_values(array_filter($oznameni, fn (OznameniNaWebu $jedno): bool => ! $this->jeSkryte($jedno)));
    }

    /**
     * Remembers an announcement closed by ?skrytOznameni=…, the oldest closed ones are forgotten beyond the limit.
     * A value that does not look like a key changes nothing.
     */
    public function zpracujSkryti(string $klic): bool
    {
        if (! self::jeKlic($klic)) {
            return false;
        }
        $klice = array_values(array_diff($this->klice, [$klic]));
        $klice[] = $klic;
        $this->klice = array_slice($klice, -self::MAX_ZAPAMATOVANYCH);
        setcookie(self::COOKIE, implode(self::ODDELOVAC, $this->klice), [
            'expires'  => time() + self::PLATNOST_COOKIE_SEKUND,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);

        return true;
    }

    /**
     * The URL to close the announcement from the current page: the current query with ?skrytOznameni=…
     *
     * @param array<string, mixed> $query typically $_GET, without the 'req' added by the rewrite
     */
    public static function getUrlSkryti(string $cesta, array $query, OznameniNaWebu $oznameni): string
    {
        $query[self::GET_KLIC] = $oznameni->getKlicSkryti();

        return $cesta . '?' . http_build_query($query);
    }

    /**
     * @return list<string>
     */
    private static function rozloz(string $cookie): array
    {
        return array_values(array_filter(
            explode(self::ODDELOVAC, $cookie),
            static fn (string $klic): bool => self::jeKlic($klic),
        ));
    }

    private static function jeKlic(string $klic): bool
    {
        return (bool) preg_match('~^\d+-[0-9a-f]{32}$~', $klic);
    }
}
