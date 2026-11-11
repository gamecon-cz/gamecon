<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Admin\VzhledAdminuEnum;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Role\Role;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Infopulťák se v adminu přepne na vypravěče („Přihlásit se jako“) a vyplní za něj prezenci na jeho aktivitě,
 * v klasickém i moderním vzhledu. Přes skutečnou administraci.
 *
 * Testy běží na locale (ENV=local), kde se smí přepnout každý admin na kohokoli
 * (@see \Uzivatel::muzePrepnoutNaJinehoUzivatele()). Omezení infopulťáka jen na vypravěče a partnery tu proto neplatí
 * a tenhle test ho neověří; ověří cestu od přepínače po uloženou prezenci.
 */
class PrepnutiInfopultuNaVypraveceTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_INFOPULTAKA = 9831;
    private const int ID_VYPRAVECE = 9832;
    private const int ID_UCASTNIKA = 9833;
    private const int ID_AKTIVITY = 9831;
    private const string NAZEV_AKTIVITY = 'Test prezence za vypravěče';

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
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele, pohlavi)
VALUES
    (%1$d, 'test-infopultak', 'Test', 'Infopulťák', 'test-infopultak@example.invalid', 'f'),
    (%2$d, 'test-vypravec', 'Test', 'Vypravěč', 'test-vypravec@example.invalid', 'm'),
    (%3$d, 'test-ucastnik', 'Test', 'Účastník', 'test-ucastnik@example.invalid', 'm')
SQL,
                self::ID_INFOPULTAKA,
                self::ID_VYPRAVECE,
                self::ID_UCASTNIKA,
            ),
            [
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0), ($2, $3, $2), ($4, $5, $4)
SQL,
                [
                    0 => self::ID_INFOPULTAKA,
                    1 => Role::LETOSNI_INFOPULT(ROCNIK),
                    2 => self::ID_VYPRAVECE,
                    3 => Role::LETOSNI_VYPRAVEC(ROCNIK),
                    4 => self::ID_UCASTNIKA,
                    5 => Role::PRIHLASEN_NA_LETOSNI_GC,
                ],
            ],
            // aktivita právě začíná, prezenci tak jde vyplnit
            [
                <<<'SQL'
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m, zacatek, konec)
VALUES ($0, $1, 'test-prezence-za-vypravece', 1, $2, $3, 'Popis', 0, 5, 0, 0,
        DATE_ADD(NOW(), INTERVAL 10 MINUTE), DATE_ADD(NOW(), INTERVAL 70 MINUTE))
SQL,
                [
                    0 => self::ID_AKTIVITY,
                    1 => self::NAZEV_AKTIVITY,
                    2 => ROCNIK,
                    3 => StavAktivity::AKTIVOVANA,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES ($0, $1)
SQL,
                [
                    0 => self::ID_AKTIVITY,
                    1 => self::ID_VYPRAVECE,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO akce_prihlaseni(id_akce, id_uzivatele, id_stavu_prihlaseni) VALUES ($0, $1, $2)
SQL,
                [
                    0 => self::ID_AKTIVITY,
                    1 => self::ID_UCASTNIKA,
                    2 => StavPrihlaseni::PRIHLASEN,
                ],
            ],
            // GameCon běží (a s ním registrace aktivit), jinak prezence příchod účastníka nepřijme. Apache čas
            // podstrčit nejde, proto termín kolem skutečného teď.
            <<<'SQL'
UPDATE systemove_nastaveni
SET vlastni = 1, hodnota = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 DAY), '%Y-%m-%d %H:%i:%s')
WHERE klic = 'GC_BEZI_OD'
SQL,
            <<<'SQL'
UPDATE systemove_nastaveni
SET vlastni = 1, hodnota = DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 DAY), '%Y-%m-%d %H:%i:%s')
WHERE klic = 'GC_BEZI_DO'
SQL,
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
     * Vzhled infopulťáka (rám s přepínačem) a vypravěče (Moje aktivity s prezencí). Infopulťák má vzhled zvolený,
     * jinak by mu dialog výběru vyskočil už na infopultu; vypravěč většinou ne, do adminu skoro nechodí.
     *
     * @return array<string, array{VzhledAdminuEnum, VzhledAdminuEnum|null}>
     */
    public static function vzhledy(): array
    {
        return [
            'klasický infopult, vypravěč vzhled nezvolil' => [VzhledAdminuEnum::KLASICKY, null],
            'moderní infopult, vypravěč vzhled nezvolil'  => [VzhledAdminuEnum::MODERNI, null],
            'klasický infopult, moderní vypravěč'         => [VzhledAdminuEnum::KLASICKY, VzhledAdminuEnum::MODERNI],
            'moderní infopult, klasický vypravěč'         => [VzhledAdminuEnum::MODERNI, VzhledAdminuEnum::KLASICKY],
        ];
    }

    /**
     * @test
     *
     * @dataProvider vzhledy
     */
    public function infopultakVyplniPrezenciZaVypravece(
        VzhledAdminuEnum $vzhledInfopultaka,
        ?VzhledAdminuEnum $vzhledVypravece,
    ): void {
        $this->setVzhled(self::ID_INFOPULTAKA, $vzhledInfopultaka);
        $this->setVzhled(self::ID_VYPRAVECE, $vzhledVypravece);
        $this->setStavUcastnika(StavPrihlaseni::PRIHLASEN);
        $session = $this->prihlas('test-infopultak');

        $infopult = $this->stahni($session, 'infopult');
        $this->assertVzhled($vzhledInfopultaka, $infopult);
        self::assertStringContainsString('name="prihlasitSeJakoUzivatel"', $infopult, 'Infopulťák nevidí přepínač');
        self::assertContains(self::ID_VYPRAVECE, $this->najdiOmniboxemPrepinace($session, $infopult, 'Vypravěč'));

        $prepnuti = $this->odesliAZjistiPresmerovani($session, 'infopult', [
            'prihlasitSeJakoUzivatel' => 1,
            'id'                      => self::ID_VYPRAVECE,
        ]);
        self::assertSame(303, $prepnuti['kod']);
        self::assertStringEndsWith('/moje-aktivity', $prepnuti['kam'], 'Po přepnutí na vypravěče patří na Moje aktivity');

        $mojeAktivity = $this->stahni($session, 'moje-aktivity');
        $this->assertVzhled($vzhledVypravece ?? VzhledAdminuEnum::KLASICKY, $mojeAktivity);
        self::assertStringContainsString(self::NAZEV_AKTIVITY, $mojeAktivity);
        self::assertStringNotContainsString(
            'id="vyberVzhledu"',
            $mojeAktivity,
            'Dialog výběru vzhledu by infopulťákovi překryl prezenci, dokud by za vypravěče nezvolil vzhled',
        );

        $ulozeni = $this->odesliSTelem($session, 'moje-aktivity', [
            OnlinePrezenceAjax::AJAX => 1,
            'akce'                   => 'zmenitPritomnostUcastnika',
            'idUcastnika'            => self::ID_UCASTNIKA,
            'idAktivity'             => self::ID_AKTIVITY,
            'dorazil'                => 1,
        ]);
        self::assertSame(200, $ulozeni['kod'], $ulozeni['telo']);
        $odpoved = json_decode($ulozeni['telo'], true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($odpoved[OnlinePrezenceAjax::PRIHLASEN] ?? null, $ulozeni['telo']);
        self::assertSame(StavPrihlaseni::PRIHLASEN_A_DORAZIL, $this->getStavUcastnika());
    }

    /**
     * Kdo má právo na přepnutí (systémový uživatel má všechna), se přepne na kohokoli, třeba na účastníka bez práv
     * v adminu, a zůstane na stránce, odkud se přepnul. Ani jemu se nenabídne výběr vzhledu za druhého.
     *
     * @test
     */
    public function sPravemNaPrepnutiSePrepneNaKohokoli(): void
    {
        $this->setVzhled(\Uzivatel::SYSTEM, VzhledAdminuEnum::MODERNI);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $prepnuti = $this->odesliAZjistiPresmerovani($session, 'uzivatel', [
            'prihlasitSeJakoUzivatel' => 1,
            'id'                      => self::ID_UCASTNIKA,
        ]);
        self::assertSame(303, $prepnuti['kod']);
        self::assertStringEndsWith('/uzivatel', $prepnuti['kam']);

        // účastník do adminu nesmí, dostane stránku 403, ale přihlášený je on
        $curl = $this->curl($session, 'uzivatel');
        $stranka = (string) curl_exec($curl);
        self::assertSame(403, curl_getinfo($curl, CURLINFO_HTTP_CODE));
        self::assertStringContainsString('test-ucastnik', $stranka);
        self::assertStringNotContainsString('id="vyberVzhledu"', $stranka);
    }

    /**
     * ID uživatelů, které omnibox přepínače najde, s parametry, které mu dala stránka (infopulťákovi bez práva
     * na přepnutí jen vypravěče a partnery), jako by je poslal našeptávač (admin/files/omnibox.js).
     *
     * @return list<int>
     */
    private function najdiOmniboxemPrepinace(string $cookieFile, string $stranka, string $hledany): array
    {
        self::assertSame(
            1,
            preg_match('~id="omniboxUzivatel"[^>]*data-omnibox="([^"]*)"~', $stranka, $shoda),
            'Na stránce chybí omnibox přepínače',
        );
        $parametry = json_decode(html_entity_decode($shoda[1], ENT_QUOTES | ENT_HTML5), true, 512, JSON_THROW_ON_ERROR);
        $nalezeni = json_decode(
            $this->stahni($cookieFile, 'ajax-omnibox?' . http_build_query([
                'term' => $hledany,
                ...$parametry,
            ])),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return array_map(static fn (array $nalezeny): int => (int) $nalezeny['value'], $nalezeni);
    }

    /**
     * @param array<string, mixed> $pole
     *
     * @return array{kod: int, kam: string}
     */
    private function odesliAZjistiPresmerovani(string $cookieFile, string $url, array $pole): array
    {
        $curl = $this->curl($cookieFile, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $pole);
        self::assertNotFalse(curl_exec($curl), curl_error($curl));

        return [
            'kod' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'kam' => (string) curl_getinfo($curl, CURLINFO_REDIRECT_URL),
        ];
    }

    private function assertVzhled(VzhledAdminuEnum $vzhled, string $stranka): void
    {
        // moderní rám adminu i moderní online prezence mají třídu na <body>, klasické ne
        $jeModerni = str_contains($stranka, '<body class="vzhled-moderni">');
        self::assertSame($vzhled === VzhledAdminuEnum::MODERNI, $jeModerni, "Stránka nemá vzhled {$vzhled->getNazev()}");
    }

    private function setVzhled(int $idUzivatele, ?VzhledAdminuEnum $vzhled): void
    {
        if ($vzhled === null) {
            dbQuery(<<<'SQL'
DELETE FROM uzivatele_vzhled_adminu WHERE id_uzivatele = $0
SQL, [
                0 => $idUzivatele,
            ]);

            return;
        }
        dbQuery(<<<'SQL'
INSERT INTO uzivatele_vzhled_adminu (id_uzivatele, vzhled) VALUES ($0, $1)
ON DUPLICATE KEY UPDATE vzhled = VALUES(vzhled)
SQL, [
            0 => $idUzivatele,
            1 => $vzhled->value,
        ]);
    }

    private function setStavUcastnika(int $stav): void
    {
        dbQuery(<<<'SQL'
UPDATE akce_prihlaseni SET id_stavu_prihlaseni = $0 WHERE id_akce = $1 AND id_uzivatele = $2
SQL, [
            0 => $stav,
            1 => self::ID_AKTIVITY,
            2 => self::ID_UCASTNIKA,
        ]);
    }

    private function getStavUcastnika(): int
    {
        return (int) dbOneCol(<<<'SQL'
SELECT id_stavu_prihlaseni FROM akce_prihlaseni WHERE id_akce = $0 AND id_uzivatele = $1
SQL, [
            0 => self::ID_AKTIVITY,
            1 => self::ID_UCASTNIKA,
        ]);
    }
}
