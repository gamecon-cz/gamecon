<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use App\Enum\ProductTagCode;
use Gamecon\Admin\FormularPracovnihoUzivatele;
use Gamecon\Login\Login;
use Gamecon\Role\Role;
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
    /**
     * přezdívka, která by v neošetřeném JSON uvnitř `<script>` stránku rozbila
     */
    private const int ID_SE_ZLOMYSLNOU_PREZDIVKOU = 9815;

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
    (%5$d, '</script><!--"x', 'Test', 'Zlomyslný', 'test-formular-zlomyslny@example.invalid', '555555555')
SQL,
                self::ID_PRVNIHO,
                self::ID_DRUHEHO,
                self::ID_UKLADAJICIHO,
                self::ID_UBYTOVANEHO,
                self::ID_SE_ZLOMYSLNOU_PREZDIVKOU,
            ),
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
INSERT INTO shop_predmety SET id_predmetu = $0, nazev = 'test formulář ubytování',
    kod_predmetu = 'test-formular-ubytovani', cena_aktualni = 200, stav = 1, nabizet_do = NOW(), popis = ''
SQL,
                [
                    0 => self::ID_UBYTOVANI,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = $1
SQL,
                [
                    0 => self::ID_UBYTOVANI,
                    1 => ProductTagCode::UBYTOVANI->value,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO product_variant (product_id, name, code, capacity, accommodation_day, position, state)
VALUES ($0, 'pátek', 'test-formular-ubytovani', 100, 1, 0, 1)
SQL,
                [
                    0 => self::ID_UBYTOVANI,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO shop_nakupy(id_uzivatele, variant_id, rok, cena_nakupni)
SELECT $0, id, $1, 200 FROM product_variant WHERE code = 'test-formular-ubytovani'
SQL,
                [
                    0 => self::ID_UBYTOVANEHO,
                    1 => ROCNIK,
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
            // ubytování a jídlo jsou komponenty nad API (s ID uživatele v data-customer-id), ne formuláře
            'uzivatel' => 3,
            'infopult' => 8,
        ] as $stranka => $nejmeneFormularu) {
            $formulare = $this->getIdPracovnihoUzivateleVeFormularich($this->stahni($session, $stranka));
            self::assertGreaterThanOrEqual($nejmeneFormularu, count($formulare), $stranka);
            foreach ($formulare as $formular => $idPracovnihoUzivatele) {
                self::assertSame((string) self::ID_UBYTOVANEHO, $idPracovnihoUzivatele, "{$stranka}: {$formular}");
            }
        }
    }

    /**
     * Mřížka prodeje na infopultu prodává tomu, koho stránka v konstantách nese, a jeho jméno ukazuje v hlavičce.
     *
     * @test
     */
    public function mrizkaProdejeNaInfopultuZnaPracovnihoUzivatele(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($session, self::ID_PRVNIHO);

        $uzivatel = $this->getPracovniUzivatelMrizky($this->stahni($session, 'infopult'));

        self::assertSame(self::ID_PRVNIHO, $uzivatel['id']);
        self::assertStringContainsString('První', $uzivatel['nazev']);
    }

    /**
     * @test
     */
    public function prezdivkaPracovnihoUzivateleNerozbijeStrankuMrizky(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($session, self::ID_SE_ZLOMYSLNOU_PREZDIVKOU);

        $html = $this->stahni($session, 'infopult');

        self::assertSame(1, preg_match('~PRACOVNI_UZIVATEL: (.+),\n~', $html, $shoda));
        self::assertStringNotContainsString('</script>', $shoda[1], 'Přezdívka nesmí uzavřít skript');
        self::assertStringNotContainsString('<!--', $shoda[1]);
        self::assertStringContainsString('</script><!--"x', json_decode($shoda[1], true, flags: JSON_THROW_ON_ERROR)['nazev'], 'Přezdívka musí dorazit nezkomolená');
    }

    /**
     * @test
     */
    public function mrizkaProdejeNaInfopultuBezPracovnihoUzivateleProdavaAnonymne(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertNull($this->getPracovniUzivatelMrizky($this->stahni($session, 'infopult')));
    }

    /**
     * @return array{id: int, nazev: string}|null
     */
    private function getPracovniUzivatelMrizky(string $html): ?array
    {
        self::assertSame(1, preg_match('~PRACOVNI_UZIVATEL: (.+),\n~', $html, $shoda), 'Stránka musí mřížce předat pracovního uživatele');

        return json_decode($shoda[1], true, flags: JSON_THROW_ON_ERROR);
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
