<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Admin\VzhledAdminu;
use Gamecon\Admin\VzhledAdminuEnum;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Login\Login;
use Gamecon\Role\Role;
use Gamecon\Uzivatel\PrepnutiNaUzivatele;

/**
 * Infopulťák se v adminu přepne na vypravěče („Přihlásit se jako“) a vyplní za něj prezenci na jeho aktivitě,
 * v klasickém i moderním vzhledu. Přes skutečnou administraci.
 *
 * Testy běží na locale (ENV=local), kde se smí přepnout každý admin na kohokoli
 * (@see \Uzivatel::muzePrepnoutNaJinehoUzivatele()). Omezení infopulťáka jen na vypravěče a partnery tu proto neplatí
 * a tenhle test ho neověří, ověřuje ho tests/Uzivatel/PrepnutiNaUzivateleTest.php. Tenhle ověří cestu od přepínače
 * po uloženou prezenci a zpět, a co přepnutí nesmí udělat s účtem vypravěče.
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
    public function infopultakVyplniPrezenciZaVypraveceAVratiSe(
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

        $prepnuti = $this->odesliSTelem($session, 'infopult', [
            'prihlasitSeJakoUzivatel' => 1,
            'id'                      => self::ID_VYPRAVECE,
        ]);
        self::assertSame(303, $prepnuti['kod']);
        self::assertStringEndsWith('/moje-aktivity', $prepnuti['kam'], 'Po přepnutí na vypravěče patří na Moje aktivity');

        $mojeAktivity = $this->stahni($session, 'moje-aktivity');
        $this->assertVzhled($vzhledVypravece ?? VzhledAdminuEnum::KLASICKY, $mojeAktivity);
        self::assertStringContainsString(self::NAZEV_AKTIVITY, $mojeAktivity);
        $this->assertNicZaDruhehoPriVzhledu($mojeAktivity);
        self::assertStringContainsString(
            '?' . PrepnutiNaUzivatele::PARAMETR_ZPET . '=1"',
            $mojeAktivity,
            'Z prezence vede odkaz zpět na infopulťáka, ne na úvod vypravěče',
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
        self::assertSame(
            0,
            $this->getPocetZobrazeniVeVzhledu(self::ID_VYPRAVECE),
            'Stránky, které viděl infopulťák, by se v reportech vzhledu počítaly vypravěči',
        );

        $zpet = $this->otevri($session, '?' . PrepnutiNaUzivatele::PARAMETR_ZPET . '=1');
        self::assertSame(303, $zpet['kod']);
        // vypravěč na infopult nesmí
        $this->assertVzhled($vzhledInfopultaka, $this->stahni($session, 'infopult'));
    }

    /**
     * Kdo má právo na přepnutí (systémový uživatel má všechna), se přepne na kohokoli, třeba na jiného admina,
     * a zůstane na stránce, odkud se přepnul. Ani jemu se za druhého nenabídne výběr vzhledu, ani přepínač.
     *
     * @test
     */
    public function sPravemNaPrepnutiSePrepneNaJinehoAdminaANevoliZaNejVzhled(): void
    {
        $this->setVzhled(\Uzivatel::SYSTEM, VzhledAdminuEnum::MODERNI);
        // infopulťák smí na záložku Uživatel a vzhled si nezvolil, sám by dialog výběru dostal
        $this->setVzhled(self::ID_INFOPULTAKA, null);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $prepnuti = $this->odesliSTelem($session, 'uzivatel', [
            'prihlasitSeJakoUzivatel' => 1,
            'id'                      => self::ID_INFOPULTAKA,
        ]);
        self::assertSame(303, $prepnuti['kod']);
        self::assertStringEndsWith('/uzivatel', $prepnuti['kam']);

        $stranka = $this->stahni($session, 'uzivatel');
        self::assertStringContainsString('test-infopultak', $stranka, 'Přihlášený má být infopulťák');
        $this->assertNicZaDruhehoPriVzhledu($stranka);
        self::assertStringContainsString('?' . PrepnutiNaUzivatele::PARAMETR_ZPET . '=1"', $stranka);
    }

    /**
     * Odhlášení po přepnutí odhlásí infopulťáka, který sedí u zařízení. Vypravěči by zrušilo trvalé přihlášení
     * i na jeho vlastním telefonu.
     *
     * @test
     */
    public function odhlaseniPoPrepnutiNechaVypraveciTrvalePrihlaseni(): void
    {
        $this->setVzhled(self::ID_INFOPULTAKA, VzhledAdminuEnum::KLASICKY);
        $tokenInfopultaka = $this->setTrvalePrihlaseni(self::ID_INFOPULTAKA);
        $tokenVypravece = $this->setTrvalePrihlaseni(self::ID_VYPRAVECE);
        $session = $this->prihlas('test-infopultak');
        self::assertSame(303, $this->odesli($session, 'infopult', [
            'prihlasitSeJakoUzivatel' => 1,
            'id'                      => self::ID_VYPRAVECE,
        ]));

        self::assertSame(303, $this->odesli($session, 'moje-aktivity', [
            'odhlasNAdm' => 1,
        ]));

        self::assertSame($tokenVypravece, $this->getTrvalePrihlaseni(self::ID_VYPRAVECE));
        self::assertNotSame($tokenInfopultaka, $this->getTrvalePrihlaseni(self::ID_INFOPULTAKA));
        self::assertStringContainsString(
            'name="' . Login::PASSWORD_INPUT_NAME . '"',
            $this->stahni($session, 'uzivatel'),
            'Odhlášený je i infopulťák',
        );
    }

    /**
     * Přepínač zůstane na stránce, i když infopulťákovi mezitím vypršela session nebo se odhlásil v jiné záložce.
     *
     * @test
     */
    public function prepnutiBezPrihlaseniUkazePrihlaseni(): void
    {
        $bezPrihlaseni = (string) tempnam(sys_get_temp_dir(), 'cookie');
        $this->kUklidu[] = $bezPrihlaseni;

        $odpoved = $this->odesliSTelem($bezPrihlaseni, 'infopult', [
            'prihlasitSeJakoUzivatel' => 1,
            'id'                      => self::ID_VYPRAVECE,
        ]);

        self::assertSame(200, $odpoved['kod'], $odpoved['telo']);
        self::assertStringContainsString('name="' . Login::PASSWORD_INPUT_NAME . '"', $odpoved['telo']);
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

    private function assertVzhled(VzhledAdminuEnum $vzhled, string $stranka): void
    {
        // moderní rám adminu i moderní online prezence mají třídu na <body>, klasické ne
        $jeModerni = str_contains($stranka, '<body class="vzhled-moderni">');
        self::assertSame($vzhled === VzhledAdminuEnum::MODERNI, $jeModerni, "Stránka nemá vzhled {$vzhled->getNazev()}");
    }

    /**
     * Po přepnutí nesmí ten, kdo sedí u zařízení, zvolit vzhled za druhého: dialog by mu překryl stránku (třeba
     * prezenci), dokud by nevybral, a volba z dialogu i z přepínače v patičce by se uložila druhému.
     */
    private function assertNicZaDruhehoPriVzhledu(string $stranka): void
    {
        self::assertStringNotContainsString('id="vyberVzhledu"', $stranka, 'Dialog výběru vzhledu za druhého');
        self::assertStringNotContainsString('class="prepinac-vzhledu"', $stranka, 'Přepínač vzhledu za druhého');
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
        $session = [];
        (new VzhledAdminu($session))->setVzhled($idUzivatele, $vzhled);
    }

    private function getPocetZobrazeniVeVzhledu(int $idUzivatele): int
    {
        return (int) dbOneCol(<<<'SQL'
SELECT COUNT(*) FROM uzivatele_vzhled_adminu_pouziti WHERE id_uzivatele = $0
SQL, [
            0 => $idUzivatele,
        ]);
    }

    /**
     * @return string hodnota cookie trvalého přihlášení, jak ji má uživatel v prohlížeči
     */
    private function setTrvalePrihlaseni(int $idUzivatele): string
    {
        $token = randHex(20);
        dbQuery(<<<'SQL'
UPDATE uzivatele_hodnoty SET random = $0 WHERE id_uzivatele = $1
SQL, [
            0 => $token,
            1 => $idUzivatele,
        ]);

        return $token;
    }

    private function getTrvalePrihlaseni(int $idUzivatele): string
    {
        return (string) dbOneCol(<<<'SQL'
SELECT random FROM uzivatele_hodnoty WHERE id_uzivatele = $0
SQL, [
            0 => $idUzivatele,
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
