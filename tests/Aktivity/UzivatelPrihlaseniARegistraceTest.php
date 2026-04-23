<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Role\Role;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Registrace;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;
use Gamecon\Uzivatel\ZpusobZobrazeniNaWebu;

/**
 * Testy pokrývající metody na přihlášení a registraci.
 */
class UzivatelPrihlaseniARegistraceTest extends AbstractTestDb
{
    private static int $poradiUzivatelu = 0;

    private static $uzivatelTab = [
        Sql::EMAIL1_UZIVATELE       => 'a@b.c',
        Sql::TELEFON_UZIVATELE      => '1',
        Sql::JMENO_UZIVATELE        => 'a',
        Sql::PRIJMENI_UZIVATELE     => 'b',
        Sql::ULICE_A_CP_UZIVATELE   => 'a 1',
        Sql::MESTO_UZIVATELE        => 'a',
        Sql::PSC_UZIVATELE          => '1',
        Sql::STAT_UZIVATELE         => '1',
        Sql::DATUM_NAROZENI         => '2000-01-01',
        Sql::STATNI_OBCANSTVI       => 'ČR',
        Sql::TYP_DOKLADU_TOTOZNOSTI => \Uzivatel::TYP_DOKLADU_OP,
        Sql::OP                     => '998009476',
        Sql::LOGIN_UZIVATELE        => 'a',
        Sql::POHLAVI                => 'f',
        'heslo'                     => 'a',
        'heslo_kontrola'            => 'a',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // "oběť" pro testy kolizí
        \Uzivatel::registruj(array_merge(self::$uzivatelTab, [
            Sql::LOGIN_UZIVATELE  => 'login@obeti.cz',
            Sql::EMAIL1_UZIVATELE => 'email@obeti.cz',
        ]));
    }

    private function uzivatel($prepis = [])
    {
        return array_merge(self::$uzivatelTab, $prepis);
    }

    private function novyUzivatel(array $prepis = []): \Uzivatel
    {
        ++self::$poradiUzivatelu;
        $poradi = self::$poradiUzivatelu;
        $id = \Uzivatel::registruj($this->uzivatel([
            Sql::LOGIN_UZIVATELE  => "uzivatel-{$poradi}",
            Sql::EMAIL1_UZIVATELE => "uzivatel-{$poradi}@example.cz",
            ...$prepis,
        ]));
        $uzivatel = \Uzivatel::zId($id);
        self::assertNotNull($uzivatel);

        return $uzivatel;
    }

    public function testRegistrujAPrihlas()
    {
        $id = \Uzivatel::registruj($this->uzivatel());

        $this->assertNotNull(\Uzivatel::prihlas('a', 'a'), 'přihlášení loginem');
        $this->assertNotNull(\Uzivatel::prihlas('a@b.c', 'a'), 'přihlášení heslem');
        $this->assertNull(\Uzivatel::prihlas('a', 'b'), 'nepřihlášení špatnými údaji');
        $this->assertSame(
            ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA->value,
            (int) \Uzivatel::zId($id)->rawDb()[Sql::ZPUSOB_ZOBRAZENI_NA_WEBU],
        );
    }

    public static function provideRegistrujDuplicity()
    {
        return [
            ['nekolizni_login', 'email@obeti.cz', 'email1_uzivatele', '/e-mail.*zaregistrovaný/'],
            ['nekolizni_login', 'login@obeti.cz', 'email1_uzivatele', '/e-mail.*zaregistrovaný/'],
            ['login@obeti.cz', 'ok@mail.com', 'login_uzivatele', '/přezdívka.*zabraná/'],
            ['email@obeti.cz', 'ok@mail.com', 'login_uzivatele', '/přezdívka.*zabraná/'],
        ];
    }

    /**
     * @dataProvider provideRegistrujDuplicity
     */
    public function testRegistrujDuplicity($login, $email, $klicChyby, $chyba)
    {
        $e = null;
        try {
            \Uzivatel::registruj($this->uzivatel([
                'login_uzivatele'  => $login,
                'email1_uzivatele' => $email,
            ]));
        } catch (\Exception $e) {
        }

        $this->assertInstanceOf(\Chyby::class, $e);
        $this->assertMatchesRegularExpression($chyba, $e->klic($klicChyby));
    }

    public function testNelzeZadatId()
    {
        try {
            \Uzivatel::registruj($this->uzivatel([
                'id_uzivatele' => 5,
            ]));
            self::fail();
        } catch (\Exception $e) {
            $this->assertMatchesRegularExpression('/ nepovolené /', $e->getMessage());
        }
    }

    public function testUprav()
    {
        $id = \Uzivatel::registruj($this->uzivatel());
        $u = \Uzivatel::zId($id);

        $this->assertEquals('a b', $u->celeJmeno());

        $u->uprav([
            'jmeno_uzivatele' => 'jiné',
        ]);

        $this->assertEquals('jiné b', $u->celeJmeno());

        $u = \Uzivatel::zId($id);
        $this->assertEquals('jiné b', $u->celeJmeno());
    }

    public function testUpravNic()
    {
        $id = \Uzivatel::registruj($this->uzivatel());
        $uData1 = \Uzivatel::zId($id)->rawDb();

        \Uzivatel::zId($id)->uprav([]);
        $uData2 = \Uzivatel::zId($id)->rawDb();

        $nemenitHesloADoklad = [
            'heslo'          => null,
            'heslo_kontrola' => null,
            Sql::OP          => null,
        ];
        \Uzivatel::zId($id)->uprav($this->uzivatel($nemenitHesloADoklad));
        $uData3 = \Uzivatel::zId($id)->rawDb();

        $this->assertSame($uData1, $uData2, 'prázdná úprava data nezměnila');
        $this->assertSame($uData1, $uData3, 'úprava stejnými daty data nezměnila');
    }

    public function testUpravUloziIVychoziNastaveniZobrazeniNaWebu(): void
    {
        $id = \Uzivatel::registruj($this->uzivatel([
            Sql::LOGIN_UZIVATELE          => 'NulaZpet',
            Sql::EMAIL1_UZIVATELE         => 'nula-zpet.' . uniqid('', true) . '@example.com',
            Sql::ZPUSOB_ZOBRAZENI_NA_WEBU => (string) ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI->value,
        ]));

        $u = \Uzivatel::zId($id);
        $u->uprav([
            Sql::ZPUSOB_ZOBRAZENI_NA_WEBU => '0',
        ]);

        self::assertSame(
            ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA->value,
            (int) \Uzivatel::zId($id)->rawDb()[Sql::ZPUSOB_ZOBRAZENI_NA_WEBU],
        );
    }

    public function testJmenoNaWebuRespektujeNastaveni(): void
    {
        $id = \Uzivatel::registruj($this->uzivatel([
            Sql::LOGIN_UZIVATELE    => 'Drak',
            Sql::EMAIL1_UZIVATELE   => 'drak.' . uniqid('', true) . '@example.com',
            Sql::JMENO_UZIVATELE    => 'Jan',
            Sql::PRIJMENI_UZIVATELE => 'Novak',
        ]));

        $u = \Uzivatel::zId($id);
        self::assertSame('Drak', $u->jmenoNaWebu());
        self::assertSame(ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA, $u->zpusobZobrazeniNaWebu());

        \dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, [
            Sql::ZPUSOB_ZOBRAZENI_NA_WEBU => ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI->value,
        ], [
            Sql::ID_UZIVATELE => $id,
        ]);
        self::assertSame('Jan Novak', \Uzivatel::zId($id)->jmenoNaWebu());

        \dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, [
            Sql::ZPUSOB_ZOBRAZENI_NA_WEBU => ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI->value,
        ], [
            Sql::ID_UZIVATELE => $id,
        ]);
        self::assertSame('Jan „Drak" Novak', \Uzivatel::zId($id)->jmenoNaWebu());
    }

    public function testJmenoNaWebuBezPrezdivkyPouzijeFallbackNaCeleJmeno(): void
    {
        $id = \Uzivatel::registruj($this->uzivatel([
            Sql::LOGIN_UZIVATELE    => 'bez.prezdivky.' . uniqid('', true) . '@example.com',
            Sql::EMAIL1_UZIVATELE   => 'kontakt.' . uniqid('', true) . '@example.com',
            Sql::JMENO_UZIVATELE    => 'Jana',
            Sql::PRIJMENI_UZIVATELE => 'Novakova',
        ]));

        self::assertSame('Jana Novakova', \Uzivatel::zId($id)->jmenoNaWebu());

        \dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, [
            Sql::ZPUSOB_ZOBRAZENI_NA_WEBU => ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI->value,
        ], [
            Sql::ID_UZIVATELE => $id,
        ]);
        self::assertSame('Jana Novakova', \Uzivatel::zId($id)->jmenoNaWebu());
    }

    public function testJmenoNaWebuPouzeZLoginuKdyzChybiJmenoAPrezdivkaJeMail(): void
    {
        $email = 'pouze-login.' . uniqid('', true) . '@example.com';
        $id = \Uzivatel::registruj($this->uzivatel([
            Sql::LOGIN_UZIVATELE    => $email,
            Sql::EMAIL1_UZIVATELE   => $email,
            Sql::JMENO_UZIVATELE    => 'Eva',
            Sql::PRIJMENI_UZIVATELE => 'Evová',
        ]));

        // nick je prázdný (login obsahuje @), a POUZE_PREZDIVKA padá na fallback = celé jméno
        self::assertSame('Eva Evová', \Uzivatel::zId($id)->jmenoNaWebu());
    }

    public function testRychloregistraceNastaviVychoziZpusobZobrazeniNaWebu(): void
    {
        $systemoveNastaveni = \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals();
        $id = \Uzivatel::rychloregistrace($systemoveNastaveni, [
            Sql::LOGIN_UZIVATELE => 'RR.' . uniqid('', true),
        ]);

        self::assertSame(
            ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA->value,
            (int) \Uzivatel::zId($id)->rawDb()[Sql::ZPUSOB_ZOBRAZENI_NA_WEBU],
        );
    }

    public function testRegistrujeOdmitneNeplatnyZpusobZobrazeniNaWebu(): void
    {
        try {
            \Uzivatel::registruj($this->uzivatel([
                Sql::LOGIN_UZIVATELE          => 'neplatny.' . uniqid('', true),
                Sql::EMAIL1_UZIVATELE         => 'neplatny.' . uniqid('', true) . '@example.com',
                Sql::ZPUSOB_ZOBRAZENI_NA_WEBU => '3',
            ]));
            self::fail('Mělo vyhodit výjimku kvůli neplatné hodnotě');
        } catch (\Chyby $chyby) {
            self::assertMatchesRegularExpression(
                '/způsob zobrazení na webu/',
                $chyby->klic(Sql::ZPUSOB_ZOBRAZENI_NA_WEBU),
            );
        }
    }

    public function testPoKontroleNelzeZamceneUdajeUpravitPresUpravAniPriSmisenemPayloadu()
    {
        $u = $this->novyUzivatel();
        $u->pridejRoli(Role::ZKONTROLOVANE_UDAJE_NA_LETOSNIM_GC, $u);

        $u->uprav([
            Sql::JMENO_UZIVATELE        => 'NoveJmeno',
            Sql::PRIJMENI_UZIVATELE     => 'NovePrijmeni',
            Sql::DATUM_NAROZENI         => '1999-12-31',
            Sql::ULICE_A_CP_UZIVATELE   => 'Nova 12',
            Sql::MESTO_UZIVATELE        => 'Brno',
            Sql::PSC_UZIVATELE          => '602 00',
            Sql::STAT_UZIVATELE         => (string) \Gamecon\Stat::SK_ID,
            Sql::TYP_DOKLADU_TOTOZNOSTI => \Uzivatel::TYP_DOKLADU_PAS,
            Sql::OP                     => 'NOVY12345',
            Sql::TELEFON_UZIVATELE      => '+420 123 456 789',
        ]);

        $uPoZmene = \Uzivatel::zId($u->id());
        self::assertNotNull($uPoZmene);
        self::assertSame('a', $uPoZmene->rawDb()[Sql::JMENO_UZIVATELE]);
        self::assertSame('b', $uPoZmene->rawDb()[Sql::PRIJMENI_UZIVATELE]);
        self::assertSame('2000-01-01', $uPoZmene->rawDb()[Sql::DATUM_NAROZENI]);
        self::assertSame('a 1', $uPoZmene->rawDb()[Sql::ULICE_A_CP_UZIVATELE]);
        self::assertSame('a', $uPoZmene->rawDb()[Sql::MESTO_UZIVATELE]);
        self::assertSame('1', $uPoZmene->rawDb()[Sql::PSC_UZIVATELE]);
        self::assertSame('1', $uPoZmene->rawDb()[Sql::STAT_UZIVATELE]);
        self::assertSame(\Uzivatel::TYP_DOKLADU_OP, $uPoZmene->rawDb()[Sql::TYP_DOKLADU_TOTOZNOSTI]);
        self::assertSame('998009476', $uPoZmene->cisloOp());
        self::assertSame('+420 123 456 789', $uPoZmene->rawDb()[Sql::TELEFON_UZIVATELE]);
    }

    public function testPredKontrolouLzeZamceneUdajeUpravitPresUprav()
    {
        $u = $this->novyUzivatel();

        $u->uprav([
            Sql::JMENO_UZIVATELE        => 'NoveJmeno',
            Sql::PRIJMENI_UZIVATELE     => 'NovePrijmeni',
            Sql::DATUM_NAROZENI         => '1999-12-31',
            Sql::ULICE_A_CP_UZIVATELE   => 'Nova 12',
            Sql::MESTO_UZIVATELE        => 'Brno',
            Sql::PSC_UZIVATELE          => '602 00',
            Sql::STAT_UZIVATELE         => (string) \Gamecon\Stat::SK_ID,
            Sql::TYP_DOKLADU_TOTOZNOSTI => \Uzivatel::TYP_DOKLADU_PAS,
            Sql::OP                     => 'NOVY12345',
        ]);

        $uPoZmene = \Uzivatel::zId($u->id());
        self::assertNotNull($uPoZmene);
        self::assertSame('NoveJmeno', $uPoZmene->rawDb()[Sql::JMENO_UZIVATELE]);
        self::assertSame('NovePrijmeni', $uPoZmene->rawDb()[Sql::PRIJMENI_UZIVATELE]);
        self::assertSame('1999-12-31', $uPoZmene->rawDb()[Sql::DATUM_NAROZENI]);
        self::assertSame('Nova 12', $uPoZmene->rawDb()[Sql::ULICE_A_CP_UZIVATELE]);
        self::assertSame('Brno', $uPoZmene->rawDb()[Sql::MESTO_UZIVATELE]);
        self::assertSame('602 00', $uPoZmene->rawDb()[Sql::PSC_UZIVATELE]);
        self::assertSame((string) \Gamecon\Stat::SK_ID, $uPoZmene->rawDb()[Sql::STAT_UZIVATELE]);
        self::assertSame(\Uzivatel::TYP_DOKLADU_PAS, $uPoZmene->rawDb()[Sql::TYP_DOKLADU_TOTOZNOSTI]);
        self::assertSame('NOVY12345', $uPoZmene->cisloOp());
    }

    public function testAdminMuzePoKontroleUpravitZamceneUdaje(): void
    {
        $u = $this->novyUzivatel();
        $u->pridejRoli(Role::ZKONTROLOVANE_UDAJE_NA_LETOSNIM_GC, $u);

        $adminUdaje = [
            Sql::JMENO_UZIVATELE        => 'AdminJmeno',
            Sql::PRIJMENI_UZIVATELE     => 'AdminPrijmeni',
            Sql::DATUM_NAROZENI         => '1998-08-20',
            Sql::ULICE_A_CP_UZIVATELE   => 'Adminova 5',
            Sql::MESTO_UZIVATELE        => 'Ostrava',
            Sql::PSC_UZIVATELE          => '702 00',
            Sql::STAT_UZIVATELE         => (string) \Gamecon\Stat::SK_ID,
            Sql::TYP_DOKLADU_TOTOZNOSTI => \Uzivatel::TYP_DOKLADU_PAS,
        ];

        // Simulace admin cesty: OP se ukládá přes cisloOp, ostatní údaje přes přímý dbUpdate.
        $u->cisloOp('ADMIN12345');
        \dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, $adminUdaje, [
            Sql::ID_UZIVATELE => $u->id(),
        ]);
        $u->otoc();

        $uPoZmene = \Uzivatel::zId($u->id());
        self::assertNotNull($uPoZmene);
        self::assertSame('AdminJmeno', $uPoZmene->rawDb()[Sql::JMENO_UZIVATELE]);
        self::assertSame('AdminPrijmeni', $uPoZmene->rawDb()[Sql::PRIJMENI_UZIVATELE]);
        self::assertSame('1998-08-20', $uPoZmene->rawDb()[Sql::DATUM_NAROZENI]);
        self::assertSame('Adminova 5', $uPoZmene->rawDb()[Sql::ULICE_A_CP_UZIVATELE]);
        self::assertSame('Ostrava', $uPoZmene->rawDb()[Sql::MESTO_UZIVATELE]);
        self::assertSame('702 00', $uPoZmene->rawDb()[Sql::PSC_UZIVATELE]);
        self::assertSame((string) \Gamecon\Stat::SK_ID, $uPoZmene->rawDb()[Sql::STAT_UZIVATELE]);
        self::assertSame(\Uzivatel::TYP_DOKLADU_PAS, $uPoZmene->rawDb()[Sql::TYP_DOKLADU_TOTOZNOSTI]);
        self::assertSame('ADMIN12345', $uPoZmene->cisloOp());
    }

    public function testSeznamZamcenychUdajuPoKontroleOdpovidaZadani(): void
    {
        self::assertSame([
            Sql::TYP_DOKLADU_TOTOZNOSTI,
            Sql::OP,
            Sql::JMENO_UZIVATELE,
            Sql::PRIJMENI_UZIVATELE,
            Sql::DATUM_NAROZENI,
            Sql::ULICE_A_CP_UZIVATELE,
            Sql::MESTO_UZIVATELE,
            Sql::PSC_UZIVATELE,
            Sql::STAT_UZIVATELE,
        ], \Uzivatel::zamceneUdajePoKontroleNaInfopultu());
    }

    public function testWebFormPoKontroleRenderujeZamceneUdajeJakoDisabled(): void
    {
        $u = $this->novyUzivatel();
        $u->pridejRoli(Role::ZKONTROLOVANE_UDAJE_NA_LETOSNIM_GC, $u);
        $u->otoc();

        $registrace = new Registrace(
            \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(),
            $u,
        );
        $html = $registrace->povinneUdajeProUbytovaniHtml();

        foreach ($this->zamceneUdajeATypTagu() as $klic => $tag) {
            $this->assertPoleVRegistraciDisabled($html, $klic, $tag);
        }
    }

    public function testWebFormPredKontrolouNerenderujeZamceneUdajeJakoDisabled(): void
    {
        $u = $this->novyUzivatel();
        $registrace = new Registrace(
            \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(),
            $u,
        );
        $html = $registrace->povinneUdajeProUbytovaniHtml();

        foreach ($this->zamceneUdajeATypTagu() as $klic => $tag) {
            $this->assertPoleVRegistraciNeniDisabled($html, $klic, $tag);
        }
    }

    /**
     * @return array<string, 'input'|'select'>
     */
    private function zamceneUdajeATypTagu(): array
    {
        return [
            Sql::TYP_DOKLADU_TOTOZNOSTI => 'select',
            Sql::OP                     => 'input',
            Sql::JMENO_UZIVATELE        => 'input',
            Sql::PRIJMENI_UZIVATELE     => 'input',
            Sql::DATUM_NAROZENI         => 'input',
            Sql::ULICE_A_CP_UZIVATELE   => 'input',
            Sql::MESTO_UZIVATELE        => 'input',
            Sql::PSC_UZIVATELE          => 'input',
            Sql::STAT_UZIVATELE         => 'select',
        ];
    }

    /**
     * @param 'input'|'select' $tag
     */
    private function assertPoleVRegistraciDisabled(string $html, string $klic, string $tag): void
    {
        $pattern = $this->disabledPatternProPole($klic, $tag);
        self::assertMatchesRegularExpression(
            $pattern,
            $html,
            "Pole '{$klic}' má být ve web formuláři disabled",
        );
    }

    /**
     * @param 'input'|'select' $tag
     */
    private function assertPoleVRegistraciNeniDisabled(string $html, string $klic, string $tag): void
    {
        $pattern = $this->disabledPatternProPole($klic, $tag);
        self::assertDoesNotMatchRegularExpression(
            $pattern,
            $html,
            "Pole '{$klic}' nemá být ve web formuláři disabled",
        );
    }

    /**
     * @param 'input'|'select' $tag
     */
    private function disabledPatternProPole(string $klic, string $tag): string
    {
        return sprintf(
            '~<%s[^>]*name="%s\[%s\]"[^>]*\bdisabled\b~',
            $tag,
            preg_quote(Registrace::FORM_DATA_KEY, '~'),
            preg_quote($klic, '~'),
        );
    }
}
