<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\TrvalePrihlaseni;

/**
 * Zařízení je tu session a cookie, se kterými prohlížeč přijde na server. {@see self::pozadavek()}
 * je nastaví a po požadavku uloží, co v nich zůstalo.
 */
abstract class AbstractPrihlaseniNaZarizenichTestDb extends AbstractTestDb
{
    protected const string HESLO = 'heslo-trvale-prihlaseni';

    private bool $sessionIdNastavenoTady = false;

    /**
     * @var array<mixed>|null null, když před testem nebyla
     */
    private ?array $sessionPredTestem = null;

    private ?string $cookiePredTestem = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionPredTestem = $_SESSION ?? null;
        $this->cookiePredTestem = $_COOKIE[TrvalePrihlaseni::COOKIE] ?? null;
        // Funkce session v CLI po prvním výstupu testů hlásí varování, ale čtou $_SESSION i bez ní.
        if (session_id() === '') {
            session_id('trvale-prihlaseni-test');
            $this->sessionIdNastavenoTady = true;
        }
    }

    protected function tearDown(): void
    {
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
        if ($this->sessionIdNastavenoTady) {
            session_id('');
        }

        parent::tearDown();
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
    0         => 'test_trvale_' . uniqid('', true),
            1 => 'test.trvale.' . uniqid('', true) . '@example.org',
            // Slabší cena než výchozí: přihlášení je rychlé a hash přepíše.
            2 => password_hash(self::HESLO, PASSWORD_BCRYPT, [
                'cost' => 4,
            ]),
]);

        return (int) dbInsertId();
    }

    protected function login(int $idUzivatele): string
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
     * Session a cookie se v CLI po výstupu testovacího běhu nedají nastavit a hlásí to varováním,
     * což o ověřované věci nic neříká. Výjimky to nepotlačí.
     */
    private function bezVarovaniSession(callable $akce): mixed
    {
        set_error_handler(static fn (): bool => true, E_WARNING);
        try {
            return $akce();
        } finally {
            restore_error_handler();
        }
    }
}
