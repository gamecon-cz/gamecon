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
     * Kolik vypršelých řádků smaže jedno trvalé přihlášení. Migrace jich naráz založila desetitisíce
     * se stejným časem, smazat je všechny by zdrželo přihlášení toho, na koho vyjdou.
     */
    private const int UKLID_NAJEDNOU = 100;

    /**
     * Karty obnovené po spuštění prohlížeče se ptají souběžně se stejnou cookie. Token vymění
     * jen první z nich, ostatní proto chvíli pustíme i s předchozím a pošleme jim tentýž nový token.
     */
    private const int PREDCHOZI_TOKEN_PLATI_SEKUND = 60;

    /**
     * @param string|null $razitko  razítko přihlášení, které platilo při ověření hesla. Změnilo-li se mezitím
     *                              (změna hesla, odhlášení ostatních zařízení), řádek nevznikne.
     * @param int|null    $idTohoto trvalé přihlášení, které si pamatuje session tohoto prohlížeče
     *
     * @return int|null id nového trvalého přihlášení, null když se razítko mezitím změnilo
     */
    public static function zaloz(int $idUzivatele, ?string $razitko, ?int $idTohoto): ?int
    {
        // Prohlížeč nese jen jednu cookie, předchozí přihlášení by jinak zůstalo viset bez ní.
        self::smazTotoZarizeni($idTohoto);
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE naposledy_pouzito < NOW() - INTERVAL $0 DAY
            LIMIT $1
            SQL, [0 => self::PLATNOST_DNU, 1 => self::UKLID_NAJEDNOU]);

        $token = self::novyToken();
        // Heslo se ověřovalo podle řádku přečteného dřív. Kdyby mezitím změna hesla nebo odhlášení ostatních
        // zařízení smazala trvalá přihlášení, nový řádek by je přežil. INSERT … SELECT na řádek uživatele
        // počká, dokud změna razítka nedoběhne, viz Uzivatel::zmenRazitkoPrihlaseni().
        $vlozeno = dbQuery(<<<'SQL'
            INSERT INTO uzivatele_trvala_prihlaseni (id_uzivatele, token_hash)
            SELECT id_uzivatele, $1
            FROM uzivatele_hodnoty
            WHERE id_uzivatele = $0 AND razitko_prihlaseni <=> $2
            SQL, [0 => $idUzivatele, 1 => self::hash($token), 2 => $razitko]);
        if (dbAffectedOrNumRows($vlozeno) !== 1) {
            return null;
        }
        $id = (int) dbInsertId();
        self::nastavCookie($token);

        return $id;
    }

    /**
     * Přihlášení cookie tohoto prohlížeče. Platný token zároveň vymění za nový.
     *
     * Cookie, která žádný řádek nenajde, v prohlížeči zůstane: tabulka může být jen chvíli prázdná,
     * třeba během kopie databáze na betě, a smazaná cookie by přihlášení zrušila napořád.
     *
     * @return array{id: int, id_uzivatele: int, razitko: string|null}|null id trvalého přihlášení, koho
     *     přihlásit a jeho razítko přihlášení, přečtené stejným dotazem jako řádek: dokud řádek existuje,
     *     je razítko to staré, viz {@see \Uzivatel::zmenRazitkoPrihlaseni()}
     */
    public static function uplatniCookie(): ?array
    {
        $token = self::getTokenZCookie();
        if ($token === null) {
            return null;
        }
        $prihlaseni = self::najdi($token);
        if ($prihlaseni === null) {
            return null;
        }

        $hash = self::hash($token);
        if ($prihlaseni['token_hash'] === $hash && ! self::vymenToken($prihlaseni['id'], $token)) {
            // Souběžný požadavek token mezitím vyměnil, nový se dá odvodit jen z jeho řádku.
            $prihlaseni = self::najdi($token) ?? $prihlaseni;
        }
        if ($prihlaseni['predchozi_token_hash'] === $hash) {
            self::predejOdvozenyToken($token, $prihlaseni);
        }

        return [
            'id'           => (int) $prihlaseni['id'],
            'id_uzivatele' => (int) $prihlaseni['id_uzivatele'],
            'razitko'      => $prihlaseni['razitko'],
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
            self::nastavCookie('');
        }
    }

    /**
     * Toto zařízení pozná jen podle cookie, ne podle session: s ukradenou kopií cookie sdílí řádek i útočník,
     * a ten by v něm zůstal.
     *
     * Tomuto zařízení vydá nový token, který se z ničeho neodvodí. Kopie jeho cookie, třeba ta, kterou si útočník
     * odvodil z předchozího tokenu, tak přestane platit taky.
     */
    public static function zrusNaOstatnichZarizenich(int $idUzivatele): void
    {
        $idTohoto = self::getIdZCookie();
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE id_uzivatele = $0 AND id != $1
            SQL, [0 => $idUzivatele, 1 => $idTohoto ?? 0]);
        if ($idTohoto === null) {
            return;
        }

        $novyToken = self::novyToken();
        dbQuery(<<<'SQL'
            UPDATE uzivatele_trvala_prihlaseni
            SET token_hash = $1,
                predchozi_token_hash = NULL,
                sul_tokenu = NULL,
                naposledy_pouzito = NOW()
            WHERE id = $0
            SQL, [0 => $idTohoto, 1 => self::hash($novyToken)]);
        self::nastavCookie($novyToken);
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
        $idTohotoZarizeni = array_filter([self::getIdZCookie(), $idTohoto]);
        if ($idTohotoZarizeni === []) {
            return;
        }
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE id IN ($0)
            SQL, [0 => $idTohotoZarizeni]);
    }

    private static function getIdZCookie(): ?int
    {
        $token = self::getTokenZCookie();

        return $token !== null
            ? self::najdi($token)['id'] ?? null
            : null;
    }

    /**
     * Trvalé přihlášení, jehož token nese cookie. Předchozím tokenem jen chvíli po výměně: cookie mohla přijít
     * ze souběžného požadavku, ale starší kopie už je ukradená nebo zapomenutá.
     *
     * @return array{id: int, id_uzivatele: int, token_hash: string, predchozi_token_hash: string|null,
     *     sul_tokenu: string|null, razitko: string|null}|null
     */
    private static function najdi(string $token): ?array
    {
        $prihlaseni = dbOneLine(<<<'SQL'
            SELECT uzivatele_trvala_prihlaseni.id,
                uzivatele_trvala_prihlaseni.id_uzivatele,
                uzivatele_trvala_prihlaseni.token_hash,
                uzivatele_trvala_prihlaseni.predchozi_token_hash,
                uzivatele_trvala_prihlaseni.sul_tokenu,
                uzivatele_hodnoty.razitko_prihlaseni AS razitko
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
            LIMIT 1
            SQL, [0 => self::hash($token), 1 => self::PLATNOST_DNU, 2 => self::PREDCHOZI_TOKEN_PLATI_SEKUND]);
        if ($prihlaseni === []) {
            return null;
        }
        $prihlaseni['id'] = (int) $prihlaseni['id'];

        return $prihlaseni;
    }

    /**
     * Nový token se odvodí z vyměněného a náhodné soli, kterou zná jen databáze. Souběžné požadavky
     * s vyměněným tokenem tak dostanou tentýž nový, viz {@see self::predejOdvozenyToken()}.
     *
     * @return bool false, když token mezitím vyměnil souběžný požadavek
     */
    private static function vymenToken(int $id, string $token): bool
    {
        $sul = self::novyToken();
        $novyToken = self::odvodToken($token, $sul);
        // Přiřazení jdou zleva, předchozí hash tedy dostane ještě ten starý.
        $vymena = dbQuery(<<<'SQL'
            UPDATE uzivatele_trvala_prihlaseni
            SET predchozi_token_hash = token_hash,
                token_hash = $1,
                sul_tokenu = $2,
                naposledy_pouzito = NOW()
            WHERE id = $0 AND token_hash = $3
            SQL, [0 => $id, 1 => self::hash($novyToken), 2 => $sul, 3 => self::hash($token)]);
        if (dbAffectedOrNumRows($vymena) !== 1) {
            return false;
        }
        self::nastavCookie($novyToken);

        return true;
    }

    /**
     * Požadavku s předchozím tokenem pošle tentýž nový, jaký dostal ten, kdo token vyměnil. Prohlížeč pak
     * nový token nese, ať dorazí kterákoli odpověď, i když tu s výměnou zahodil (karta zavřená během
     * obnovení, výpadek sítě).
     *
     * @param array{token_hash: string, sul_tokenu: string|null} $prihlaseni
     */
    private static function predejOdvozenyToken(string $predchoziToken, array $prihlaseni): void
    {
        if ($prihlaseni['sul_tokenu'] === null) {
            return;
        }
        $novyToken = self::odvodToken($predchoziToken, $prihlaseni['sul_tokenu']);
        if (self::hash($novyToken) === $prihlaseni['token_hash']) {
            self::nastavCookie($novyToken);
        }
    }

    private static function odvodToken(string $predchoziToken, string $sul): string
    {
        return hash_hmac('sha256', $sul, $predchoziToken);
    }

    private static function getTokenZCookie(): ?string
    {
        $token = $_COOKIE[self::COOKIE] ?? null;

        return is_string($token) && $token !== ''
            ? $token
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
     * od teď nese. Prázdný token cookie smaže.
     *
     * Bez domény, tedy jen pro tento host: session cookie platí pro celou doménu, i pro betu, preview
     * a archivy, a token tam nemá co dělat. Secure podle session cookie, ta ví, kdy jde o HTTPS.
     */
    private static function nastavCookie(string $token): void
    {
        if ($token === '') {
            unset($_COOKIE[self::COOKIE]);
        } else {
            $_COOKIE[self::COOKIE] = $token;
        }
        setcookie(self::COOKIE, $token, [
            'expires'  => $token === ''
                ? 0
                : time() + self::PLATNOST_DNU * 24 * 3600,
            'path'     => '/',
            'secure'   => session_get_cookie_params()['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
