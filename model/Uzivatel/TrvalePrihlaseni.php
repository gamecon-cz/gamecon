<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

/**
 * Trvalé přihlášení („Trvale přihlásit"), jeden řádek na zařízení, takže přihlášení na dalším
 * zařízení nikoho neodhlásí.
 *
 * Cookie nese stálý selektor řádku, pořadí výměny tokenu a náhodný token, DB jen SHA-256 selektoru a tokenu:
 * kdo přečte databázi, nepřihlásí se a nemá ani selektor, kterým by někoho odhlásil.
 * Každé přihlášení z cookie token vymění. Kdo pak přijde se selektorem a tokenem vyměněným víckrát zpět, nese kopii
 * cookie, kterou mezitím použil někdo jiný: uživatel je odhlášen všude, viz {@see self::uplatniCookie()}.
 *
 * Běžící session ukončuje razítko přihlášení v {@see \Uzivatel::zSession()}.
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
     * Selektor, počet výměn tokenu a token v cookie. Cookie z doby před selektorem (převedená migrací)
     * nese jen token.
     */
    private const string ODDELOVAC = '.';

    /** Selektor se hledá v DB, cokoli jiného než náš selektor se tam nedostane. */
    private const string VZOR_SELEKTORU = '/^[0-9a-f]{64}$/D';

    private const string VZOR_POCTU_VYMEN = '/^(0|[1-9]\d{0,8})$/D';

    private const string STAV_AKTUALNI = 'aktualni';

    /** Token vyměněný před chvílí, nese ho souběžný požadavek. */
    private const string STAV_PREDCHOZI = 'predchozi';

    /**
     * Token vyměněný jednou zpět, ale ne před chvílí. Nese ho prohlížeč, kterému se ztratila odpověď s výměnou
     * (karta zavřená před odpovědí, zabitý mobilní prohlížeč, požadavek dlouho ve frontě), nebo kopie cookie:
     * nový token zatím nikdo nepoužil, takže se to nepozná.
     */
    private const string STAV_ZASTARALY = 'zastaraly';

    /**
     * Token vyměněný víckrát zpět, u řádku, který existuje: nový token už někdo použil, cookie tedy nesou dva.
     */
    private const string STAV_ZNEUZITY = 'zneuzity';

    /**
     * Cookie s víc výměnami tokenu, než má její řádek: databáze je obnovená ze zálohy starší než cookie.
     * Kopie cookie je naopak starší než řádek.
     */
    private const string STAV_NOVEJSI_NEZ_RADEK = 'novejsi_nez_radek';

    /**
     * Cookie, která v tomto požadavku žádný řádek nenašla v prázdné tabulce, a proto v prohlížeči zůstala.
     * Uzivatel::zSession() se za požadavek volá víckrát, bez tohohle by se nepřihlášený ptal databáze pokaždé znovu.
     */
    private static ?string $cookieBezRadku = null;

    /**
     * Zapomene, co si třída pamatuje jen po dobu požadavku. Pro proces, který obslouží víc požadavků (testy).
     */
    public static function zapomenStavPozadavku(): void
    {
        self::$cookieBezRadku = null;
    }

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

        $selektor = self::novyToken();
        $token = self::novyToken();
        // Heslo se ověřovalo podle řádku přečteného dřív. Kdyby mezitím změna hesla nebo odhlášení ostatních
        // zařízení smazala trvalá přihlášení, nový řádek by je přežil. Zamykající čtení řádku uživatele počká,
        // dokud změna razítka nedoběhne, a pak vidí to nové, viz Uzivatel::zmenRazitkoPrihlaseni(). Bez
        // LOCK IN SHARE MODE by INSERT … SELECT pod READ COMMITTED četl snímek bez zámku a vzal razítko staré.
        $vlozeno = dbQuery(<<<'SQL'
            INSERT INTO uzivatele_trvala_prihlaseni (id_uzivatele, selektor_hash, token_hash)
            SELECT id_uzivatele, $3, $1
            FROM uzivatele_hodnoty
            WHERE id_uzivatele = $0 AND razitko_prihlaseni <=> $2
            LOCK IN SHARE MODE
            SQL, [0 => $idUzivatele, 1 => self::hash($token), 2 => $razitko, 3 => self::hash($selektor)]);
        if (dbAffectedOrNumRows($vlozeno) !== 1) {
            return null;
        }
        $id = (int) dbInsertId();
        self::nastavCookie($selektor, 0, $token);

        return $id;
    }

    /**
     * Přihlášení cookie tohoto prohlížeče. Platný token zároveň vymění za nový.
     *
     * Cookie, která žádný řádek nenajde (zrušený odhlášením, vypršelý), z prohlížeče zmizí, jinak by se každý
     * požadavek ptal databáze ještě rok. V prohlížeči zůstane jen v prázdné tabulce: ta bývá chvíli prázdná,
     * třeba během kopie databáze na betě, a smazaná cookie by přihlášení zrušila napořád.
     *
     * Cookie, jejíž řádek existuje, ale token je vyměněný víckrát zpět, nese jen ukradená nebo zapomenutá kopie,
     * a kdo z těch dvou token vyměnil první, se nepozná. Uživatel je proto odhlášen všude, i z běžící session toho
     * druhého, viz {@see self::odhlasPoZneuziti()}.
     *
     * Token vyměněný jen jednou zpět, a ne před chvílí, nese nejspíš prohlížeč, kterému se ztratila odpověď
     * s výměnou. Takové trvalé přihlášení jen skončí s varováním, ostatní zařízení zůstanou přihlášená,
     * viz {@see self::ukonciZastarale()}.
     *
     * Cookie novější než její řádek (databáze obnovená ze zálohy) kopie není: jen zmizí z prohlížeče, token
     * řádku už nikdo nenese. Řádek zůstane, dokud nevyprší, takže taková cookie nikomu nic nezruší.
     *
     * Když už cookie nejde poslat (výstup začal), nepřihlásí: nový token, token pro e-shop ani smazání cookie by
     * do prohlížeče nedošly. Přihlásí další požadavek.
     *
     * @return array{id: int, id_uzivatele: int, razitko: string|null}|null id trvalého přihlášení, koho
     *     přihlásit a jeho razítko přihlášení, přečtené stejným dotazem jako řádek: dokud řádek existuje,
     *     je razítko to staré, viz {@see \Uzivatel::zmenRazitkoPrihlaseni()}
     */
    public static function uplatniCookie(): ?array
    {
        $cookie = self::getCookie();
        if ($cookie === null
            || $cookie['hodnota'] === self::$cookieBezRadku
            || ! self::isCookieMoznePoslat()
        ) {
            return null;
        }
        $prihlaseni = self::najdi($cookie);
        if ($prihlaseni === null) {
            if (self::isTabulkaPrazdna()) {
                self::$cookieBezRadku = $cookie['hodnota'];
            } else {
                self::smazCookie();
            }

            return null;
        }
        if ($prihlaseni['stav'] === self::STAV_ZNEUZITY) {
            self::odhlasPoZneuziti($prihlaseni['id_uzivatele']);

            return null;
        }
        if ($prihlaseni['stav'] === self::STAV_ZASTARALY) {
            self::ukonciZastarale($prihlaseni['id'], $cookie);

            return null;
        }
        if ($prihlaseni['stav'] === self::STAV_NOVEJSI_NEZ_RADEK) {
            self::smazCookie();

            return null;
        }

        if ($prihlaseni['stav'] === self::STAV_AKTUALNI
            && ! self::vymenToken($prihlaseni, $cookie)
        ) {
            // Souběžný požadavek token mezitím vyměnil, nový se dá odvodit jen z jeho řádku. Když řádek
            // nenajde ani předchozím tokenem, mezitím ho zrušilo odhlášení a přečtený řádek už nepřihlásí.
            $prihlaseni = self::najdi($cookie);
            if ($prihlaseni === null || $prihlaseni['stav'] !== self::STAV_PREDCHOZI) {
                return null;
            }
        }
        if ($prihlaseni['stav'] === self::STAV_PREDCHOZI) {
            self::predejOdvozenyToken($cookie, $prihlaseni);
        }

        return [
            'id'           => $prihlaseni['id'],
            'id_uzivatele' => $prihlaseni['id_uzivatele'],
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
            self::smazCookie();
        }
    }

    /**
     * Toto zařízení pozná jen podle cookie, ne podle session: s ukradenou kopií cookie sdílí řádek i útočník,
     * a ten by v něm zůstal.
     *
     * Tomuto zařízení vydá nový selektor i token, které se z ničeho neodvodí. Kopie jeho cookie, třeba ta, kterou si
     * útočník odvodil z předchozího tokenu, tak přestane platit taky, a už nenajde ani řádek: zloděj se starou kopií
     * by jinak tohle zařízení odhlásil jako zneužité.
     *
     * Cookie jiného uživatele (po „Přihlásit se jako“ nese prohlížeč cookie toho, kdo se přepnul) toto zařízení
     * neurčuje: jeho řádek patří tomu druhému a tahle operace na něj nesahá.
     *
     * @return string|null nová cookie tohoto zařízení, null když žádné trvalé přihlášení nemá. Prohlížeči ji pošle až
     *     {@see self::posliCookie()} po commitu: po rollbacku by prohlížeč nesl cookie, která žádný řádek nemá.
     */
    public static function zrusNaOstatnichZarizenich(int $idUzivatele): ?string
    {
        $idTohoto = self::getIdZCookie($idUzivatele);
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE id_uzivatele = $0 AND id != $1
            SQL, [0 => $idUzivatele, 1 => $idTohoto ?? 0]);
        if ($idTohoto === null) {
            return null;
        }

        $novySelektor = self::novyToken();
        $novyToken = self::novyToken();
        dbQuery(<<<'SQL'
            UPDATE uzivatele_trvala_prihlaseni
            SET selektor_hash = $2,
                token_hash = $1,
                predchozi_token_hash = NULL,
                sul_tokenu = NULL,
                pocet_vymen = 0,
                vymeneno_unix = NULL,
                naposledy_pouzito = NOW()
            WHERE id = $0
            SQL, [0 => $idTohoto, 1 => self::hash($novyToken), 2 => self::hash($novySelektor)]);

        return self::sestavCookie($novySelektor, 0, $novyToken);
    }

    public static function zrusVsude(int $idUzivatele): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE id_uzivatele = $0
            SQL, [0 => $idUzivatele]);
    }

    /**
     * Cookie tu smaže taky: řádek existoval, o chvilkově prázdnou tabulku tedy nejde.
     *
     * Zrušit jen ten jeden řádek nestačí: kdo token vyměnil první, má z něj běžící session, a tu ukončí až
     * změna razítka. Ta zruší i trvalá přihlášení ostatních zařízení, majitel se přihlásí heslem.
     */
    private static function odhlasPoZneuziti(int $idUzivatele): void
    {
        \Uzivatel::zId($idUzivatele)?->zmenRazitkoPrihlaseni(ponechatTotoZarizeni: false);
        self::smazCookie();
        \Chyba::nastav(
            'Tvoje trvalé přihlášení mezitím použil jiný prohlížeč, proto jsme tě pro jistotu odhlásili na všech '
            . 'zařízeních. Pokud jsi to nebyl ty, přihlas se a změň si heslo.',
            \Chyba::VAROVANI,
        );
    }

    /**
     * Kdo nese token o jednu výměnu pozadu, se nepozná: prohlížeč, kterému se ztratila odpověď s výměnou, nebo kopie
     * cookie. Skončí proto jen tohle trvalé přihlášení, ať nový token nese kdokoli, a majitel se přihlásí heslem.
     * Odhlásit všude jako po zneužití by kvůli zavřené kartě odhlásilo i ostatní zařízení; běžící session toho, kdo
     * token vyměnil, tak skončí až sama, nebo s odhlášením ostatních zařízení. Proto varování: bez něj by majitel,
     * kterého předběhl zloděj, vypadal jen odhlášený a neměl důvod ostatní zařízení odhlásit.
     *
     * Řádek smaže, jen když je pořád ve stavu, který přečetl {@see self::najdi()}: „Odhlásit ostatní zařízení“
     * mu mezitím mohlo dát nový selektor i token právě proto, aby staré kopie přestaly platit, a ten by stará kopie
     * jinak smazala.
     *
     * @param array{token: string} $cookie cookie s tokenem o jednu výměnu pozadu
     */
    private static function ukonciZastarale(int $id, array $cookie): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM uzivatele_trvala_prihlaseni
            WHERE id = $0 AND predchozi_token_hash = $1
            SQL, [0 => $id, 1 => self::hash($cookie['token'])]);
        self::smazCookie();
        \Chyba::nastav(
            'Trvalé přihlášení v tomto prohlížeči skončilo, přihlas se prosím znovu. Obvykle se to stane, když '
            . 'zavřeš stránku během načítání. Mohl ho ale použít i jiný prohlížeč: jestli to nečekáš, odhlas po '
            . 'přihlášení v Nastavení ostatní zařízení.',
            \Chyba::VAROVANI,
        );
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

    /**
     * Cookie, jejíž token k řádku nesedí, toto zařízení neurčuje: starou kopii může nést kdokoli a řádek
     * patří majiteli.
     *
     * @param int|null $idUzivatele jen trvalé přihlášení tohoto uživatele, null kohokoli
     */
    private static function getIdZCookie(?int $idUzivatele = null): ?int
    {
        $cookie = self::getCookie();
        if ($cookie === null) {
            return null;
        }
        $prihlaseni = self::najdi($cookie);

        return $prihlaseni !== null
            && ($idUzivatele === null || $prihlaseni['id_uzivatele'] === $idUzivatele)
            && in_array($prihlaseni['stav'], [self::STAV_AKTUALNI, self::STAV_PREDCHOZI], true)
            ? $prihlaseni['id']
            : null;
    }

    /**
     * Tabulka bez jediného řádku: třeba během kopie databáze na betě, ne po odhlášení.
     */
    private static function isTabulkaPrazdna(): bool
    {
        return dbOneCol(<<<'SQL'
            SELECT 1 FROM uzivatele_trvala_prihlaseni LIMIT 1
            SQL) === null;
    }

    /**
     * Trvalé přihlášení, ke kterému patří cookie, a co o něm její token říká (stav). Řádek se hledá hashem
     * selektoru, token se porovná až v PHP: i špatný token u existujícího řádku je zpráva, že cookie nesou dva,
     * ledaže je cookie novější než řádek ({@see self::STAV_NOVEJSI_NEZ_RADEK}) nebo jen o jednu výměnu pozadu
     * ({@see self::STAV_ZASTARALY}).
     *
     * Cookie bez selektoru (převedená migrací) najde řádek jen tokenem, aktuálním nebo předchozím, zneužití se
     * u ní proto nepozná; první výměna dá řádku selektor.
     *
     * Předchozí token přihlásí jen chvíli po výměně: cookie mohla přijít ze souběžného požadavku. Chvíle se
     * měří v sekundách od epochy, ne v místním čase: ten při změně letního času skočí o hodinu.
     *
     * @param array{selektor: string|null, pocet_vymen: int, token: string} $cookie
     *
     * @return array{id: int, id_uzivatele: int, selektor_hash: string|null, token_hash: string,
     *     sul_tokenu: string|null, pocet_vymen: int, razitko: string|null, stav: string}|null
     */
    private static function najdi(array $cookie): ?array
    {
        $hash = self::hash($cookie['token']);
        $prihlaseni = dbOneLine(<<<'SQL'
            SELECT uzivatele_trvala_prihlaseni.id,
                uzivatele_trvala_prihlaseni.id_uzivatele,
                uzivatele_trvala_prihlaseni.selektor_hash,
                uzivatele_trvala_prihlaseni.token_hash,
                uzivatele_trvala_prihlaseni.predchozi_token_hash,
                uzivatele_trvala_prihlaseni.sul_tokenu,
                uzivatele_trvala_prihlaseni.pocet_vymen,
                uzivatele_trvala_prihlaseni.vymeneno_unix >= UNIX_TIMESTAMP() - $3 AS predchozi_token_plati,
                uzivatele_hodnoty.razitko_prihlaseni AS razitko
            FROM uzivatele_trvala_prihlaseni
            JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = uzivatele_trvala_prihlaseni.id_uzivatele
            WHERE uzivatele_trvala_prihlaseni.naposledy_pouzito >= NOW() - INTERVAL $2 DAY
                AND (
                    uzivatele_trvala_prihlaseni.selektor_hash = $0
                    OR uzivatele_trvala_prihlaseni.token_hash = $1
                    OR uzivatele_trvala_prihlaseni.predchozi_token_hash = $1
                )
            LIMIT 1
            SQL, [
            0 => $cookie['selektor'] === null
                ? null
                : self::hash($cookie['selektor']),
            // NULL se ničemu nerovná: cookie se selektorem hledá jen jím, cookie bez něj jen tokenem.
            1 => $cookie['selektor'] === null
                ? $hash
                : null,
            2 => self::PLATNOST_DNU,
            3 => self::PREDCHOZI_TOKEN_PLATI_SEKUND,
        ]);
        if ($prihlaseni === []) {
            return null;
        }
        $stav = match (true) {
            $prihlaseni['token_hash'] === $hash => self::STAV_AKTUALNI,
            $prihlaseni['predchozi_token_hash'] === $hash => (bool) $prihlaseni['predchozi_token_plati']
                ? self::STAV_PREDCHOZI
                : self::STAV_ZASTARALY,
            $cookie['selektor'] !== null
                && $cookie['pocet_vymen'] > (int) $prihlaseni['pocet_vymen'] => self::STAV_NOVEJSI_NEZ_RADEK,
            default                                            => self::STAV_ZNEUZITY,
        };

        return [
            'id'            => (int) $prihlaseni['id'],
            'id_uzivatele'  => (int) $prihlaseni['id_uzivatele'],
            'selektor_hash' => $prihlaseni['selektor_hash'],
            'token_hash'    => $prihlaseni['token_hash'],
            'sul_tokenu'    => $prihlaseni['sul_tokenu'],
            'pocet_vymen'   => (int) $prihlaseni['pocet_vymen'],
            'razitko'       => $prihlaseni['razitko'],
            'stav'          => $stav,
        ];
    }

    /**
     * Nový token se odvodí z vyměněného a náhodné soli, kterou zná jen databáze. Souběžné požadavky
     * s vyměněným tokenem tak dostanou tentýž nový, viz {@see self::predejOdvozenyToken()}.
     *
     * Řádek převedený migrací tu dostane selektor, odvozený stejně jako token: DB drží jen jeho hash
     * a souběžný požadavek se starou cookie by ho jinak neměl odkud vzít.
     *
     * @param array{id: int, pocet_vymen: int}            $prihlaseni
     * @param array{selektor: string|null, token: string} $cookie
     *
     * @return bool false, když token mezitím vyměnil souběžný požadavek
     */
    private static function vymenToken(array $prihlaseni, array $cookie): bool
    {
        $token = $cookie['token'];
        $sul = self::novyToken();
        $selektor = $cookie['selektor'] ?? self::odvodSelektor($token, $sul);
        $novyToken = self::odvodToken($token, $sul);
        // Přiřazení jdou zleva, předchozí hash tedy dostane ještě ten starý.
        $vymena = dbQuery(<<<'SQL'
            UPDATE uzivatele_trvala_prihlaseni
            SET predchozi_token_hash = token_hash,
                token_hash = $1,
                sul_tokenu = $2,
                selektor_hash = $4,
                pocet_vymen = pocet_vymen + 1,
                vymeneno_unix = UNIX_TIMESTAMP(),
                naposledy_pouzito = NOW()
            WHERE id = $0 AND token_hash = $3
            SQL, [
            0 => $prihlaseni['id'],
            1 => self::hash($novyToken),
            2 => $sul,
            3 => self::hash($token),
            4 => self::hash($selektor),
        ]);
        if (dbAffectedOrNumRows($vymena) !== 1) {
            return false;
        }
        // Počet výměn se mění jen s tokenem, a ten podmínka dotazu ověřila: přečtený počet tedy platil.
        self::nastavCookie($selektor, $prihlaseni['pocet_vymen'] + 1, $novyToken);

        return true;
    }

    /**
     * Požadavku s předchozím tokenem pošle tentýž nový, jaký dostal ten, kdo token vyměnil. Prohlížeč pak
     * nový token nese, ať dorazí kterákoli odpověď, i když tu s výměnou zahodil (karta zavřená během
     * obnovení, výpadek sítě).
     *
     * @param array{selektor: string|null, token: string} $cookie cookie s předchozím tokenem
     * @param array{selektor_hash: string|null, token_hash: string, sul_tokenu: string|null,
     *     pocet_vymen: int} $prihlaseni
     */
    private static function predejOdvozenyToken(array $cookie, array $prihlaseni): void
    {
        if ($prihlaseni['sul_tokenu'] === null) {
            return;
        }
        $novyToken = self::odvodToken($cookie['token'], $prihlaseni['sul_tokenu']);
        $selektor = $cookie['selektor'] ?? self::odvodSelektor($cookie['token'], $prihlaseni['sul_tokenu']);
        if (self::hash($novyToken) === $prihlaseni['token_hash']
            && self::hash($selektor) === $prihlaseni['selektor_hash']
        ) {
            self::nastavCookie($selektor, $prihlaseni['pocet_vymen'], $novyToken);
        }
    }

    private static function odvodToken(string $predchoziToken, string $sul): string
    {
        return hash_hmac('sha256', $sul, $predchoziToken);
    }

    /**
     * Selektor řádku převedeného migrací. Jiná zpráva než u tokenu, aby z jednoho nešlo spočítat druhé.
     */
    private static function odvodSelektor(string $predchoziToken, string $sul): string
    {
        return hash_hmac('sha256', 'selektor' . $sul, $predchoziToken);
    }

    /**
     * Cokoli jiného než selektor, počet výměn a token v našem tvaru se bere celé jako token z doby před selektorem:
     * ten se jen hashuje, takže do dotazu se z cookie nedostane nic, co by vypadalo jako číslo (dbQv() ho
     * nedává do uvozovek a MariaDB by s ním porovnala selektory jako čísla, tedy našla cizí řádky).
     *
     * @return array{hodnota: string, selektor: string|null, pocet_vymen: int, token: string}|null selektor null
     *     u cookie z doby před selektorem, null u cookie, která chybí
     */
    private static function getCookie(): ?array
    {
        $hodnota = $_COOKIE[self::COOKIE] ?? null;
        if (! is_string($hodnota) || $hodnota === '') {
            return null;
        }
        $casti = explode(self::ODDELOVAC, $hodnota);
        if (count($casti) === 3
            && $casti[2] !== ''
            && preg_match(self::VZOR_SELEKTORU, $casti[0]) === 1
            && preg_match(self::VZOR_POCTU_VYMEN, $casti[1]) === 1
        ) {
            return ['hodnota' => $hodnota, 'selektor' => $casti[0], 'pocet_vymen' => (int) $casti[1], 'token' => $casti[2]];
        }

        return ['hodnota' => $hodnota, 'selektor' => null, 'pocet_vymen' => 0, 'token' => $hodnota];
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function novyToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function nastavCookie(string $selektor, int $pocetVymen, string $token): void
    {
        self::posliCookie(self::sestavCookie($selektor, $pocetVymen, $token));
    }

    private static function sestavCookie(string $selektor, int $pocetVymen, string $token): string
    {
        return implode(self::ODDELOVAC, [$selektor, $pocetVymen, $token]);
    }

    /**
     * Po začátku výstupu už hlavička s cookie neodejde. V CLI žádný prohlížeč není a hlavičky tam
     * „odešly“ s prvním výpisem (testy), cookie tam žije jen v $_COOKIE.
     */
    private static function isCookieMoznePoslat(): bool
    {
        return PHP_SAPI === 'cli' || ! headers_sent();
    }

    private static function smazCookie(): void
    {
        self::posliCookie('');
    }

    /**
     * Do $_COOKIE taky, aby další volání v tomtéž požadavku pracovala s cookie, kterou prohlížeč
     * od teď nese. Prázdná hodnota cookie smaže.
     *
     * Bez domény, tedy jen pro tento host: session cookie platí pro celou doménu, i pro betu, preview
     * a archivy, a token tam nemá co dělat. Secure podle session cookie, ta ví, kdy jde o HTTPS.
     *
     * @param string $hodnota cookie, jejíž řádek je commitnutý, třeba z {@see self::zrusNaOstatnichZarizenich()}
     */
    public static function posliCookie(string $hodnota): void
    {
        if ($hodnota === '') {
            unset($_COOKIE[self::COOKIE]);
        } else {
            $_COOKIE[self::COOKIE] = $hodnota;
        }
        setcookie(self::COOKIE, $hodnota, [
            'expires'  => $hodnota === ''
                ? 0
                : time() + self::PLATNOST_DNU * 24 * 3600,
            'path'     => '/',
            'secure'   => session_get_cookie_params()['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
