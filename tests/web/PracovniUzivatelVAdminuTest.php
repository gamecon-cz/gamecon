<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Login\Login;
use Gamecon\Pravo;

/**
 * Pracovního uživatele si vybere jen přihlášený s právem stránky, která s ním pracuje (PracovniUzivatel::PRAVA),
 * POSTem z výběru uživatele i odkazem s `?pracovni_uzivatel=`. Kdo právo nemá, s pracovním uživatelem nepracuje:
 * výběr se mu nezpracuje, ani když ho poslal před přihlášením, a toho, kterého měl, než o právo přišel, mu admin
 * zahodí. Přihlašovat pracovního uživatele na aktivity smí jen infopult (PracovniUzivatel::PRAVO_PROGRAMU). Admin API
 * (`api/prihlasenyUzivatel`) vrací pracovního uživatele jako účastníka a přihlášeného jako operátora, proto se podle
 * něj pozná, s kým admin pracuje.
 *
 * Každý test pracuje s vlastní relací, aby nezávisel na pořadí testů.
 */
class PracovniUzivatelVAdminuTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    /**
     * Vypravěč: do adminu smí (Moje aktivity), pracovního uživatele ale mít nesmí.
     */
    private const int ID_VYPRAVECE = 9821;
    private const string LOGIN_VYPRAVECE = 'test-pracovni-vypravec';
    private const int ID_ROLE_VYPRAVECE = 9821;
    private const int ID_VYBIRANEHO = 9822;
    /**
     * Finance: pracovního uživatele mít smí, přihlašovat ho na aktivity ne.
     */
    private const int ID_FINANCE = 9823;
    private const string LOGIN_FINANCE = 'test-pracovni-finance';
    private const int ID_ROLE_FINANCE = 9823;
    /**
     * Aktivita, na které je vybíraný přihlášený a operátor ne.
     */
    private const int ID_AKTIVITY_VYBIRANEHO = 9824;

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
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (%1$d, '%2$s', 'Test', 'Vypravěč', 'test-pracovni-vypravec@example.invalid'),
    (%3$d, 'test-pracovni-vybirany', 'Test', 'Vybíraný', 'test-pracovni-vybirany@example.invalid')
SQL,
                self::ID_VYPRAVECE,
                self::LOGIN_VYPRAVECE,
                self::ID_VYBIRANEHO,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES (%1$d, 'TEST_PRACOVNI_VYPRAVEC', 'Test pracovní vypravěč', '', -1, 'trvala', '')
SQL,
                self::ID_ROLE_VYPRAVECE,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO prava_role(id_role, id_prava) VALUES (%1$d, %2$d)
SQL,
                self::ID_ROLE_VYPRAVECE,
                Pravo::ADMINISTRACE_MOJE_AKTIVITY,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role) VALUES (%1$d, %2$d)
SQL,
                self::ID_VYPRAVECE,
                self::ID_ROLE_VYPRAVECE,
            ),
            [
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES ($0, $1, 'Test', 'Finance', 'test-pracovni-finance@example.invalid')
SQL,
                [self::ID_FINANCE, self::LOGIN_FINANCE],
            ],
            [
                <<<'SQL'
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, 'TEST_PRACOVNI_FINANCE', 'Test pracovní finance', '', -1, 'trvala', '')
SQL,
                [self::ID_ROLE_FINANCE],
            ],
            [
                <<<'SQL'
INSERT INTO prava_role(id_role, id_prava) VALUES ($0, $1)
SQL,
                [self::ID_ROLE_FINANCE, Pravo::ADMINISTRACE_FINANCE],
            ],
            [
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role) VALUES ($0, $1)
SQL,
                [self::ID_FINANCE, self::ID_ROLE_FINANCE],
            ],
            [
                <<<'SQL'
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m, teamova)
VALUES ($0, 'Test pracovní uživatel', 'test-pracovni-uzivatel', 1, $1, 1, 'Popis', 0, 5, 0, 0, 0)
SQL,
                [self::ID_AKTIVITY_VYBIRANEHO, ROCNIK],
            ],
            [
                <<<'SQL'
INSERT INTO akce_prihlaseni(id_akce, id_uzivatele, id_stavu_prihlaseni) VALUES ($0, $1, $2)
SQL,
                [self::ID_AKTIVITY_VYBIRANEHO, self::ID_VYBIRANEHO, StavPrihlaseni::PRIHLASEN],
            ],
        ];
    }

    /**
     * @test
     */
    public function sPravemInfopultuSiPracovnihoUzivateleVybere(): void
    {
        $relace = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $this->vyberPracovnihoUzivatele($relace, self::ID_VYBIRANEHO);
        self::assertSame(self::ID_VYBIRANEHO, $this->getIdPracovnihoUzivatele($relace));
        self::assertSame(\Uzivatel::SYSTEM, $this->getPrihlasenyUzivatel($relace)['operator']['id'] ?? null);

        $odkaz = $this->otevri($relace, 'uzivatel?pracovni_uzivatel=' . self::ID_VYPRAVECE);
        self::assertSame(303, $odkaz['kod']);
        self::assertStringNotContainsString('pracovni_uzivatel', $odkaz['kam']);
        self::assertSame(self::ID_VYPRAVECE, $this->getIdPracovnihoUzivatele($relace));
    }

    /**
     * @test
     */
    public function bezPravaSiPracovnihoUzivateleNevybere(): void
    {
        $relace = $this->prihlas(self::LOGIN_VYPRAVECE);

        $this->odesli($relace, 'uzivatel', [
            'vybratUzivateleProPraci' => 1,
            'id'                      => self::ID_VYBIRANEHO,
        ]);
        self::assertNull($this->getIdPracovnihoUzivatele($relace));

        // odkaz nezůstane viset v adrese, vrátí na ni bez parametru
        $odkaz = $this->otevri($relace, 'moje-aktivity?pracovni_uzivatel=' . self::ID_VYBIRANEHO);
        self::assertSame(303, $odkaz['kod']);
        self::assertStringNotContainsString('pracovni_uzivatel', $odkaz['kam']);
        self::assertNull($this->getIdPracovnihoUzivatele($relace));
    }

    /**
     * Výběr poslaný bez přihlášení se nezpracuje, takže nečeká v session na toho, kdo se přihlásí příště. Dřív se
     * zpracoval komukoli. Odkaz s `?pracovni_uzivatel=` se zpracuje až po přihlášení, a jen když v adrese zůstane, viz
     * odkazNaUzivateleProjdePrihlasenim().
     *
     * @test
     */
    public function vyberPredPrihlasenimSeNezpracuje(): void
    {
        $relace = $this->getNovaRelace();

        $this->odesli($relace, 'uzivatel', [
            'vybratUzivateleProPraci' => 1,
            'id'                      => self::ID_VYBIRANEHO,
        ]);
        $this->otevri($relace, 'infopult?pracovni_uzivatel=' . self::ID_VYBIRANEHO);
        $prihlaseni = $this->prihlasVRelaci($relace, \Uzivatel::SYSTEM_LOGIN, 'infopult');
        self::assertSame(303, $prihlaseni['kod']);

        self::assertNull($this->getIdPracovnihoUzivatele($relace));
    }

    /**
     * Kdo o právo přijde, tomu se pracovní uživatel při dalším požadavku zahodí, takže se nevrátí, ani když právo
     * zase dostane. Tady mu roli odebere a vrátí databáze, jako by to udělal někdo jiný na stránce Práva.
     *
     * @test
     */
    public function pracovniUzivatelSeSOdebranymPravemZahodi(): void
    {
        $relace = $this->prihlas(self::LOGIN_FINANCE);
        $this->vyberPracovnihoUzivatele($relace, self::ID_VYBIRANEHO);
        self::assertSame(self::ID_VYBIRANEHO, $this->getIdPracovnihoUzivatele($relace));

        $odebratRoli = <<<'SQL'
DELETE FROM uzivatele_role WHERE id_uzivatele = $0 AND id_role = $1
SQL;
        $vratitRoli = <<<'SQL'
INSERT IGNORE INTO uzivatele_role(id_uzivatele, id_role) VALUES ($0, $1)
SQL;
        try {
            dbQuery($odebratRoli, [self::ID_FINANCE, self::ID_ROLE_FINANCE]);
            // bez práva admin nic nedovolí, požadavek ale klíč pracovního uživatele zahodí
            self::assertSame(403, $this->otevri($relace, 'finance')['kod']);
        } finally {
            dbQuery($vratitRoli, [self::ID_FINANCE, self::ID_ROLE_FINANCE]);
        }

        self::assertNull($this->getIdPracovnihoUzivatele($relace));
    }

    /**
     * Totéž přepnutím na vypravěče: přepnutí přesměruje na jeho stránku a už ten požadavek pracovního uživatele
     * zahodí, takže se nevrátí, ani když se v téže relaci znovu přihlásí někdo s právem.
     *
     * @test
     */
    public function pracovniUzivatelSeZtratouPravaZahodi(): void
    {
        $relace = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($relace, self::ID_VYBIRANEHO);

        self::assertSame(303, $this->odesli($relace, 'uzivatel', [
            'prihlasitSeJakoUzivatel' => 1,
            'id'                      => self::ID_VYPRAVECE,
        ]));
        // požadavek vypravěče, jako by prohlížeč šel za přesměrováním po přepnutí
        $vypravec = $this->getPrihlasenyUzivatel($relace);
        self::assertSame(self::ID_VYPRAVECE, $vypravec['operator']['id'] ?? null, 'Přepnutí na vypravěče se nepovedlo');
        self::assertSame(self::ID_VYPRAVECE, $vypravec['ucastnik']['id'] ?? null);

        self::assertSame(303, $this->prihlasVRelaci($relace, \Uzivatel::SYSTEM_LOGIN, 'uzivatel')['kod']);
        self::assertNull($this->getIdPracovnihoUzivatele($relace));
    }

    /**
     * Odkaz s `?pracovni_uzivatel=` (z e-mailu, z reportu) otevře uživatele i tomu, koho napřed čeká přihlášení:
     * parametr zůstane v adrese, formulář přihlášení se na ni odešle a po přihlášení se zpracuje.
     *
     * @test
     */
    public function odkazNaUzivateleProjdePrihlasenim(): void
    {
        $relace = $this->getNovaRelace();
        $adresa = 'uzivatel?pracovni_uzivatel=' . self::ID_VYBIRANEHO;

        $prihlasovaciStranka = $this->otevri($relace, $adresa);
        self::assertSame(200, $prihlasovaciStranka['kod']);
        self::assertStringContainsString('name="' . Login::PASSWORD_INPUT_NAME . '"', $prihlasovaciStranka['telo']);

        $prihlaseni = $this->prihlasVRelaci($relace, \Uzivatel::SYSTEM_LOGIN, $adresa);
        self::assertSame(303, $prihlaseni['kod']);
        self::assertStringContainsString('pracovni_uzivatel=' . self::ID_VYBIRANEHO, $prihlaseni['kam']);

        $vyber = $this->otevriAdresu($relace, $prihlaseni['kam']);
        self::assertSame(303, $vyber['kod']);
        self::assertStringNotContainsString('pracovni_uzivatel', $vyber['kam']);

        self::assertSame(self::ID_VYBIRANEHO, $this->getIdPracovnihoUzivatele($relace));
    }

    /**
     * Finance pracovního uživatele mít smí, admin API za něj ale nepřihlašuje na aktivity ani nespravuje jeho tým.
     *
     * @test
     */
    public function financeZaPracovnihoUzivateleNeprihlasuje(): void
    {
        $relace = $this->prihlas(self::LOGIN_FINANCE);
        $this->vyberPracovnihoUzivatele($relace, self::ID_VYBIRANEHO);
        self::assertSame(self::ID_VYBIRANEHO, $this->getIdPracovnihoUzivatele($relace));

        self::assertSame(403, $this->odesli($relace, 'api/aktivitaAkce', [
            'odhlasit' => self::ID_AKTIVITY_VYBIRANEHO,
        ]));
        self::assertSame(403, $this->otevri($relace, 'api/aktivitaTym?aktivitaId=' . self::ID_AKTIVITY_VYBIRANEHO)['kod']);
        self::assertSame('1', (string) dbOneCol(<<<'SQL'
SELECT COUNT(*) FROM akce_prihlaseni WHERE id_akce = $0 AND id_uzivatele = $1
SQL, [self::ID_AKTIVITY_VYBIRANEHO, self::ID_VYBIRANEHO]), 'Odhlášení pracovního uživatele mělo být odmítnuto');
    }

    /**
     * Odpověď akce nese stav přihlášení účastníka, ne operátora: program ho zapíše do programu pracovního uživatele.
     *
     * @test
     */
    public function akceVraciStavPracovnihoUzivatele(): void
    {
        $relace = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($relace, self::ID_VYBIRANEHO);

        // vybíraný už přihlášený je, přihlášení tedy nic nezmění
        $odpoved = $this->odesliSTelem($relace, 'api/aktivitaAkce', [
            'prihlasit' => self::ID_AKTIVITY_VYBIRANEHO,
        ]);
        self::assertSame(200, $odpoved['kod'], $odpoved['telo']);
        $data = json_decode($odpoved['telo'], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(self::ID_AKTIVITY_VYBIRANEHO, $data['aktivitaUzivatel']['id'] ?? null, $odpoved['telo']);
        self::assertSame(
            StavPrihlaseni::frontendKod(StavPrihlaseni::PRIHLASEN),
            $data['aktivitaUzivatel']['stavPrihlaseni'] ?? null,
            $odpoved['telo'],
        );
    }

    /**
     * Pracovní uživatel je účastník z admin API. Bez pracovního uživatele je účastníkem přihlášený sám; sám sebe si
     * v těchto testech nikdo nevybírá, takže shoda s operátorem znamená, že pracovního uživatele nemá.
     */
    private function getIdPracovnihoUzivatele(string $relace): ?int
    {
        $data = $this->getPrihlasenyUzivatel($relace);
        $idUcastnika = $data['ucastnik']['id'] ?? null;

        return $idUcastnika !== ($data['operator']['id'] ?? null)
            ? $idUcastnika
            : null;
    }

    /**
     * @return array{ucastnik?: array{id: int}, operator?: array{id: int}}
     */
    private function getPrihlasenyUzivatel(string $relace): array
    {
        return json_decode($this->stahni($relace, 'api/prihlasenyUzivatel'), true, 512, JSON_THROW_ON_ERROR);
    }
}
