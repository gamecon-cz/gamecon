<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Admin\FormularPracovnihoUzivatele;
use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;

/**
 * Šéf infopultu na Infopultu vrátí odjezd, příjezd a přihlášení, které někdo odklikl omylem (přihlášení jen bez
 * objednávek); obyčejný infopulťák tlačítko nevidí a podvržený formulář nic nevrátí. Přes skutečnou administraci.
 *
 * Každý test má vlastního účastníka, aby nezávisel na pořadí testů.
 */
class VraceniPruchoduInfopultemVAdminuTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_SEFA = 9961;
    private const int ID_INFOPULTAKA = 9962;
    private const int ID_VRACENEHO = 9963;
    private const int ID_NEVRACENEHO = 9964;
    /**
     * jen přihlášený, s objednaným předmětem
     */
    private const int ID_S_OBJEDNAVKOU = 9965;
    private const int ID_PREDMETU = 9961;

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
    (%1$d, 'test-vraceni-sef', 'Test', 'Šéf', 'test-vraceni-sef@example.invalid', 'm'),
    (%2$d, 'test-vraceni-infopultak', 'Test', 'Infopulťák', 'test-vraceni-infopultak@example.invalid', 'f'),
    (%3$d, 'test-vraceni-vraceny', 'Test', 'Vrácený', 'test-vraceni-vraceny@example.invalid', 'm'),
    (%4$d, 'test-vraceni-nevraceny', 'Test', 'Nevrácený', 'test-vraceni-nevraceny@example.invalid', 'm'),
    (%5$d, 'test-vraceni-s-objednavkou', 'Test', 'Objednávka', 'test-vraceni-s-objednavkou@example.invalid', 'f')
SQL,
                self::ID_SEFA,
                self::ID_INFOPULTAKA,
                self::ID_VRACENEHO,
                self::ID_NEVRACENEHO,
                self::ID_S_OBJEDNAVKOU,
            ),
            [
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)
SQL,
                [
                    0 => self::ID_S_OBJEDNAVKOU,
                    1 => Role::PRIHLASEN_NA_LETOSNI_GC,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO shop_predmety SET id_predmetu = $0, nazev = 'test vrácení předmět', model_rok = $1,
    kod_predmetu = 'test-vraceni-predmet', cena_aktualni = 100, stav = 1, nabizet_do = NOW(),
    kusu_vyrobeno = 100, typ = $2
SQL,
                [
                    0 => self::ID_PREDMETU,
                    1 => ROCNIK,
                    2 => TypPredmetu::PREDMET,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni) VALUES ($0, $1, $2, 100)
SQL,
                [
                    0 => self::ID_S_OBJEDNAVKOU,
                    1 => self::ID_PREDMETU,
                    2 => ROCNIK,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil)
VALUES ($0, $2, $0), ($0, $3, $0), ($1, $2, $1)
SQL,
                [
                    0 => self::ID_SEFA,
                    1 => self::ID_INFOPULTAKA,
                    2 => Role::LETOSNI_INFOPULT(ROCNIK),
                    3 => Role::SEF_INFOPULTU,
                ],
            ],
            // oba účastníci prošli infopultem a odjeli
            [
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil)
VALUES ($0, $2, $0), ($0, $3, $0), ($0, $4, $0), ($1, $2, $1), ($1, $3, $1), ($1, $4, $1)
SQL,
                [
                    0 => self::ID_VRACENEHO,
                    1 => self::ID_NEVRACENEHO,
                    2 => Role::PRIHLASEN_NA_LETOSNI_GC,
                    3 => Role::PRITOMEN_NA_LETOSNIM_GC,
                    4 => Role::ODJEL_Z_LETOSNIHO_GC,
                ],
            ],
        ];
    }

    /**
     * @test
     */
    public function sefInfopultuVratiOdjezdPrijezdIPrihlaseni(): void
    {
        $session = $this->prihlas('test-vraceni-sef');
        $this->vyberPracovnihoUzivatele($session, self::ID_VRACENEHO);

        $stranka = $this->stahni($session, 'infopult');
        self::assertStringContainsString('name="gcZrusOdjezd"', $stranka);
        self::assertStringNotContainsString('name="gcZrusPrijezd"', $stranka, 'vrátit jde jen poslední krok');

        self::assertSame(303, $this->odesli($session, 'infopult', [
            'gcZrusOdjezd'                       => 'Zrušit odjezd',
            FormularPracovnihoUzivatele::POST_ID => self::ID_VRACENEHO,
        ]));
        self::assertSame(
            [Role::PRIHLASEN_NA_LETOSNI_GC, Role::PRITOMEN_NA_LETOSNIM_GC],
            $this->getRolePruchoduInfopultem(self::ID_VRACENEHO),
        );

        $stranka = $this->stahni($session, 'infopult');
        self::assertStringContainsString('Odjezd zrušen', $stranka);
        self::assertStringNotContainsString('name="gcZrusOdjezd"', $stranka);
        self::assertStringContainsString('name="gcZrusPrijezd"', $stranka);

        self::assertSame(303, $this->odesli($session, 'infopult', [
            'gcZrusPrijezd'                      => 'Zrušit příjezd',
            FormularPracovnihoUzivatele::POST_ID => self::ID_VRACENEHO,
        ]));
        self::assertSame(
            [Role::PRIHLASEN_NA_LETOSNI_GC],
            $this->getRolePruchoduInfopultem(self::ID_VRACENEHO),
        );

        $stranka = $this->stahni($session, 'infopult');
        self::assertStringContainsString('Příjezd zrušen', $stranka);
        self::assertStringNotContainsString('name="gcZrusOdjezd"', $stranka);
        self::assertStringNotContainsString('name="gcZrusPrijezd"', $stranka);
        self::assertMatchesRegularExpression('~name="gcZrusPrihlaseni" value="Zrušit přihlášení"\s*>~', $stranka);

        self::assertSame(303, $this->odesli($session, 'infopult', [
            'gcZrusPrihlaseni'                   => 'Zrušit přihlášení',
            FormularPracovnihoUzivatele::POST_ID => self::ID_VRACENEHO,
        ]));
        self::assertSame([], $this->getRolePruchoduInfopultem(self::ID_VRACENEHO));

        $stranka = $this->stahni($session, 'infopult');
        self::assertStringContainsString('Přihlášení zrušeno', $stranka);
        self::assertStringNotContainsString('name="gcZrusPrihlaseni"', $stranka);
    }

    /**
     * @test
     */
    public function prihlaseniSObjednavkouSefNezrusi(): void
    {
        $session = $this->prihlas('test-vraceni-sef');
        $this->vyberPracovnihoUzivatele($session, self::ID_S_OBJEDNAVKOU);

        $stranka = $this->stahni($session, 'infopult');
        self::assertMatchesRegularExpression('~name="gcZrusPrihlaseni" value="Zrušit přihlášení"\s*disabled~', $stranka);
        self::assertStringContainsString('Účastník už má: objednávky.', $stranka);

        self::assertSame(303, $this->odesli($session, 'infopult', [
            'gcZrusPrihlaseni'                   => 'Zrušit přihlášení',
            FormularPracovnihoUzivatele::POST_ID => self::ID_S_OBJEDNAVKOU,
        ]));
        self::assertSame([Role::PRIHLASEN_NA_LETOSNI_GC], $this->getRolePruchoduInfopultem(self::ID_S_OBJEDNAVKOU));
        self::assertStringContainsString('Odhlásit z GC ho může správce financí', $this->stahni($session, 'infopult'));
    }

    /**
     * @test
     */
    public function infopultakBezSefaOdjezdNevrati(): void
    {
        $session = $this->prihlas('test-vraceni-infopultak');
        $this->vyberPracovnihoUzivatele($session, self::ID_NEVRACENEHO);

        self::assertStringNotContainsString('name="gcZrusOdjezd"', $this->stahni($session, 'infopult'));

        self::assertSame(303, $this->odesli($session, 'infopult', [
            'gcZrusOdjezd'                       => 'Zrušit odjezd',
            FormularPracovnihoUzivatele::POST_ID => self::ID_NEVRACENEHO,
        ]));
        self::assertSame(
            [Role::PRIHLASEN_NA_LETOSNI_GC, Role::PRITOMEN_NA_LETOSNIM_GC, Role::ODJEL_Z_LETOSNIHO_GC],
            $this->getRolePruchoduInfopultem(self::ID_NEVRACENEHO),
        );
        self::assertStringContainsString('jen šéf infopultu', $this->stahni($session, 'infopult'));
    }

    /**
     * @return list<int> role přihlášen, přítomen a odjel, které uživatel má, v tomto pořadí
     */
    private function getRolePruchoduInfopultem(int $idUzivatele): array
    {
        $role = array_map('intval', dbOneArray(<<<'SQL'
SELECT id_role FROM uzivatele_role WHERE id_uzivatele = $0
SQL, [
            0 => $idUzivatele,
        ]));

        return array_values(array_intersect(
            [Role::PRIHLASEN_NA_LETOSNI_GC, Role::PRITOMEN_NA_LETOSNIM_GC, Role::ODJEL_Z_LETOSNIHO_GC],
            $role,
        ));
    }
}
