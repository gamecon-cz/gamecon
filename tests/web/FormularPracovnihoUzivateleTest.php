<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Admin\FormularPracovnihoUzivatele;
use Gamecon\Login\Login;
use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Formulář pracovního uživatele uloží změnu jen tomu, u koho ho organizátor viděl, ne tomu, na koho se mezitím
 * přepnul v jiné záložce prohlížeče. Přes skutečnou administraci, na záložce Uživatel a na Infopultu.
 *
 * Každý test pracuje s vlastními uživateli, aby nezávisel na pořadí testů (třída se do původního stavu vrací až po
 * posledním z nich).
 */
class FormularPracovnihoUzivateleTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    /**
     * u něj organizátor formulář načetl, než se v jiné záložce přepnul na druhého
     */
    private const int ID_PRVNIHO = 9811;
    private const int ID_DRUHEHO = 9812;
    private const int ID_UKLADAJICIHO = 9813;
    /**
     * přihlášený na GameCon s ubytováním: záložka Uživatel i Infopult mu ukážou všechny formuláře
     */
    private const int ID_UBYTOVANEHO = 9814;
    private const int ID_UBYTOVANI = 9811;
    private const int ID_UBYTOVANI_POSTEL = 9815;
    /**
     * má objednaná dvě ubytování, každé na jiný den, kvůli výpisu po řádcích
     */
    private const int ID_SE_DVEMA_UBYTOVANIMI = 9816;
    /**
     * bez pokoje i bez objednaného ubytování
     */
    private const int ID_BEZ_UBYTOVANI = 9817;
    /**
     * bydlí na jednom pokoji se spolubydlícím, který má ve jméně HTML
     */
    private const int ID_SE_SPOLUBYDLICIM = 9818;
    private const int ID_SPOLUBYDLICIHO_S_HTML = 9819;
    private const string POKOJ_SE_SPOLUBYDLICIM = 'test-formular-pokoj';

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
    (%2$d, 'test-formular-druhy', 'Test', 'Druhý', 'test-formular-druhy@example.invalid', '222222222'),
    (%3$d, 'test-formular-ukladajici', 'Test', 'Ukládající', 'test-formular-ukladajici@example.invalid', '333333333'),
    (%4$d, 'test-formular-ubytovany', 'Test', 'Ubytovaný', 'test-formular-ubytovany@example.invalid', '444444444'),
    (%5$d, 'test-formular-dve-ubytovani', 'Test', 'Dvojí', 'test-formular-dve-ubytovani@example.invalid', '555555555'),
    (%6$d, 'test-formular-bez-ubytovani', 'Test', 'Neubytovaný', 'test-formular-bez-ubytovani@example.invalid', '666666666'),
    (%7$d, 'test-formular-se-spolubydlicim', 'Test', 'Spolubydlící', 'test-formular-se-spolubydlicim@example.invalid', '777777777'),
    (%8$d, 'test-formular-html', '<i>Kurzíva</i>', 'Html', 'test-formular-html@example.invalid', '888888888')
SQL,
                self::ID_PRVNIHO,
                self::ID_DRUHEHO,
                self::ID_UKLADAJICIHO,
                self::ID_UBYTOVANEHO,
                self::ID_SE_DVEMA_UBYTOVANIMI,
                self::ID_BEZ_UBYTOVANI,
                self::ID_SE_SPOLUBYDLICIM,
                self::ID_SPOLUBYDLICIHO_S_HTML,
            ),
            [
                <<<'SQL'
INSERT INTO ubytovani(id_uzivatele, den, pokoj, rok) VALUES ($0, 1, $2, $3), ($1, 1, $2, $3)
SQL,
                [
                    0 => self::ID_SE_SPOLUBYDLICIM,
                    1 => self::ID_SPOLUBYDLICIHO_S_HTML,
                    2 => self::POKOJ_SE_SPOLUBYDLICIM,
                    3 => ROCNIK,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role) VALUES ($0, $1)
SQL,
                [
                    0 => self::ID_UBYTOVANEHO,
                    1 => Role::PRIHLASEN_NA_LETOSNI_GC,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO shop_predmety SET id_predmetu = $0, nazev = 'test formulář ubytování', model_rok = $1,
    kod_predmetu = 'test-formular-ubytovani', cena_aktualni = 200, stav = 1, nabizet_do = NOW(),
    kusu_vyrobeno = 100, typ = $2, ubytovani_den = 1
SQL,
                [
                    0 => self::ID_UBYTOVANI,
                    1 => ROCNIK,
                    2 => TypPredmetu::UBYTOVANI,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO shop_predmety SET id_predmetu = $0, nazev = 'test formulář postel', model_rok = $1,
    kod_predmetu = 'test-formular-postel', cena_aktualni = 300, stav = 1, nabizet_do = NOW(),
    kusu_vyrobeno = 100, typ = $2, ubytovani_den = 2
SQL,
                [
                    0 => self::ID_UBYTOVANI_POSTEL,
                    1 => ROCNIK,
                    2 => TypPredmetu::UBYTOVANI,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni) VALUES ($0, $1, $2, 200)
SQL,
                [
                    0 => self::ID_UBYTOVANEHO,
                    1 => self::ID_UBYTOVANI,
                    2 => ROCNIK,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni) VALUES ($0, $1, $3, 200), ($0, $2, $3, 300)
SQL,
                [
                    0 => self::ID_SE_DVEMA_UBYTOVANIMI,
                    1 => self::ID_UBYTOVANI,
                    2 => self::ID_UBYTOVANI_POSTEL,
                    3 => ROCNIK,
                ],
            ],
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
            'udaj[telefon_uzivatele]'            => '999999999',
            FormularPracovnihoUzivatele::POST_ID => self::ID_PRVNIHO,
        ]));

        self::assertSame('111111111', $this->getTelefon(self::ID_PRVNIHO));
        self::assertSame('222222222', $this->getTelefon(self::ID_DRUHEHO));
        self::assertStringContainsString('formulář patřil jinému uživateli', $this->stahni($session, 'uzivatel'));
    }

    /**
     * @test
     */
    public function formularPracovnihoUzivateleSeUlozi(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($session, self::ID_UKLADAJICIHO);

        $formulare = $this->getIdPracovnihoUzivateleVeFormularich($this->stahni($session, 'uzivatel'));
        self::assertCount(2, $formulare, 'osobní údaje a prodej, ubytování a jídlo má jen přihlášený na GameCon');
        foreach ($formulare as $formular => $idPracovnihoUzivatele) {
            self::assertSame((string) self::ID_UKLADAJICIHO, $idPracovnihoUzivatele, $formular);
        }

        self::assertSame(303, $this->odesli($session, 'uzivatel', [
            'zmenitUdaj'                         => 'uložit',
            'udaj[telefon_uzivatele]'            => '777777777',
            FormularPracovnihoUzivatele::POST_ID => self::ID_UKLADAJICIHO,
        ]));

        self::assertSame('777777777', $this->getTelefon(self::ID_UKLADAJICIHO));
    }

    /**
     * Pole nedoplňuje každý formulář zvlášť, ale stránka do všech svých formulářů, i do těch, které přibudou.
     *
     * @test
     */
    public function kazdyFormularZalozkyUzivatelAInfopultuNeseIdPracovnihoUzivatele(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($session, self::ID_UBYTOVANEHO);

        foreach ([
            'uzivatel' => 5,
            'infopult' => 9,
        ] as $stranka => $nejmeneFormularu) {
            $formulare = $this->getIdPracovnihoUzivateleVeFormularich($this->stahni($session, $stranka));
            self::assertGreaterThanOrEqual($nejmeneFormularu, count($formulare), $stranka);
            foreach ($formulare as $formular => $idPracovnihoUzivatele) {
                self::assertSame((string) self::ID_UBYTOVANEHO, $idPracovnihoUzivatele, "{$stranka}: {$formular}");
            }
        }
    }

    /**
     * @test
     */
    public function platbaNaInfopultuJinemuNezPracovnimuUzivateliSeNepripise(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        // formulář platby se načetl u prvního, pak se v jiné záložce prohlížeče přepnulo na druhého
        $this->vyberPracovnihoUzivatele($session, self::ID_DRUHEHO);

        self::assertSame(303, $this->odesli($session, 'infopult', [
            'platba'                             => '100',
            'poznamka'                           => '',
            FormularPracovnihoUzivatele::POST_ID => self::ID_PRVNIHO,
        ]));

        self::assertSame(0, $this->getPocetPlateb(self::ID_PRVNIHO));
        self::assertSame(0, $this->getPocetPlateb(self::ID_DRUHEHO));
        self::assertStringContainsString('formulář patřil jinému uživateli', $this->stahni($session, 'infopult'));
    }

    /**
     * @test
     */
    public function pokojNaZalozceUzivatelSePrideliPracovnimuUzivateli(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($session, self::ID_UBYTOVANEHO);

        self::assertSame(303, $this->odesli($session, 'uzivatel', [
            'pokoj'                              => '42',
            'pridelitPokoj'                      => 'Přidělit',
            FormularPracovnihoUzivatele::POST_ID => self::ID_UBYTOVANEHO,
        ]));

        self::assertSame('42', $this->getPokoj(self::ID_UBYTOVANEHO));
        self::assertStringContainsString('Pokoj přidělen', $this->stahni($session, 'uzivatel'));
    }

    /**
     * @test
     */
    public function objednaneUbytovaniNaInfopultuMaOdrazkuNaKazdemRadku(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($session, self::ID_SE_DVEMA_UBYTOVANIMI);

        $stranka = $this->stahni($session, 'infopult');

        self::assertMatchesRegularExpression('~Ubytování:</td>\s*<td><ul>(<li>[^<]+: [^<]+</li>){2}</ul></td>~', $stranka);
        self::assertStringContainsString('<li>test formulář ubytování: ', $stranka);
        self::assertStringContainsString('<li>test formulář postel: ', $stranka);
    }

    /**
     * @test
     */
    public function bezObjednanehoUbytovaniNaInfopultuNeniPrazdnaOdrazka(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($session, self::ID_BEZ_UBYTOVANI);

        $stranka = $this->stahni($session, 'infopult');

        self::assertMatchesRegularExpression('~Spolubydlící:</td>\s*<td>\s*</td>\s*</tr>\s*<tr>\s*<td>Ubytování:</td>\s*<td></td>~', $stranka);
        self::assertStringNotContainsString('<ul></ul>', $stranka);
        self::assertDoesNotMatchRegularExpression('~<li>\s*</li>~', $stranka);
    }

    /**
     * Jméno, přezdívku i telefon si spolubydlící vyplnil sám, do stránky infopultu je nesmí vložit jako HTML.
     *
     * @test
     */
    public function spolubydliciNaInfopultuJsouSeznamemBezHtmlZeJmena(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($session, self::ID_SE_SPOLUBYDLICIM);

        $stranka = $this->stahni($session, 'infopult');

        self::assertMatchesRegularExpression('~Spolubydlící:</td>\s*<td>\s*<ul>(<li>[^<]+</li>){2}</ul>\s*</td>~', $stranka);
        self::assertStringContainsString('&lt;i&gt;Kurzíva&lt;/i&gt;', $stranka);
        self::assertStringNotContainsString('<i>Kurzíva</i>', $stranka);
    }

    /**
     * Prázdné číslo by smazalo přidělený pokoj a místo něj zapsalo prázdný.
     *
     * @test
     */
    public function prazdnyPokojNicNezmeni(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($session, self::ID_UBYTOVANEHO);
        $prideleni = [
            'pokoj'                              => '7',
            'pridelitPokoj'                      => 'Přidělit',
            FormularPracovnihoUzivatele::POST_ID => self::ID_UBYTOVANEHO,
        ];
        self::assertSame(303, $this->odesli($session, 'uzivatel', $prideleni));

        $prazdny = [
            ...$prideleni,
            'pokoj' => ' ',
        ];
        foreach (['uzivatel', 'infopult'] as $stranka) {
            self::assertSame(303, $this->odesli($session, $stranka, $prazdny), $stranka);
            self::assertSame('7', $this->getPokoj(self::ID_UBYTOVANEHO), $stranka);
            self::assertStringContainsString('Vyplň číslo pokoje', $this->stahni($session, $stranka), $stranka);
        }
    }

    /**
     * Rychloregistrace zakládá nového uživatele a s pracovním nesouvisí: přepnutí pracovního uživatele v jiné záložce
     * ji odmítnout nemá.
     *
     * @test
     */
    public function rychloregistraceNeseBezIdPracovnihoUzivatele(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $formulare = $this->getIdPracovnihoUzivateleVeFormularich($this->stahni($session, 'infopult'));
        $rychloregistrace = array_filter(
            $formulare,
            static fn (string $formular): bool => str_contains($formular, 'name="rychloregistrace"'),
            ARRAY_FILTER_USE_KEY,
        );
        self::assertCount(1, $rychloregistrace, implode("\n", array_keys($formulare)));
        self::assertNull(reset($rychloregistrace));
    }

    /**
     * Bez přihlášení (vypršela session, odhlášení v jiné záložce) formulář nezpracuje žádný modul a o přepnutém
     * pracovním uživateli se nemá co hlásit.
     *
     * @test
     */
    public function formularPoVyprseniPrihlaseniNehlasiPrepnutiPracovnihoUzivatele(): void
    {
        $bezPrihlaseni = (string) tempnam(sys_get_temp_dir(), 'cookie');
        $this->kUklidu[] = $bezPrihlaseni;

        self::assertSame(200, $this->odesli($bezPrihlaseni, 'uzivatel', [
            'zmenitUdaj'                         => 'uložit',
            'udaj[telefon_uzivatele]'            => '999999999',
            FormularPracovnihoUzivatele::POST_ID => self::ID_PRVNIHO,
        ]));

        self::assertSame('111111111', $this->getTelefon(self::ID_PRVNIHO));
        $stranka = $this->stahni($bezPrihlaseni, 'uzivatel');
        self::assertStringContainsString('name="' . Login::PASSWORD_INPUT_NAME . '"', $stranka);
        self::assertStringNotContainsString('formulář patřil jinému uživateli', $stranka);
    }

    /**
     * Hodnota skrytého pole pracovního uživatele v každém POST formuláři výstupu modulu, bez formulářů rámu adminu
     * (hledání uživatele, odhlášení, vzhled). Pole může ležet i mimo formulář, ke kterému patří (atribut `form`).
     *
     * @return array<string, string|null> začátek formuláře => hodnota pole, null bez pole
     */
    private function getIdPracovnihoUzivateleVeFormularich(string $stranka): array
    {
        $dokument = new \DOMDocument();
        $predchoziChyby = libxml_use_internal_errors(true);
        $dokument->loadHTML('<?xml encoding="UTF-8">' . $stranka);
        libxml_clear_errors();
        libxml_use_internal_errors($predchoziChyby);
        $xpath = new \DOMXPath($dokument);
        $pole = sprintf('input[@name="%s"]', FormularPracovnihoUzivatele::POST_ID);

        $formulare = [];
        foreach ($xpath->query('//*[@id="mainContent"]//form[translate(@method, "POST", "post") = "post"]') as $formular) {
            \assert($formular instanceof \DOMElement);
            $poleFormulare = $xpath->query('.//' . $pole, $formular)->item(0);
            if ($poleFormulare === null && $formular->getAttribute('id') !== '') {
                $poleFormulare = $xpath->query(sprintf('//%s[@form="%s"]', $pole, $formular->getAttribute('id')))->item(0);
            }
            $popis = count($formulare) . ': '
                . mb_substr((string) preg_replace('~\s+~', ' ', (string) $dokument->saveHTML($formular)), 0, 300);
            $formulare[$popis] = $poleFormulare instanceof \DOMElement
                ? $poleFormulare->getAttribute('value')
                : null;
        }

        return $formulare;
    }

    private function getTelefon(int $idUzivatele): string
    {
        return (string) dbOneCol(<<<'SQL'
SELECT telefon_uzivatele FROM uzivatele_hodnoty WHERE id_uzivatele = $0
SQL, [
            0 => $idUzivatele,
        ]);
    }

    private function getPokoj(int $idUzivatele): string
    {
        return (string) dbOneCol(<<<'SQL'
SELECT pokoj FROM ubytovani WHERE id_uzivatele = $0 AND rok = $1
SQL, [
            0 => $idUzivatele,
            1 => ROCNIK,
        ]);
    }

    private function getPocetPlateb(int $idUzivatele): int
    {
        return (int) dbOneCol(<<<'SQL'
SELECT COUNT(*) FROM platby WHERE id_uzivatele = $0
SQL, [
            0 => $idUzivatele,
        ]);
    }
}
