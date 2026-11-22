<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Role\Role;

/**
 * Sekce „Chceš se zapojit do organizace?“ na přihlášce (`/prihlaska`) přes skutečný Apache a modul přihlášky.
 *
 * Sekce je jen text. Formulář s druhem pomoci a podrobnostmi zůstal v šabloně skrytý po úpravě textů v únoru 2025 a
 * přihláška pak každým uložením přepsala `pomoc_typ` a `pomoc_vice` prázdnou hodnotou: zpracování čekalo zaškrtávátko
 * `cPomoc[chci]`, které už nebylo. Kdyby se jen zahodila podmínka na zaškrtávátko, zapsal by se každému druh pomoci
 * „dobrovolník“, předvybraný ve skrytém formuláři. Viz docs/generated/prihlaska-odeslani-objednavky.md.
 */
class PomocVPrihlasceTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_PROHLIZEJICIHO = 9871;
    private const int ID_UKLADAJICIHO = 9872;

    private const string LOGIN_PROHLIZEJICIHO = 'test-pomoc-prohlizi';
    private const string LOGIN_UKLADAJICIHO = 'test-pomoc-uklada';

    private const string ULOZENY_TYP = 'vypravec';
    private const string ULOZENE_PODROBNOSTI = 'Uvedu deskovku, domluveno s vedoucím deskoher.';

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    /**
     * Apache čte databázi vlastním spojením, transakci testu by neviděl.
     */
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
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele, pohlavi, datum_narozeni, pomoc_typ, pomoc_vice)
VALUES
    ($0, $1, 'Test', 'Prohlížející', 'test-pomoc-prohlizi@example.invalid', 'f', '1990-01-01', '', ''),
    ($2, $3, 'Test', 'Ukládající', 'test-pomoc-uklada@example.invalid', 'm', '1990-01-01', $4, $5)
SQL,
                [
                    0 => self::ID_PROHLIZEJICIHO,
                    1 => self::LOGIN_PROHLIZEJICIHO,
                    2 => self::ID_UKLADAJICIHO,
                    3 => self::LOGIN_UKLADAJICIHO,
                    4 => self::ULOZENY_TYP,
                    5 => self::ULOZENE_PODROBNOSTI,
                ],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->otevriPrihlasovani();
    }

    public function testSekceZapojitSeJeJenTextBezFormulare(): void
    {
        $session = $this->prihlas(self::LOGIN_PROHLIZEJICIHO);

        $stranka = $this->stahniVerejnou($session, $this->cesta('prihlaska'));

        self::assertStringContainsString('name="prihlasitNeboUpravit"', $stranka, 'Na stránce není formulář přihlášky.');
        self::assertStringContainsString('<h3>Chceš se zapojit do organizace?</h3>', $stranka);
        self::assertStringContainsString('<a href="organizacni-vypomoc">tady</a>', $stranka);
        self::assertStringNotContainsString(
            'name="cPomoc',
            $stranka,
            'Sekce Chceš se zapojit nesmí posílat žádné pole: skrytý formulář by odeslal předvybraný druh pomoci za každého.',
        );
    }

    public function testUlozeniPrihlaskyNechaUlozenouPomocBezeZmeny(): void
    {
        $session = $this->prihlas(self::LOGIN_UKLADAJICIHO);

        // Pole skrytého formuláře posílá stránka otevřená ještě před nasazením, přihláška je musí přejít.
        $odeslani = $this->odesliPrihlasku($session, [
            'prihlasitNeboUpravit' => '1',
            'cPomoc[typ]'          => 'dobrovolnik',
            'cPomoc[detail]'       => '',
        ]);

        self::assertContains($odeslani['kod'], [302, 303], $odeslani['telo']);
        self::assertSame(
            '1',
            (string) dbOneCol(
                'SELECT COUNT(*) FROM uzivatele_role WHERE id_uzivatele = $0 AND id_role = $1',
                [
                    0 => self::ID_UKLADAJICIHO,
                    1 => Role::PRIHLASEN_NA_LETOSNI_GC,
                ],
            ),
            'Přihláška se neuložila, test by tak nic neověřil.',
        );
        self::assertSame(
            [
                'pomoc_typ'  => self::ULOZENY_TYP,
                'pomoc_vice' => self::ULOZENE_PODROBNOSTI,
            ],
            dbOneLine(
                'SELECT pomoc_typ, pomoc_vice FROM uzivatele_hodnoty WHERE id_uzivatele = $0',
                [
                    0 => self::ID_UKLADAJICIHO,
                ],
            ),
            'Uložení přihlášky nesmí přepsat druh pomoci ani podrobnosti, které sekce Chceš se zapojit nezobrazuje.',
        );
        self::assertSame(
            '0',
            (string) dbOneCol(
                "SELECT COUNT(*) FROM uzivatele_hodnoty_log WHERE id_uzivatele = $0 AND sloupec IN ('pomoc_typ', 'pomoc_vice')",
                [
                    0 => self::ID_UKLADAJICIHO,
                ],
            ),
            'Audit nesmí hlásit změnu pomoci, která se nestala.',
        );
    }

    /**
     * Přihlašování běží, GameCon začne za měsíc. Termíny se nastavují kolem skutečného teď, Apache čas podstrčit nejde.
     */
    private function otevriPrihlasovani(): void
    {
        foreach ([
            'GC_BEZI_OD' => 30,
            'GC_BEZI_DO' => 33,
            'REG_GC_OD'  => -10,
            'REG_GC_DO'  => 33,
        ] as $klic => $dni) {
            dbQuery(<<<'SQL'
UPDATE systemove_nastaveni
SET vlastni = 1, hodnota = DATE_FORMAT(DATE_ADD(NOW(), INTERVAL $0 DAY), '%Y-%m-%d %H:%i:%s')
WHERE klic = $1
SQL,
                [
                    0 => $dni,
                    1 => $klic,
                ],
            );
        }
    }

    /**
     * @param array<string, string> $pole
     *
     * @return array{kod: int, kam: string, telo: string}
     */
    private function odesliPrihlasku(string $session, array $pole): array
    {
        $curl = $this->curlNaAdresu($session, URL_WEBU . '/prihlaska');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $pole);
        // ochrana proti CSRF (omezCsrf()) chce odkazujícího ze stejného webu
        curl_setopt($curl, CURLOPT_REFERER, URL_WEBU . '/prihlaska');

        return $this->proved($curl);
    }

    /**
     * Cesta stránky webu od kořene serveru pro {@see stahniVerejnou()}, třeba `/web/prihlaska`.
     */
    private function cesta(string $stranka): string
    {
        return parse_url(URL_WEBU, PHP_URL_PATH) . '/' . $stranka;
    }
}
