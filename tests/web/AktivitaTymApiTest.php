<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

/**
 * Týmové API (`aktivitaTym`) přes skutečný admin, kde operátor jedná za pracovního uživatele: akci s týmem provede
 * kapitán, tedy pracovní uživatel, ne operátor. Chyby přijdou jako JSON, ne jako prázdná odpověď nebo 500.
 */
class AktivitaTymApiTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_KAPITANA = 9851;
    private const int ID_CLENA = 9852;

    /**
     * týmová aktivita
     */
    private const int ID_TYMOVE_AKTIVITY = 9851;

    /**
     * soukromý tým s kapitánem a členem
     */
    private const int ID_TYMU = 9851;

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
            [
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    ($0, 'test-tym-api-kapitan', 'Test', 'Kapitán', 'test-tym-api-kapitan@example.invalid'),
    ($1, 'test-tym-api-clen', 'Test', 'Člen', 'test-tym-api-clen@example.invalid')
SQL,
                [self::ID_KAPITANA, self::ID_CLENA],
            ],
            [
                <<<'SQL'
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m,
                        teamova, team_min, team_max, team_kapacita)
VALUES ($0, 'Test týmové API', 'test-tym-api', 1, $1, 1, 'Popis', 0, 10, 0, 0, 1, 1, 4, NULL)
SQL,
                [self::ID_TYMOVE_AKTIVITY, ROCNIK],
            ],
            [
                <<<'SQL'
INSERT INTO akce_tym(id, kod, nazev, id_kapitan, zalozen, verejny, zamceny)
VALUES ($0, 985101, NULL, $1, NOW(), 0, 0)
SQL,
                [self::ID_TYMU, self::ID_KAPITANA],
            ],
            [
                <<<'SQL'
INSERT INTO akce_tym_akce(id_tymu, id_akce) VALUES ($0, $1)
SQL,
                [self::ID_TYMU, self::ID_TYMOVE_AKTIVITY],
            ],
            [
                <<<'SQL'
INSERT INTO akce_tym_prihlaseni(id_uzivatele, id_tymu) VALUES ($0, $2), ($1, $2)
SQL,
                [self::ID_KAPITANA, self::ID_CLENA, self::ID_TYMU],
            ],
        ];
    }

    public function testTymNeexistujiciAktivityJe404(): void
    {
        $odpoved = $this->otevri($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'api/aktivitaTym?aktivitaId=999999');

        self::assertSame(404, $odpoved['kod'], $odpoved['telo']);
        self::assertArrayHasKey('chyba', json_decode($odpoved['telo'], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Kapitánem je pracovní uživatel, ne operátor: bez něj operátor jedná sám za sebe a kapitánem není.
     */
    public function testKapitanemJePracovniUzivatelNeOperator(): void
    {
        $relace = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $akce = [
            'akce'   => 'nastavNazev',
            'idTymu' => self::ID_TYMU,
            'nazev'  => 'Test týmové API',
        ];

        $odpovedOperatora = $this->odesliAkci($relace, $akce);
        self::assertFalse($odpovedOperatora['úspěch']);
        self::assertSame('Tuto akci může provést pouze kapitán týmu', $odpovedOperatora['chyba']['hláška'] ?? null);

        $this->vyberPracovnihoUzivatele($relace, self::ID_KAPITANA);
        $odpovedKapitana = $this->odesliAkci($relace, $akce);
        self::assertTrue($odpovedKapitana['úspěch'], $odpovedKapitana['chyba']['hláška'] ?? '');
        self::assertSame('Test týmové API', dbOneCol(<<<'SQL'
SELECT nazev FROM akce_tym WHERE id = $0
SQL, [self::ID_TYMU]));
    }

    /**
     * Na webu, kde vypršelo přihlášení, odpoví API 401 s JSON tělem, ne prázdnou odpovědí, kterou by program nepřečetl.
     */
    public function testNeprihlasenyNaWebuDostane401(): void
    {
        $relace = $this->getNovaRelace();
        foreach (['aktivitaTym', 'aktivitaAkce'] as $endpoint) {
            $adresa = 'http://localhost/web/api/' . $endpoint;
            $curl = $this->curlNaAdresu($relace, $adresa);
            curl_setopt($curl, CURLOPT_POST, true);
            // POST bez odkazující stránky z téhož webu web zahodí jako CSRF, viz omezCsrf()
            curl_setopt($curl, CURLOPT_REFERER, $adresa);
            curl_setopt($curl, CURLOPT_POSTFIELDS, [
                'akce'      => 'nastavNazev',
                'idTymu'    => self::ID_TYMU,
                'prihlasit' => self::ID_TYMOVE_AKTIVITY,
            ]);
            $odpoved = $this->proved($curl);

            self::assertSame(401, $odpoved['kod'], "{$endpoint}: {$odpoved['telo']}");
            self::assertArrayHasKey('error', json_decode($odpoved['telo'], true, 512, JSON_THROW_ON_ERROR), $endpoint);
        }
    }

    /**
     * @param array<string, int|string> $pole
     *
     * @return array{úspěch: bool, chyba?: array{hláška: string}}
     */
    private function odesliAkci(string $relace, array $pole): array
    {
        $odpoved = $this->odesliSTelem($relace, 'api/aktivitaTym', $pole);
        self::assertSame(200, $odpoved['kod'], $odpoved['telo']);

        return json_decode($odpoved['telo'], true, 512, JSON_THROW_ON_ERROR);
    }
}
