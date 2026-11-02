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

    /**
     * @param int|null $idTohoto trvalé přihlášení, které si pamatuje session tohoto prohlížeče
     *
     * @return int id nového trvalého přihlášení
     */
    public static function zaloz(int $idUzivatele, ?int $idTohoto): int
    {
        // Prohlížeč nese jen jednu cookie, předchozí přihlášení by jinak zůstalo viset bez ní.
        self::smazTotoZarizeni($idTohoto);
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE naposledy_pouzito < NOW() - INTERVAL $0 DAY
            SQL, [0 => self::PLATNOST_DNU]);

        $token = self::novyToken();
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_trvala_prihlaseni (id_uzivatele, token_hash)
            VALUES ($0, $1)
            SQL, [0 => $idUzivatele, 1 => self::hash($token)]);
        $id = (int) dbInsertId();
        self::nastavCookie($token, time() + self::PLATNOST_DNU * 24 * 3600);

        return $id;
    }

    /**
     * Přihlášení cookie tohoto prohlížeče. Platný token zároveň vymění za nový.
     *
     * @return array{id: int, id_uzivatele: int, razitko: string}|null id trvalého přihlášení, koho přihlásit
     *     a jeho razítko přihlášení, přečtené stejným dotazem jako řádek: dokud řádek existuje, je razítko
     *     to staré, viz {@see \Uzivatel::zmenRazitkoPrihlaseni()}
     */
    public static function uplatniCookie(): ?array
    {
        $hash = self::hashZCookie();
        if ($hash === null) {
            return null;
        }
        try {
            $prihlaseni = dbOneLine(<<<'SQL'
                SELECT uzivatele_trvala_prihlaseni.id,
                    uzivatele_trvala_prihlaseni.id_uzivatele,
                    uzivatele_trvala_prihlaseni.token_hash = $0 AS je_aktualni,
                    uzivatele_hodnoty.random AS razitko
                FROM uzivatele_trvala_prihlaseni
                JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = uzivatele_trvala_prihlaseni.id_uzivatele
                WHERE uzivatele_trvala_prihlaseni.naposledy_pouzito >= NOW() - INTERVAL $1 DAY
                    AND (
                        uzivatele_trvala_prihlaseni.token_hash = $0
                        OR (
                            uzivatele_trvala_prihlaseni.predchozi_token_hash = $0
                            AND uzivatele_trvala_prihlaseni.naposledy_pouzito >= NOW() - INTERVAL $2 SECOND
                        )
                    )
                SQL, [0 => $hash, 1 => self::PLATNOST_DNU, 2 => self::PREDCHOZI_TOKEN_PLATI_SEKUND]);
        } catch (\DbException $dbException) {
            // Nasazení nahraje kód dřív, než migrace tabulku vytvoří. Stránka kvůli tomu nespadne.
            if ($dbException->getCode() !== 1146 /* table does not exist */) {
                throw $dbException;
            }

            return null;
        }
        if (! $prihlaseni) {
            // Zrušené nebo vypršelé. Prohlížeč by cookie posílal ještě rok a každý požadavek bez session by ji hledal.
            self::nastavCookie('', 0);

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

        return [
            'id'           => (int) $prihlaseni['id'],
            'id_uzivatele' => (int) $prihlaseni['id_uzivatele'],
            'razitko'      => (string) $prihlaseni['razitko'],
        ];
    }

    /**
     * @param int|null $idTohoto trvalé přihlášení, které si pamatuje session tohoto prohlížeče. Admin běží
     *                           na jiném hostu a cookie nevidí, bez tohohle by odhlášení v něm trvalé přihlášení nechalo.
     */
    public static function zrusNaTomtoZarizeni(?int $idTohoto): void
    {
        self::smazTotoZarizeni($idTohoto);
        if (isset($_COOKIE[self::COOKIE])) {
            self::nastavCookie('', 0);
        }
    }

    /**
     * Toto zařízení pozná jen podle cookie, ne podle session: s ukradenou kopií cookie sdílí řádek i útočník,
     * a ten by v něm zůstal.
     */
    public static function zrusNaOstatnichZarizenich(int $idUzivatele): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE id_uzivatele = $0 AND id != $1
            SQL, [0 => $idUzivatele, 1 => self::idZCookie() ?? 0]);
    }

    public static function zrusVsude(int $idUzivatele): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE id_uzivatele = $0
            SQL, [0 => $idUzivatele]);
    }

    private static function smazTotoZarizeni(?int $idTohoto): void
    {
        $idTohotoZarizeni = array_filter([self::idZCookie(), $idTohoto]);
        if ($idTohotoZarizeni === []) {
            return;
        }
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE id IN ($0)
            SQL, [0 => $idTohotoZarizeni]);
    }

    /**
     * Trvalé přihlášení, jehož token nese cookie tohoto prohlížeče. Předchozím tokenem jen chvíli po výměně:
     * cookie mohla přijít ze souběžného požadavku, ale starší kopie už je ukradená nebo zapomenutá.
     */
    private static function idZCookie(): ?int
    {
        $hash = self::hashZCookie();
        if ($hash === null) {
            return null;
        }
        $id = dbOneCol(<<<'SQL'
            SELECT id
            FROM uzivatele_trvala_prihlaseni
            WHERE token_hash = $0
                OR (predchozi_token_hash = $0 AND naposledy_pouzito >= NOW() - INTERVAL $1 SECOND)
            LIMIT 1
            SQL, [0 => $hash, 1 => self::PREDCHOZI_TOKEN_PLATI_SEKUND]);

        return $id !== null
            ? (int) $id
            : null;
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
     *
     * Bez domény, tedy jen pro tento host: session cookie platí pro celou doménu, i pro betu, preview
     * a archivy, a token tam nemá co dělat. Secure podle session cookie, ta ví, kdy jde o HTTPS.
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
            'secure'   => session_get_cookie_params()['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
