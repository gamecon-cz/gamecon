<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Web\QrOdkaz;
use Random\Randomizer;

/**
 * Přihlášení QR kódem. Zařízení, které se přihlašuje, ukáže QR kód s odkazem na stránku schválení a číslo.
 * Uživatel odkaz otevře na telefonu, kde je přihlášený na webu (fotoaparátem nebo čtečkou v Nastavení), vybere
 * stejné číslo a přihlášení schválí. Zařízení se mezitím ptá ({@see self::zjistiStav()}) a po schválení se přihlásí
 * za toho, kdo schválil.
 *
 * Kód z odkazu jen najde žádost. Přihlásit se může jen session, která žádost založila, takže kdo QR kód vyfotí
 * přes rameno, k cizímu účtu se nedostane.
 *
 * Číslo hlídá, že uživatel schvaluje zařízení, které má před sebou: kdo má jen odkaz (z historie prohlížeče,
 * přeposlaný, vyfocený bez obrazovky), trefí správné číslo jednou ze tří a špatné žádost zamítne. Dvě další čísla
 * jsou náhodná a uložená v databázi, z kódu se správné nepozná. Podvod, kdy útočník oběti pošle QR kód ze své
 * obrazovky i s číslem („naskenuj a vyber 42“), číslo nezastaví: proti němu je jen varování na stránce schválení.
 *
 * Žádosti webu a adminu jsou v session zvlášť: session je pro oba hosty společná, ale trvalé přihlášení dává
 * cookie jen hostu, na kterém se zařízení přihlásí, a „Trvale přihlásit“ má na webu jinou výchozí volbu než v adminu.
 *
 * Čas měří databáze v sekundách od epochy, ne v místním čase: ten při změně letního času skočí o hodinu.
 *
 * @see docs/generated/prihlaseni-qr-kodem.md
 */
final class PrihlaseniQrKodem
{
    /**
     * Stránka webu, na kterou vede odkaz v QR kódu.
     */
    public const string STRANKA_SCHVALENI = 'prihlasit-zarizeni';

    public const string PARAMETR_KODU = 'kod';

    /**
     * Parametr přihlašovací stránky webu s kódem, na jehož schválení se po přihlášení vrátit. Stránka schválení
     * tam posílá toho, kdo na telefonu přihlášený není.
     */
    public const string PARAMETR_SCHVALIT_PO_PRIHLASENI = 'schvalitKod';

    /**
     * Jak dlouho jde kód schválit. Kdo to nestihne, vygeneruje nový jedním tlačítkem.
     */
    public const int PLATNOST_SEKUND = 120;

    /**
     * Kolik kódů vygeneruje jeden rozsah IP za OKNO_MINUT. Velkoryse, za jednou IP může být celá síť
     * (wifi na festivalu, infopult). Brání jen plnění tabulky: kdo se do limitu nevejde, přihlásí se heslem.
     */
    public const int MAX_ZADOSTI_Z_IP = 300;

    /**
     * Kolik kódů vygeneruje jedna session za OKNO_MINUT, aby opakované „Nový kód“ jednoho člověka nevyčerpalo
     * limit celé sítě.
     */
    public const int MAX_ZADOSTI_ZE_SESSION = 10;

    public const int OKNO_MINUT = 10;

    public const string STAV_CEKA = 'ceka';

    public const string STAV_PRIHLASENO = 'prihlaseno';

    public const string STAV_ZAMITNUTO = 'zamitnuto';

    public const string STAV_VYPRSELO = 'vyprselo';

    /**
     * Prohlížeč žádnou žádost nemá: zrušil ji, nebo mu vypršela session.
     */
    public const string STAV_ZADNA = 'zadna';

    /**
     * Schválená žádost, kterou zařízení ještě neuplatnilo.
     */
    public const string STAV_SCHVALENO = 'schvaleno';

    public const string SCHVALENO = 'schvaleno';

    public const string SPATNE_CISLO = 'spatne_cislo';

    public const string NEPLATNY_KOD = 'neplatny_kod';

    /**
     * Jak dlouho po schválení se zařízení ještě přihlásí. Ptá se každé 2 s, karta na pozadí ale méně často.
     */
    private const int DOKONCENI_SEKUND = 120;

    /**
     * Na dohledání, kdo komu přihlášení schválil, to stačí, a IP adresy nezůstanou v databázi napořád.
     */
    private const int UCHOVAVAT_DNI = 30;

    /**
     * Kolik starých žádostí smaže jedna nová.
     */
    private const int UKLID_NAJEDNOU = 100;

    private const int NEJMENSI_CISLO = 10;

    private const int NEJVETSI_CISLO = 99;

    private const string SESSION_KLIC_WEB = 'prihlaseni_qr';

    private const string SESSION_KLIC_ADMIN = 'prihlaseni_qr_admin';

    /**
     * Časy založení žádostí této session pro MAX_ZADOSTI_ZE_SESSION, společné webu i adminu.
     */
    private const string SESSION_KLIC_ZALOZENI = 'prihlaseni_qr_zalozeni';

    /**
     * 16 náhodných bajtů v base64url. Kratší kód dá menší QR kód, který telefon přečte i z dálky.
     */
    private const string VZOR_KODU = '/^[A-Za-z0-9_-]{22}$/D';

    /**
     * Bez běžící session (CLI) si nic nepamatuje.
     *
     * @param bool $doAdminu žádost přihlašovací stránky adminu, jinak webu
     */
    public static function zSession(bool $doAdminu): self
    {
        if (! isset($_SESSION)) {
            $_SESSION = [];
        }

        return new self($_SESSION, $doAdminu);
    }

    /**
     * @param array<string, mixed> $session PHP session, odkazem, aby žádost přežila požadavek
     */
    public function __construct(
        private array &$session,
        private readonly bool $doAdminu,
    ) {
    }

    /**
     * Nová žádost tohoto prohlížeče. Předchozí, kterou ještě nikdo neschválil, zamítne, až když nová vznikne:
     * QR kód na obrazovce je jen jeden, a kdo narazí na limit, může dál použít ten, který má.
     *
     * Žádost se zapíše dřív, než se spočítá limit IP, a počítá se do něj i ona: souběžné žádosti tak vidí všechny,
     * které se zapsaly před nimi. Odmítnutá se hned smaže, aby vytrvalý bot nemohl tabulku plnit bez omezení.
     *
     * @param bool $trvale po schválení přihlásit trvale, jako zaškrtnuté „Trvale přihlásit“
     *
     * @return string|null proč žádost nevznikla, jako hláška pro uživatele; null, když vznikla
     */
    public function zaloz(string $ip, string $userAgent, bool $trvale): ?string
    {
        $odKdyPocitat = time() - self::OKNO_MINUT * 60;
        $zalozeni = array_values(array_filter(
            (array) ($this->session[self::SESSION_KLIC_ZALOZENI] ?? []),
            static fn (mixed $cas) => is_int($cas) && $cas >= $odKdyPocitat,
        ));
        if (count($zalozeni) >= self::MAX_ZADOSTI_ZE_SESSION) {
            return 'Vygeneroval jsi teď moc kódů. Zkus to za pár minut, nebo se přihlas heslem.';
        }
        $kod = self::vygenerujKod();
        $cislo = random_int(self::NEJMENSI_CISLO, self::NEJVETSI_CISLO);
        dbQuery(<<<'SQL'
            INSERT INTO prihlaseni_qr (kod_hash, cislo, nabidka_cisel, do_adminu, ip, prohlizec, vytvoreno_unix)
            VALUES ($0, $1, $2, $3, $4, $5, UNIX_TIMESTAMP())
            SQL, [
            0 => self::hash($kod),
            1 => $cislo,
            2 => implode(',', self::vylosujNabidkuCisel($cislo)),
            3 => (int) $this->doAdminu,
            4 => $ip,
            5 => mb_substr($userAgent, 0, 255),
        ]);
        $id = (int) dbInsertId();
        if ($this->isPrekrocenLimitIp($id)) {
            dbQuery(<<<'SQL'
                DELETE FROM prihlaseni_qr WHERE id = $0
                SQL, [0 => $id]);

            return 'Z tvé sítě teď přišlo moc žádostí o přihlášení QR kódem. Přihlas se heslem, nebo to zkus za pár minut.';
        }
        dbQuery(<<<'SQL'
            DELETE FROM prihlaseni_qr
            WHERE vytvoreno_unix < UNIX_TIMESTAMP() - $0
            LIMIT $1
            SQL, [0 => self::UCHOVAVAT_DNI * 24 * 3600, 1 => self::UKLID_NAJEDNOU]);
        $this->zrus();
        $this->session[$this->getSessionKlic()] = [
            'id'     => $id,
            'kod'    => $kod,
            'cislo'  => $cislo,
            'trvale' => $trvale,
        ];
        $zalozeni[] = time();
        $this->session[self::SESSION_KLIC_ZALOZENI] = $zalozeni;

        return null;
    }

    /**
     * Žádost tohoto prohlížeče, jak si ji pamatuje session, bez ohledu na to, jestli ještě platí.
     *
     * @return array{id: int, kod: string, cislo: int, trvale: bool}|null
     */
    public function getZadost(): ?array
    {
        $zadost = $this->session[$this->getSessionKlic()] ?? null;
        if (! is_array($zadost)
            || ! is_int($zadost['id'] ?? null)
            || ! is_string($zadost['kod'] ?? null)
            || ! is_int($zadost['cislo'] ?? null)
            || ! is_bool($zadost['trvale'] ?? null)
        ) {
            return null;
        }

        return $zadost;
    }

    /**
     * Žádost, kterou má ukázat přihlašovací stránka: čeká na schválení, nebo je schválená a zařízení ji ještě
     * neuplatnilo. Vypršelou nebo zamítnutou zapomene, aby se místo ní ukázal formulář s heslem.
     *
     * @return array{id: int, kod: string, cislo: int, trvale: bool}|null
     */
    public function getZivouZadost(): ?array
    {
        $zadost = $this->getZadost();
        if ($zadost === null) {
            return null;
        }
        $stav = $this->nactiStav($zadost['id']);
        if ($stav === self::STAV_CEKA || $stav === self::STAV_SCHVALENO) {
            return $zadost;
        }
        $this->zapomen();

        return null;
    }

    /**
     * QR kód s odkazem na stránku schválení jako data URI obrázku SVG, null bez žádosti.
     */
    public function getQrKodSvg(): ?string
    {
        $zadost = $this->getZadost();

        return $zadost === null
            ? null
            : (new QrOdkaz(self::getUrlSchvaleni($zadost['kod']), 'prihlaseni'))->svgDataUri();
    }

    public static function getUrlSchvaleni(string $kod): string
    {
        return URL_WEBU . '/' . self::STRANKA_SCHVALENI . '?' . http_build_query([self::PARAMETR_KODU => $kod]);
    }

    /**
     * Stránka schválení pro kód z parametru {@see self::PARAMETR_SCHVALIT_PO_PRIHLASENI}. Pro cokoli, co tvar kódu
     * nemá, null: jinam přihlášení nikoho neposílá.
     */
    public static function getUrlSchvaleniZParametru(mixed $kod): ?string
    {
        return is_string($kod) && preg_match(self::VZOR_KODU, $kod) === 1
            ? self::getUrlSchvaleni($kod)
            : null;
    }

    public static function getUrlPrihlaseniPredSchvalenim(string $kod): string
    {
        return URL_WEBU . '/prihlaseni?' . http_build_query([self::PARAMETR_SCHVALIT_PO_PRIHLASENI => $kod]);
    }

    /**
     * Zapomene žádost tohoto prohlížeče. Neschválenou zamítne, ať ji už nikdo neschvaluje.
     */
    public function zrus(): void
    {
        $zadost = $this->getZadost();
        $this->zapomen();
        if ($zadost === null) {
            return;
        }
        dbQuery(<<<'SQL'
            UPDATE prihlaseni_qr
            SET zamitnuto_unix = UNIX_TIMESTAMP()
            WHERE id = $0 AND schvaleno_unix IS NULL AND zamitnuto_unix IS NULL
            SQL, [0 => $zadost['id']]);
    }

    /**
     * Stav žádosti tohoto prohlížeče, na který se zařízení ptá. Schválenou rovnou dokončí: přihlásí toho, kdo ji
     * schválil. Vypršelou nebo zamítnutou zapomene, přihlašovací stránka pak ukáže formulář s heslem.
     *
     * @param bool $jePrihlasen prohlížeč už je přihlášený (třeba heslem v jiné kartě): žádost zapomene a stránka
     *                          se má obnovit
     *
     * @return string STAV_CEKA, STAV_PRIHLASENO, STAV_ZAMITNUTO, STAV_VYPRSELO nebo STAV_ZADNA
     */
    public function zjistiStav(bool $jePrihlasen): string
    {
        if ($jePrihlasen) {
            $this->zrus();

            return self::STAV_PRIHLASENO;
        }
        $zadost = $this->getZadost();
        if ($zadost === null) {
            return self::STAV_ZADNA;
        }
        $stav = $this->nactiStav($zadost['id']);
        if ($stav === self::STAV_SCHVALENO) {
            return $this->dokonci($zadost)
                ? self::STAV_PRIHLASENO
                : self::STAV_VYPRSELO;
        }
        if ($stav !== self::STAV_CEKA) {
            $this->zapomen();
        }

        return $stav;
    }

    /**
     * Odpověď API, na které se ptá přihlašovací stránka (soubory/prihlaseni-qr.js), společná webu i adminu.
     */
    public function odpovezStavem(bool $jePrihlasen): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        echo json_encode(['stav' => $this->zjistiStav($jePrihlasen)], JSON_THROW_ON_ERROR);
    }

    /**
     * @return string STAV_CEKA, STAV_SCHVALENO, STAV_ZAMITNUTO nebo STAV_VYPRSELO
     */
    private function nactiStav(int $idZadosti): string
    {
        $radek = dbOneLine(<<<'SQL'
            SELECT schvaleno_unix IS NOT NULL AS schvaleno,
                zamitnuto_unix IS NOT NULL AS zamitnuto,
                uplatneno_unix IS NOT NULL AS uplatneno,
                vytvoreno_unix >= UNIX_TIMESTAMP() - $2 AS plati
            FROM prihlaseni_qr
            WHERE id = $0 AND do_adminu = $1
            SQL, [0 => $idZadosti, 1 => (int) $this->doAdminu, 2 => self::PLATNOST_SEKUND]);

        return match (true) {
            $radek === []              => self::STAV_VYPRSELO,
            (bool) $radek['zamitnuto'] => self::STAV_ZAMITNUTO,
            (bool) $radek['schvaleno'] => (bool) $radek['uplatneno']
                ? self::STAV_VYPRSELO
                : self::STAV_SCHVALENO,
            (bool) $radek['plati']     => self::STAV_CEKA,
            default                    => self::STAV_VYPRSELO,
        };
    }

    /**
     * Uplatní schválení jen jednou. Požadavky jedné session PHP řadí za sebe (zámek session), druhý tedy žádost
     * v session už nenajde; podmínka dotazu to drží i bez zámku.
     *
     * @param array{id: int, trvale: bool} $zadost
     */
    private function dokonci(array $zadost): bool
    {
        $uplatneni = dbQuery(<<<'SQL'
            UPDATE prihlaseni_qr
            SET uplatneno_unix = UNIX_TIMESTAMP()
            WHERE id = $0
                AND uplatneno_unix IS NULL
                AND zamitnuto_unix IS NULL
                AND schvaleno_unix >= UNIX_TIMESTAMP() - $1
            SQL, [0 => $zadost['id'], 1 => self::DOKONCENI_SEKUND]);
        $this->zapomen();
        if (dbAffectedOrNumRows($uplatneni) !== 1) {
            return false;
        }
        $schvaleni = dbOneLine(<<<'SQL'
            SELECT id_uzivatele, razitko_prihlaseni FROM prihlaseni_qr WHERE id = $0
            SQL, [0 => $zadost['id']]);

        return \Uzivatel::prihlasSchvalenymZarizenim(
            (int) $schvaleni['id_uzivatele'],
            $schvaleni['razitko_prihlaseni'],
            $zadost['trvale'],
        ) !== null;
    }

    /**
     * Žádost pro stránku schválení podle kódu z odkazu.
     *
     * @return array{cisla: list<int>, do_adminu: bool, ip: string, prohlizec: string, pred_sekundami: int,
     *     stav: string}|null null u kódu, který žádnou žádost nenajde; stav je STAV_CEKA, STAV_SCHVALENO,
     *     STAV_ZAMITNUTO nebo STAV_VYPRSELO
     */
    public static function najdiProSchvaleni(string $kod): ?array
    {
        if (preg_match(self::VZOR_KODU, $kod) !== 1) {
            return null;
        }
        // Se znaménkem: rozdíl dvou čísel bez znaménka, který vyjde záporný (hodiny databáze skočily zpět),
        // MariaDB odmítne chybou.
        $radek = dbOneLine(<<<'SQL'
            SELECT nabidka_cisel, do_adminu, ip, prohlizec,
                CAST(UNIX_TIMESTAMP() AS SIGNED) - CAST(vytvoreno_unix AS SIGNED) AS pred_sekundami,
                schvaleno_unix IS NOT NULL AS schvaleno,
                zamitnuto_unix IS NOT NULL AS zamitnuto
            FROM prihlaseni_qr
            WHERE kod_hash = $0
            SQL, [0 => self::hash($kod)]);
        if ($radek === []) {
            return null;
        }
        $predSekundami = max(0, (int) $radek['pred_sekundami']);

        return [
            'cisla'          => array_map('intval', explode(',', $radek['nabidka_cisel'])),
            'do_adminu'      => (bool) $radek['do_adminu'],
            'ip'             => $radek['ip'],
            'prohlizec'      => self::getPopisProhlizece($radek['prohlizec']),
            'pred_sekundami' => $predSekundami,
            'stav'           => match (true) {
                (bool) $radek['zamitnuto']             => self::STAV_ZAMITNUTO,
                (bool) $radek['schvaleno']             => self::STAV_SCHVALENO,
                $predSekundami > self::PLATNOST_SEKUND => self::STAV_VYPRSELO,
                default                                => self::STAV_CEKA,
            },
        ];
    }

    /**
     * Schválí žádost za uživatele, který na telefonu vybral číslo. Špatné číslo žádost zamítne: druhým pokusem by
     * ze tří čísel uhodl skoro jistě.
     *
     * @param string|null $razitko razítko přihlášení session, která schvaluje ({@see \Uzivatel::getRazitkoPrihlaseniZSession()}).
     *                             Když se do přihlášení zařízení změní, zařízení se nepřihlásí.
     *
     * @return string SCHVALENO, SPATNE_CISLO, nebo NEPLATNY_KOD u kódu, který neexistuje, vypršel nebo už je vyřízený
     */
    public static function schval(string $kod, int $idUzivatele, ?string $razitko, int $vybraneCislo): string
    {
        if (preg_match(self::VZOR_KODU, $kod) !== 1) {
            return self::NEPLATNY_KOD;
        }
        $schvaleni = dbQuery(<<<'SQL'
            UPDATE prihlaseni_qr
            SET id_uzivatele = $1, razitko_prihlaseni = $2, schvaleno_unix = UNIX_TIMESTAMP()
            WHERE kod_hash = $0
                AND cislo = $3
                AND schvaleno_unix IS NULL
                AND zamitnuto_unix IS NULL
                AND vytvoreno_unix >= UNIX_TIMESTAMP() - $4
            SQL, [
            0 => self::hash($kod),
            1 => $idUzivatele,
            2 => $razitko,
            3 => $vybraneCislo,
            4 => self::PLATNOST_SEKUND,
        ]);
        if (dbAffectedOrNumRows($schvaleni) === 1) {
            return self::SCHVALENO;
        }

        return self::zamitniPlatnou($kod, $idUzivatele)
            ? self::SPATNE_CISLO
            : self::NEPLATNY_KOD;
    }

    /**
     * Zamítne žádost, kterou uživatel na telefonu nechce schválit.
     *
     * @return bool false u kódu, který neexistuje, vypršel nebo už je vyřízený
     */
    public static function zamitni(string $kod, int $idUzivatele): bool
    {
        return preg_match(self::VZOR_KODU, $kod) === 1
            && self::zamitniPlatnou($kod, $idUzivatele);
    }

    private static function zamitniPlatnou(string $kod, int $idUzivatele): bool
    {
        $zamitnuti = dbQuery(<<<'SQL'
            UPDATE prihlaseni_qr
            SET id_uzivatele = $1, zamitnuto_unix = UNIX_TIMESTAMP()
            WHERE kod_hash = $0
                AND schvaleno_unix IS NULL
                AND zamitnuto_unix IS NULL
                AND vytvoreno_unix >= UNIX_TIMESTAMP() - $2
            SQL, [0 => self::hash($kod), 1 => $idUzivatele, 2 => self::PLATNOST_SEKUND]);

        return dbAffectedOrNumRows($zamitnuti) === 1;
    }

    /**
     * Tři čísla na výběr v náhodném pořadí, mezi nimi to správné. Ukládají se k žádosti: kdyby se s každým načtením
     * stránky měnila, správné by prozradilo to, které zůstává, a kdyby šla odvodit z kódu, prozradil by ho kód.
     *
     * @return list<int>
     */
    private static function vylosujNabidkuCisel(int $cislo): array
    {
        // výchozí generátor je kryptograficky bezpečný (Random\Engine\Secure)
        $nahoda = new Randomizer();
        $cisla = [$cislo];
        while (count($cisla) < 3) {
            $navnada = $nahoda->getInt(self::NEJMENSI_CISLO, self::NEJVETSI_CISLO);
            if (! in_array($navnada, $cisla, true)) {
                $cisla[] = $navnada;
            }
        }

        return $nahoda->shuffleArray($cisla);
    }

    /**
     * Prohlížeč a systém z User-Agent, jen pro poznání vlastního zařízení na stránce schválení.
     */
    public static function getPopisProhlizece(string $userAgent): string
    {
        $prohlizec = match (true) {
            str_contains($userAgent, 'Edg/'), str_contains($userAgent, 'EdgiOS/') => 'Edge',
            str_contains($userAgent, 'OPR/'), str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'SamsungBrowser/') => 'Samsung Internet',
            str_contains($userAgent, 'Firefox/'), str_contains($userAgent, 'FxiOS/') => 'Firefox',
            str_contains($userAgent, 'Chrome/'), str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };
        $system = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'CrOS') => 'ChromeOS',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
        $popis = implode(', ', array_filter([$prohlizec, $system]));

        return $popis !== ''
            ? $popis
            : 'neznámý prohlížeč';
    }

    /**
     * Počítá žádosti ze stejného rozsahu IP jako $idZadosti, i ji samotnou. Rozsah je generovaný sloupec
     * ip_rozsah, stejný jako u obnovy hesla ({@see ObnovaHeslaLog::jePrekrocenLimitIp()}).
     */
    private function isPrekrocenLimitIp(int $idZadosti): bool
    {
        $pocet = dbOneCol(<<<'SQL'
            SELECT COUNT(*)
            FROM prihlaseni_qr
            WHERE ip_rozsah = (SELECT ip_rozsah FROM prihlaseni_qr WHERE id = $0)
                AND vytvoreno_unix >= UNIX_TIMESTAMP() - $1
            SQL, [0 => $idZadosti, 1 => self::OKNO_MINUT * 60]);

        return (int) $pocet > self::MAX_ZADOSTI_Z_IP;
    }

    /**
     * Zapomene žádost v session, v databázi ji nechá, jak je.
     */
    private function zapomen(): void
    {
        unset($this->session[$this->getSessionKlic()]);
    }

    private function getSessionKlic(): string
    {
        return $this->doAdminu
            ? self::SESSION_KLIC_ADMIN
            : self::SESSION_KLIC_WEB;
    }

    private static function vygenerujKod(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    private static function hash(string $kod): string
    {
        return hash('sha256', $kod);
    }
}
