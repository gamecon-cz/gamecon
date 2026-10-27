<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

/**
 * Trvalé přihlášení („Trvale přihlásit"), jeden řádek na zařízení, takže přihlášení na dalším
 * zařízení nikoho neodhlásí.
 *
 * Cookie nese náhodný token, DB jen jeho SHA-256: kdo přečte databázi, nepřihlásí se. Každé
 * přihlášení z cookie token vymění, ukradená kopie tak platí jen do příští návštěvy majitele.
 *
 * Běžící session tahle třída neukončí, to dělá razítko přihlášení v {@see \Uzivatel::zSession()}.
 */
final class TrvalePrihlaseni
{
    public const string COOKIE = 'gcTrvalePrihlaseni';

    /** Stejně dlouho platí cookie. Řádek nepoužitý déle vyprší i v DB. */
    private const int PLATNOST_DNU = 365;

    /**
     * Karty obnovené po spuštění prohlížeče se ptají souběžně se stejnou cookie. Token vymění
     * jen první z nich, ostatní proto chvíli pustíme i s předchozím.
     */
    private const int PREDCHOZI_TOKEN_PLATI_SEKUND = 60;

    public static function zaloz(int $idUzivatele): void
    {
        // Prohlížeč nese jen jednu cookie, předchozí přihlášení by jinak zůstalo viset bez ní.
        self::smazTotoZarizeni();
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE naposledy_pouzito < NOW() - INTERVAL $0 DAY
            SQL, [0 => self::PLATNOST_DNU]);

        $token = self::novyToken();
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_trvala_prihlaseni (id_uzivatele, token_hash)
            VALUES ($0, $1)
            SQL, [0 => $idUzivatele, 1 => self::hash($token)]);
        self::nastavCookie($token, time() + self::PLATNOST_DNU * 24 * 3600);
    }

    /**
     * Id uživatele, kterého přihlásí cookie tohoto prohlížeče, nebo null. Platný token zároveň
     * vymění za nový.
     */
    public static function idUzivateleZCookie(): ?int
    {
        $hash = self::hashZCookie();
        if ($hash === null) {
            return null;
        }
        $prihlaseni = dbOneLine(<<<'SQL'
            SELECT id, id_uzivatele, token_hash = $0 AS je_aktualni
            FROM uzivatele_trvala_prihlaseni
            WHERE naposledy_pouzito >= NOW() - INTERVAL $1 DAY
                AND (
                    token_hash = $0
                    OR (predchozi_token_hash = $0 AND naposledy_pouzito >= NOW() - INTERVAL $2 SECOND)
                )
            SQL, [0 => $hash, 1 => self::PLATNOST_DNU, 2 => self::PREDCHOZI_TOKEN_PLATI_SEKUND]);
        if (! $prihlaseni) {
            return null;
        }

        if ($prihlaseni['je_aktualni']) {
            $novyToken = self::novyToken();
            // Přiřazení jdou zleva, předchozí hash tedy dostane ještě ten starý.
            $vymena = dbQuery(<<<'SQL'
                UPDATE uzivatele_trvala_prihlaseni
                SET predchozi_token_hash = token_hash,
                    token_hash = $1,
                    naposledy_pouzito = NOW()
                WHERE id = $0 AND token_hash = $2
                SQL, [0 => $prihlaseni['id'], 1 => self::hash($novyToken), 2 => $hash]);
            // Souběžný požadavek mohl token vyměnit dřív. Jeho cookie pak platí a tahle by ji přepsala neplatnou.
            if (dbAffectedOrNumRows($vymena) === 1) {
                self::nastavCookie($novyToken, time() + self::PLATNOST_DNU * 24 * 3600);
            }
        }

        return (int) $prihlaseni['id_uzivatele'];
    }

    public static function zrusNaTomtoZarizeni(): void
    {
        self::smazTotoZarizeni();
        if (isset($_COOKIE[self::COOKIE])) {
            self::nastavCookie('', 0);
        }
    }

    public static function zrusNaOstatnichZarizenich(int $idUzivatele): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE id_uzivatele = $0
                AND token_hash != $1
                AND (predchozi_token_hash IS NULL OR predchozi_token_hash != $1)
            SQL, [0 => $idUzivatele, 1 => self::hashZCookie() ?? '']);
    }

    public static function zrusVsude(int $idUzivatele): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE id_uzivatele = $0
            SQL, [0 => $idUzivatele]);
    }

    private static function smazTotoZarizeni(): void
    {
        $hash = self::hashZCookie();
        if ($hash === null) {
            return;
        }
        // I předchozím tokenem: cookie mohla přijít ze souběžného požadavku, který ho nevyměnil.
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE token_hash = $0 OR predchozi_token_hash = $0
            SQL, [0 => $hash]);
    }

    private static function hashZCookie(): ?string
    {
        $token = $_COOKIE[self::COOKIE] ?? null;

        return is_string($token) && $token !== ''
            ? self::hash($token)
            : null;
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function novyToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Do $_COOKIE taky, aby další volání v tomtéž požadavku pracovala s tokenem, který prohlížeč
     * od teď nese.
     */
    private static function nastavCookie(string $token, int $vyprsi): void
    {
        if ($token === '') {
            unset($_COOKIE[self::COOKIE]);
        } else {
            $_COOKIE[self::COOKIE] = $token;
        }
        setcookie(self::COOKIE, $token, [
            'expires'  => $vyprsi,
            'path'     => '/',
            'secure'   => ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
