<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use App\Service\JwtService;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\TrvalePrihlaseni;

/**
 * Zařízení je tu session a cookie, se kterými prohlížeč přijde na server. {@see self::pozadavek()}
 * je nastaví a po požadavku uloží, co v nich zůstalo.
 */
abstract class AbstractPrihlaseniNaZarizenichTestDb extends AbstractTestDb
{
    protected const string HESLO = 'heslo-trvale-prihlaseni';

    /**
     * Varování, která funkce session a cookie hlásí v CLI po prvním výstupu testů. Jiná varování
     * kódu pod testem jsou chyba.
     */
    private const string VZOR_VAROVANI_SESSION = '/^session_\w+\(\): |headers (have )?already (been )?sent/';

    private bool $sessionIdNastavenoTady = false;

    /**
     * @var array<mixed>|null null, když před testem nebyla
     */
    private ?array $sessionPredTestem = null;

    private ?string $cookiePredTestem = null;

    /**
     * Uživatelé z {@see self::vytvorUzivatele()}: řádky v DB vrátí rollback, uložené tokeny pro e-shop
     * (soubory) ne, a id se po obnově testovací DB opakují.
     *
     * @var list<int>
     */
    private array $vytvoreniUzivatele = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionPredTestem = $_SESSION ?? null;
        $this->cookiePredTestem = $_COOKIE[TrvalePrihlaseni::COOKIE] ?? null;
        TrvalePrihlaseni::zapomenStavPozadavku();
        // Funkce session v CLI po prvním výstupu testů hlásí varování, ale čtou $_SESSION i bez ní.
        if (session_id() === '') {
            session_id('trvale-prihlaseni-test');
            $this->sessionIdNastavenoTady = true;
        }
        // Hlášky z dřívějších testů, ať test vidí jen ty své.
        $this->vyzvedniHlasky();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->sessionPredTestem === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $this->sessionPredTestem;
            }
            if ($this->cookiePredTestem === null) {
                unset($_COOKIE[TrvalePrihlaseni::COOKIE]);
            } else {
                $_COOKIE[TrvalePrihlaseni::COOKIE] = $this->cookiePredTestem;
            }
            TrvalePrihlaseni::zapomenStavPozadavku();
            if ($this->sessionIdNastavenoTady) {
                session_id('');
            }
            $vytvoreniUzivatele = $this->vytvoreniUzivatele;
            $this->vytvoreniUzivatele = [];
            foreach ($vytvoreniUzivatele as $idUzivatele) {
                $this->getJwtService()->deleteToken($idUzivatele);
            }
        } finally {
            // Rollback změn testu v DB, ať úklid výše selže jakkoli.
            parent::tearDown();
        }
    }

    protected function getJwtService(): JwtService
    {
        return $this->getContainer()->get(JwtService::class);
    }

    protected function vytvorUzivatele(): int
    {
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_hodnoty SET
                login_uzivatele = $0,
                email1_uzivatele = $1,
                jmeno_uzivatele = 'Test',
                prijmeni_uzivatele = 'TrvalePrihlaseni',
                heslo_md5 = $2,
                random = ''
            SQL, [
            0 => 'test_trvale_' . uniqid('', true),
            1 => 'test.trvale.' . uniqid('', true) . '@example.org',
            // Slabší cena než výchozí: přihlášení je rychlé a hash přepíše.
            2 => password_hash(self::HESLO, PASSWORD_BCRYPT, [
                'cost' => 4,
            ]),
        ]);
        $idUzivatele = (int) dbInsertId();
        $this->vytvoreniUzivatele[] = $idUzivatele;

        return $idUzivatele;
    }

    protected function getLogin(int $idUzivatele): string
    {
        return (string) dbOneCol(<<<'SQL'
            SELECT login_uzivatele FROM uzivatele_hodnoty WHERE id_uzivatele = $0
            SQL, [
            0 => $idUzivatele,
        ]);
    }

    /**
     * @return array{session: array, cookie: ?string}
     */
    protected function zarizeni(?string $cookie = null, array $session = []): array
    {
        return [
            'session' => $session,
            'cookie'  => $cookie,
        ];
    }

    /**
     * Session cookie zavřením prohlížeče zmizí, cookie trvalého přihlášení zůstane.
     */
    protected function poRestartuProhlizece(array $zarizeni): array
    {
        return $this->zarizeni(cookie: $zarizeni['cookie']);
    }

    protected function idPrihlasenehoNa(array &$zarizeni): ?int
    {
        return $this->pozadavek($zarizeni, static fn () => \Uzivatel::zSession())?->id();
    }

    protected function pozadavek(array &$zarizeni, callable $akce): mixed
    {
        $_SESSION = $zarizeni['session'];
        // Co si třída pamatuje jen po dobu požadavku, v jednom procesu testů by přežilo do dalšího.
        TrvalePrihlaseni::zapomenStavPozadavku();
        unset($_COOKIE[TrvalePrihlaseni::COOKIE]);
        if ($zarizeni['cookie'] !== null) {
            $_COOKIE[TrvalePrihlaseni::COOKIE] = $zarizeni['cookie'];
        }
        try {
            return $this->bezVarovaniSession($akce);
        } finally {
            $zarizeni = $this->zarizeni(
                cookie: $_COOKIE[TrvalePrihlaseni::COOKIE] ?? null,
                session: $_SESSION ?? [],
            );
        }
    }

    /**
     * Hlášky pro uživatele ({@see \Chyba::nastav()}) jako HTML, které by ukázala příští stránka. Vyzvednuté zmizí.
     */
    protected function vyzvedniHlasky(): string
    {
        return $this->bezVarovaniSession(static fn (): string => \Chyba::vyzvedniHtml());
    }

    /**
     * Session a cookie se v CLI po výstupu testovacího běhu nedají nastavit a hlásí to varováním,
     * což o ověřované věci nic neříká. Jiné varování test shodí, výjimky to nepotlačí.
     *
     * Varování potlačené zavináčem nechá být: s tím kód pod testem počítá a ošetří ho sám.
     */
    private function bezVarovaniSession(callable $akce): mixed
    {
        set_error_handler(static function (int $uroven, string $zprava, string $soubor, int $radek): bool {
            if (! (error_reporting() & $uroven)) {
                return false;
            }
            if (preg_match(self::VZOR_VAROVANI_SESSION, $zprava) === 1) {
                return true;
            }
            throw new \ErrorException($zprava, 0, $uroven, $soubor, $radek);
        }, E_WARNING);
        try {
            return $akce();
        } finally {
            restore_error_handler();
        }
    }
}
