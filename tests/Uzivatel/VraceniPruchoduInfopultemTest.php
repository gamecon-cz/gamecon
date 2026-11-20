<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Šéf infopultu vrátí přihlášení, příjezd nebo odjezd, který někdo odklikl omylem, a to jen poslední krok. Nikdo jiný
 * ne. Přihlášení jen dokud na něm nic nevisí.
 */
class VraceniPruchoduInfopultemTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function sefInfopultuVratiOdjezd(): void
    {
        $sef = $this->vytvorUzivatele('sef-odjezd', [Role::SEF_INFOPULTU]);
        $ucastnik = $this->vytvorUzivatele('odjel', [
            Role::PRIHLASEN_NA_LETOSNI_GC,
            Role::PRITOMEN_NA_LETOSNIM_GC,
            Role::ODJEL_Z_LETOSNIHO_GC,
        ]);

        $ucastnik->gcZrusOdjezd($sef);

        $ulozeny = \Uzivatel::zIdUrcite($ucastnik->id());
        self::assertFalse($ulozeny->gcOdjel());
        self::assertTrue($ulozeny->gcPritomen());
        self::assertTrue($ulozeny->gcPrihlasen());
        self::assertSame(
            [$sef->id(), \Uzivatel::SESAZEN],
            $this->getPosledniZmenaRole($ucastnik->id(), Role::ODJEL_Z_LETOSNIHO_GC),
            'v logu rolí je, kdo odjezd vrátil',
        );
    }

    /**
     * @test
     */
    public function sefInfopultuVratiPrijezd(): void
    {
        $sef = $this->vytvorUzivatele('sef-prijezd', [Role::SEF_INFOPULTU]);
        $ucastnik = $this->vytvorUzivatele('prijel', [
            Role::PRIHLASEN_NA_LETOSNI_GC,
            Role::PRITOMEN_NA_LETOSNIM_GC,
        ]);

        $ucastnik->gcZrusPrijezd($sef);

        $ulozeny = \Uzivatel::zIdUrcite($ucastnik->id());
        self::assertFalse($ulozeny->gcPritomen());
        self::assertTrue($ulozeny->gcPrihlasen(), 'na GC zůstane přihlášený, příjezd není odhlášení');
        self::assertSame(
            [$sef->id(), \Uzivatel::SESAZEN],
            $this->getPosledniZmenaRole($ucastnik->id(), Role::PRITOMEN_NA_LETOSNIM_GC),
        );
    }

    /**
     * @test
     */
    public function prijezdNejdeVratitDrivNezOdjezd(): void
    {
        $sef = $this->vytvorUzivatele('sef-poradi', [Role::SEF_INFOPULTU]);
        $ucastnik = $this->vytvorUzivatele('poradi', [
            Role::PRIHLASEN_NA_LETOSNI_GC,
            Role::PRITOMEN_NA_LETOSNIM_GC,
            Role::ODJEL_Z_LETOSNIHO_GC,
        ]);

        try {
            $ucastnik->gcZrusPrijezd($sef);
            self::fail('Příjezd odjetého účastníka nejde vrátit');
        } catch (\Chyba $chyba) {
            self::assertStringContainsString('nejdřív vrať odjezd', $chyba->getMessage());
        }

        $ulozeny = \Uzivatel::zIdUrcite($ucastnik->id());
        self::assertTrue($ulozeny->gcPritomen());
        self::assertTrue($ulozeny->gcOdjel());
    }

    /**
     * @return array<string, array{string, list<int>}>
     */
    public static function vraceniPoslednihoKroku(): array
    {
        return [
            'odjezd' => ['gcZrusOdjezd', [
                Role::PRIHLASEN_NA_LETOSNI_GC,
                Role::PRITOMEN_NA_LETOSNIM_GC,
                Role::ODJEL_Z_LETOSNIHO_GC,
            ]],
            'příjezd' => ['gcZrusPrijezd', [
                Role::PRIHLASEN_NA_LETOSNI_GC,
                Role::PRITOMEN_NA_LETOSNIM_GC,
            ]],
            'přihlášení' => ['gcZrusPrihlaseni', [
                Role::PRIHLASEN_NA_LETOSNI_GC,
            ]],
        ];
    }

    /**
     * @test
     *
     * @dataProvider vraceniPoslednihoKroku
     *
     * @param list<int> $roleUcastnika
     */
    public function infopultakBezSefaNicNevrati(string $vraceni, array $roleUcastnika): void
    {
        $infopultak = $this->vytvorUzivatele('infopultak-' . $vraceni, [Role::LETOSNI_INFOPULT(ROCNIK)]);
        $ucastnik = $this->vytvorUzivatele('nevraceny-' . $vraceni, $roleUcastnika);

        try {
            $ucastnik->{$vraceni}($infopultak);
            self::fail('Vracet smí jen šéf infopultu');
        } catch (\Chyba $chyba) {
            self::assertStringContainsString('jen šéf infopultu', $chyba->getMessage());
        }

        self::assertEqualsCanonicalizing(
            $roleUcastnika,
            array_values(array_intersect($roleUcastnika, \Uzivatel::zIdUrcite($ucastnik->id())->dejIdsRoli())),
            'účastníkovi zůstanou všechny role průchodu infopultem',
        );
        self::assertFalse($infopultak->isOpravnenVracetStavNaGc());
    }

    /**
     * @return array<string, array{string, list<int>}>
     */
    public static function vraceniKrokuKteryNeudelal(): array
    {
        return [
            'odjezd přihlášeného'  => ['gcZrusOdjezd', [Role::PRIHLASEN_NA_LETOSNI_GC]],
            'příjezd přihlášeného' => ['gcZrusPrijezd', [Role::PRIHLASEN_NA_LETOSNI_GC]],
            'přihlášení nikoho'    => ['gcZrusPrihlaseni', []],
        ];
    }

    /**
     * @test
     *
     * @dataProvider vraceniKrokuKteryNeudelal
     *
     * @param list<int> $roleUcastnika
     */
    public function krokKteryNeudelalNejdeVratit(string $vraceni, array $roleUcastnika): void
    {
        $sef = $this->vytvorUzivatele('sef-nic-' . $vraceni, [Role::SEF_INFOPULTU]);
        $ucastnik = $this->vytvorUzivatele('bez-kroku-' . $vraceni, $roleUcastnika);

        try {
            $ucastnik->{$vraceni}($sef);
            self::fail('Není co vracet');
        } catch (\Chyba $chyba) {
            self::assertStringContainsString('není co vracet', $chyba->getMessage());
        }

        self::assertSame(
            $roleUcastnika !== [],
            \Uzivatel::zIdUrcite($ucastnik->id())->gcPrihlasen(),
        );
    }

    /**
     * @test
     */
    public function sefInfopultuZrusiPrihlaseniNaKteremNicNevisi(): void
    {
        $sef = $this->vytvorUzivatele('sef-prihlaseni', [Role::SEF_INFOPULTU]);
        $ucastnik = $this->vytvorUzivatele('prihlaseny-omylem', [Role::PRIHLASEN_NA_LETOSNI_GC]);
        // loňská platba s letošním přihlášením nesouvisí
        $this->pridejPlatbu($ucastnik->id(), ROCNIK - 1);

        self::assertSame([], $ucastnik->getPrekazkyZruseniPrihlaseni());
        $ucastnik->gcZrusPrihlaseni($sef);

        self::assertFalse(\Uzivatel::zIdUrcite($ucastnik->id())->gcPrihlasen());
        self::assertSame(
            [$sef->id(), \Uzivatel::SESAZEN],
            $this->getPosledniZmenaRole($ucastnik->id(), Role::PRIHLASEN_NA_LETOSNI_GC),
        );
    }

    /**
     * @test
     */
    public function prihlaseniNejdeZrusitDrivNezPrijezd(): void
    {
        $sef = $this->vytvorUzivatele('sef-prihlaseni-poradi', [Role::SEF_INFOPULTU]);
        $ucastnik = $this->vytvorUzivatele('prihlaseni-poradi', [
            Role::PRIHLASEN_NA_LETOSNI_GC,
            Role::PRITOMEN_NA_LETOSNIM_GC,
        ]);

        try {
            $ucastnik->gcZrusPrihlaseni($sef);
            self::fail('Přihlášení přítomného účastníka nejde zrušit');
        } catch (\Chyba $chyba) {
            self::assertStringContainsString('nejdřív vrať příjezd', $chyba->getMessage());
        }

        self::assertTrue(\Uzivatel::zIdUrcite($ucastnik->id())->gcPrihlasen());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function coNaPrihlaseniVisi(): array
    {
        return [
            'aktivita'           => ['aktivita', 'aktivity'],
            'sledovaná aktivita' => ['sledovana', 'aktivity'],
            'vedení aktivity'    => ['vede', 'vedené aktivity'],
            'objednávka'         => ['objednavka', 'objednávky'],
            'platba'             => ['platba', 'platby'],
        ];
    }

    /**
     * Zrušení by tu bylo odhlášením z GC se vším, co ruší, a to patří správci financí.
     *
     * @test
     *
     * @dataProvider coNaPrihlaseniVisi
     */
    public function prihlaseniNaKteremNecoVisiNejdeZrusit(string $co, string $prekazka): void
    {
        $sef = $this->vytvorUzivatele('sef-visi-' . $co, [Role::SEF_INFOPULTU]);
        $ucastnik = $this->vytvorUzivatele('visi-' . $co, [Role::PRIHLASEN_NA_LETOSNI_GC]);
        match ($co) {
            'aktivita'   => $this->prihlasNaAktivitu($ucastnik->id(), StavPrihlaseni::PRIHLASEN),
            'sledovana'  => $this->prihlasNaAktivitu($ucastnik->id(), StavPrihlaseni::SLEDUJICI),
            'vede'       => $this->pridejVedeniAktivity($ucastnik->id()),
            'objednavka' => $this->pridejObjednavku($ucastnik->id()),
            'platba'     => $this->pridejPlatbu($ucastnik->id(), ROCNIK),
        };

        self::assertSame([$prekazka], $ucastnik->getPrekazkyZruseniPrihlaseni());
        try {
            $ucastnik->gcZrusPrihlaseni($sef);
            self::fail('Přihlášení, na kterém něco visí, nejde zrušit');
        } catch (\Chyba $chyba) {
            self::assertStringContainsString($prekazka, $chyba->getMessage());
            self::assertStringContainsString('správce financí', $chyba->getMessage());
        }

        self::assertTrue(\Uzivatel::zIdUrcite($ucastnik->id())->gcPrihlasen());
    }

    private function prihlasNaAktivitu(int $idUzivatele, int $stavPrihlaseni): void
    {
        $idAktivity = $this->vytvorAktivitu($idUzivatele);
        $tabulka = $stavPrihlaseni === StavPrihlaseni::SLEDUJICI
            ? 'akce_prihlaseni_spec'
            : 'akce_prihlaseni';
        dbQuery(
            <<<SQL
INSERT INTO {$tabulka}(id_akce, id_uzivatele, id_stavu_prihlaseni) VALUES ($0, $1, $2)
SQL,
            [
                0 => $idAktivity,
                1 => $idUzivatele,
                2 => $stavPrihlaseni,
            ],
        );
    }

    private function pridejVedeniAktivity(int $idUzivatele): void
    {
        dbQuery(
            <<<'SQL'
INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES ($0, $1)
SQL,
            [
                0 => $this->vytvorAktivitu($idUzivatele),
                1 => $idUzivatele,
            ],
        );
    }

    /**
     * @return int id letošní aktivity
     */
    private function vytvorAktivitu(int $idUzivatele): int
    {
        dbQuery(
            <<<'SQL'
INSERT INTO akce_seznam(nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m, zacatek, konec)
VALUES ($0, $0, 1, $1, $2, 'Popis', 0, 5, 0, 0, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))
SQL,
            [
                0 => 'test-vraceni-aktivita-' . $idUzivatele,
                1 => ROCNIK,
                2 => StavAktivity::AKTIVOVANA,
            ],
        );

        return (int) dbInsertId();
    }

    private function pridejObjednavku(int $idUzivatele): void
    {
        dbQuery(
            <<<'SQL'
INSERT INTO shop_predmety SET nazev = $0, kod_predmetu = $0, model_rok = $1, cena_aktualni = 100, stav = 1,
    nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $2
SQL,
            [
                0 => 'test-vraceni-predmet-' . $idUzivatele,
                1 => ROCNIK,
                2 => TypPredmetu::PREDMET,
            ],
        );
        dbQuery(
            <<<'SQL'
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni) VALUES ($0, $1, $2, 100)
SQL,
            [
                0 => $idUzivatele,
                1 => dbInsertId(),
                2 => ROCNIK,
            ],
        );
    }

    private function pridejPlatbu(int $idUzivatele, int $rok): void
    {
        dbQuery(
            <<<'SQL'
INSERT INTO platby(id_uzivatele, castka, rok, provedeno, provedl) VALUES ($0, 100, $1, NOW(), $0)
SQL,
            [
                0 => $idUzivatele,
                1 => $rok,
            ],
        );
    }

    /**
     * @param list<int> $role
     */
    private function vytvorUzivatele(string $login, array $role): \Uzivatel
    {
        dbQuery(
            <<<'SQL'
INSERT INTO uzivatele_hodnoty SET login_uzivatele = $0, email1_uzivatele = $1, jmeno_uzivatele = 'Test', prijmeni_uzivatele = $0
SQL,
            [
                0 => 'test-vraceni-' . $login,
                1 => 'test-vraceni-' . $login . '@example.invalid',
            ],
        );
        $idUzivatele = dbInsertId();
        foreach ($role as $idRole) {
            dbQuery(
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)
SQL,
                [
                    0 => $idUzivatele,
                    1 => $idRole,
                ],
            );
        }

        return \Uzivatel::zIdUrcite($idUzivatele);
    }

    /**
     * @return array{int, string}|null kdo a jak roli změnil naposledy
     */
    private function getPosledniZmenaRole(int $idUzivatele, int $idRole): ?array
    {
        $zmena = dbOneLine(
            <<<'SQL'
SELECT id_zmenil, zmena FROM uzivatele_role_log WHERE id_uzivatele = $0 AND id_role = $1 ORDER BY id DESC LIMIT 1
SQL,
            [
                0 => $idUzivatele,
                1 => $idRole,
            ],
        );

        return $zmena
            ? [(int) $zmena['id_zmenil'], $zmena['zmena']]
            : null;
    }
}
