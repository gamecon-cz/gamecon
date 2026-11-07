<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Admin\FormularPracovnihoUzivatele;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Formulář pracovního uživatele uloží změnu jen tomu, u koho ho organizátor viděl, ne tomu, na koho se mezitím
 * přepnul v jiné záložce prohlížeče. Přes skutečnou administraci, na formuláři osobních údajů záložky Uživatel.
 */
class FormularPracovnihoUzivateleTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_PRVNIHO = 9811;
    private const int ID_DRUHEHO = 9812;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele, telefon_uzivatele)
VALUES
    (%1$d, 'test-formular-prvni', 'Test', 'První', 'test-formular-prvni@example.invalid', '111111111'),
    (%2$d, 'test-formular-druhy', 'Test', 'Druhý', 'test-formular-druhy@example.invalid', '222222222')
SQL,
                self::ID_PRVNIHO,
                self::ID_DRUHEHO,
            ),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        // aby se DNS vyřešilo ještě před curl, které by jinak mohlo padnout na ještě nepřipraveném Apache
        get_headers(URL_ADMIN);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kUklidu);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function formularJinehoNezPracovnihoUzivateleNicNeulozi(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        // formulář se načetl u prvního, pak se v jiné záložce prohlížeče přepnulo na druhého
        $this->vyberPracovnihoUzivatele($session, self::ID_DRUHEHO);

        self::assertSame(303, $this->odesli($session, 'uzivatel', [
            'zmenitUdaj'                         => 'uložit',
            'udaj[telefon_uzivatele]'            => '333333333',
            FormularPracovnihoUzivatele::POST_ID => self::ID_PRVNIHO,
        ]));

        self::assertSame('111111111', $this->telefon(self::ID_PRVNIHO));
        self::assertSame('222222222', $this->telefon(self::ID_DRUHEHO));
        self::assertStringContainsString('formulář patřil jinému uživateli', $this->stahni($session, 'uzivatel'));
    }

    /**
     * @test
     */
    public function formularPracovnihoUzivateleSeUlozi(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($session, self::ID_DRUHEHO);

        // každý formulář v mřížce záložky nese ID pracovního uživatele; formuláře rámu adminu (odhlášení, vzhled) ne
        $stranka = $this->stahni($session, 'uzivatel');
        $zacatek = strpos($stranka, 'class="uzivatel-grid--container"');
        $konec = strpos($stranka, '</form>', (int) strpos($stranka, 'name="prodej[odeslano]"'));
        self::assertNotFalse($zacatek);
        self::assertNotFalse($konec);
        preg_match_all('~<form method="post"[^>]*>.*?</form>~s', substr($stranka, $zacatek, $konec - $zacatek + strlen('</form>')), $formulare);
        self::assertCount(2, $formulare[0], 'osobní údaje a prodej, ubytování a jídlo má jen přihlášený na GameCon');
        foreach ($formulare[0] as $formular) {
            self::assertStringContainsString(
                'name="' . FormularPracovnihoUzivatele::POST_ID . '" value="' . self::ID_DRUHEHO . '"',
                $formular,
            );
        }

        self::assertSame(303, $this->odesli($session, 'uzivatel', [
            'zmenitUdaj'                         => 'uložit',
            'udaj[telefon_uzivatele]'            => '444444444',
            FormularPracovnihoUzivatele::POST_ID => self::ID_DRUHEHO,
        ]));

        self::assertSame('444444444', $this->telefon(self::ID_DRUHEHO));
    }

    private function vyberPracovnihoUzivatele(string $session, int $idUzivatele): void
    {
        self::assertSame(303, $this->odesli($session, 'uzivatel', [
            'vybratUzivateleProPraci' => 1,
            'id'                      => $idUzivatele,
        ]));
    }

    private function telefon(int $idUzivatele): string
    {
        return (string) dbOneCol(<<<'SQL'
SELECT telefon_uzivatele FROM uzivatele_hodnoty WHERE id_uzivatele = $0
SQL, [0 => $idUzivatele]);
    }
}
