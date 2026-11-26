<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Role\Role;
use Gamecon\Stat;
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

    public function testJmenoNaWebuOrizneNezlomitelneMezery(): void
    {
        $id = \Uzivatel::registruj($this->uzivatel([
            Sql::LOGIN_UZIVATELE    => 'Kew.' . uniqid('', true),
            Sql::EMAIL1_UZIVATELE   => 'kew.' . uniqid('', true) . '@example.com',
            Sql::JMENO_UZIVATELE    => 'Jan',
            Sql::PRIJMENI_UZIVATELE => 'Novak',
        ]));
        \dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, [
            Sql::LOGIN_UZIVATELE => "\u{A0}Kew\u{A0}",
        ], [
            Sql::ID_UZIVATELE => $id,
        ]);

        self::assertSame('Kew', \Uzivatel::zId($id)->jmenoNaWebu());
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

    public function testRegistraceUloziJazykPozadavku(): void
    {
        AktualniJazyk::nastav(Jazyk::ANGLICTINA);
        try {
            $id = \Uzivatel::registruj($this->uzivatel([
                Sql::LOGIN_UZIVATELE  => 'anglicky.' . uniqid('', true),
                Sql::EMAIL1_UZIVATELE => 'anglicky.' . uniqid('', true) . '@example.com',
            ]));
            $idRychloregistrace = \Uzivatel::rychloregistrace(\Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals());
        } finally {
            AktualniJazyk::reset();
        }

        self::assertSame(Jazyk::ANGLICTINA, \Uzivatel::zId($id)->jazyk());
        self::assertSame(Jazyk::ANGLICTINA, \Uzivatel::zId($idRychloregistrace)->jazyk());
    }

    public function testAdresaVJinemStateNemusiMitCeskePscACisloPopisneNaKonci(): void
    {
        $u = $this->novyUzivatel([
            Sql::ULICE_A_CP_UZIVATELE => '10 Downing Street',
            Sql::MESTO_UZIVATELE      => 'London',
            Sql::PSC_UZIVATELE        => 'SW1A 2AA',
            Sql::STAT_UZIVATELE       => (string) Stat::JINY_ID,
            Sql::TELEFON_UZIVATELE    => '+44 20 7946 0958',
        ]);

        self::assertSame('10 Downing Street', $u->rawDb()[Sql::ULICE_A_CP_UZIVATELE]);
        self::assertSame('SW1A 2AA', $u->rawDb()[Sql::PSC_UZIVATELE]);

        // úprava jen PSČ bere stát uložený u účtu
        foreach (['LV-1010', '12345-6789', 'K1A 0B1', 'd02 x285'] as $psc) {
            $u->uprav([
                Sql::PSC_UZIVATELE => $psc,
            ]);
            self::assertSame($psc, \Uzivatel::zId($u->id())->rawDb()[Sql::PSC_UZIVATELE]);
        }
        $u->uprav([
            Sql::ULICE_A_CP_UZIVATELE => 'Rose Cottage, Church Lane',
        ]);
        self::assertSame('Rose Cottage, Church Lane', \Uzivatel::zId($u->id())->rawDb()[Sql::ULICE_A_CP_UZIVATELE]);
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: string}>
     */
    public static function provideNeplatnaAdresaVJinemState(): iterable
    {
        yield 'PSČ s podtržítkem' => [[
            Sql::PSC_UZIVATELE => 'SW1A_2AA',
        ], Sql::PSC_UZIVATELE];
        yield 'PSČ jen z pomlčky' => [[
            Sql::PSC_UZIVATELE => '-',
        ], Sql::PSC_UZIVATELE];
        yield 'PSČ s lomítkem' => [[
            Sql::PSC_UZIVATELE => 'N/A',
        ], Sql::PSC_UZIVATELE];
        yield 'ulice bez písmene' => [[
            Sql::ULICE_A_CP_UZIVATELE => '10 / 12',
        ], Sql::ULICE_A_CP_UZIVATELE];
    }

    /**
     * @dataProvider provideNeplatnaAdresaVJinemState
     *
     * @param array<string, string> $adresa
     */
    public function testAdresaVJinemStateSeTakyKontroluje(array $adresa, string $klicChyby): void
    {
        $chyby = $this->chybyRegistrace([
            Sql::ULICE_A_CP_UZIVATELE => '10 Downing Street',
            Sql::PSC_UZIVATELE        => 'SW1A 2AA',
            Sql::STAT_UZIVATELE       => (string) Stat::JINY_ID,
            ...$adresa,
        ]);

        self::assertSame([$klicChyby], array_keys($chyby->vsechny()));
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function provideCeskaPravidlaAdresy(): iterable
    {
        yield 'Česko' => [(string) Stat::CZ_ID];
        yield 'Slovensko' => [(string) Stat::SK_ID];
        yield 'bez státu' => [null];
    }

    /**
     * @dataProvider provideCeskaPravidlaAdresy
     */
    public function testAdresaVCeskuASlovenskuMaPravidlaJakoDriv(?string $stat): void
    {
        $chyby = $this->chybyRegistrace([
            Sql::ULICE_A_CP_UZIVATELE => '10 Downing Street',
            Sql::PSC_UZIVATELE        => 'SW1A 2AA',
            Sql::STAT_UZIVATELE       => $stat,
        ]);

        self::assertSame(
            [
                Sql::ULICE_A_CP_UZIVATELE => 'vyplň prosím ulici, např. Česká 27',
                Sql::PSC_UZIVATELE        => 'vyplň prosím PSČ, např. 602 00',
            ],
            $chyby->vsechny(),
        );
        self::assertSame('Registrace se nepodařila. Oprav prosím zvýrazněné položky.', $chyby->globalniChyba());

        $login = 'cesky.' . uniqid('', true);
        $tab = $this->uzivatel([
            Sql::LOGIN_UZIVATELE      => $login,
            Sql::EMAIL1_UZIVATELE     => $login . '@example.com',
            Sql::ULICE_A_CP_UZIVATELE => 'Česká 27/3a',
            Sql::PSC_UZIVATELE        => '602 00',
            Sql::STAT_UZIVATELE       => $stat,
        ]);
        $id = \Uzivatel::registruj(array_filter($tab, static fn (?string $hodnota) => $hodnota !== null));
        self::assertSame('602 00', \Uzivatel::zId($id)->rawDb()[Sql::PSC_UZIVATELE]);
    }

    public function testUpravaCeskeAdresyNaCiziPscChceJinyStat(): void
    {
        $u = $this->novyUzivatel([
            Sql::STAT_UZIVATELE => (string) Stat::CZ_ID,
        ]);

        try {
            $u->uprav([
                Sql::PSC_UZIVATELE => 'SW1A 2AA',
            ]);
            self::fail('U českého účtu má cizí PSČ neprojít');
        } catch (\Chyby $chyby) {
            self::assertSame([Sql::PSC_UZIVATELE], array_keys($chyby->vsechny()));
        }

        $u->uprav([
            Sql::PSC_UZIVATELE  => 'SW1A 2AA',
            Sql::STAT_UZIVATELE => (string) Stat::JINY_ID,
        ]);
        self::assertSame('SW1A 2AA', \Uzivatel::zId($u->id())->rawDb()[Sql::PSC_UZIVATELE]);
    }

    public function testChybyRegistraceVAnglictineJsouAnglickyAEscapovane(): void
    {
        $chyby = AktualniJazyk::provedVJazyce(Jazyk::ANGLICTINA, fn () => $this->chybyRegistrace([
            Sql::TELEFON_UZIVATELE    => '(555) 123',
            Sql::ULICE_A_CP_UZIVATELE => 'Downing Street',
            Sql::PSC_UZIVATELE        => 'SW1A 2AA',
            Sql::STAT_UZIVATELE       => (string) Stat::CZ_ID,
            'heslo_kontrola'          => 'jine',
        ]));

        self::assertSame('Sign-up failed. Please correct the highlighted fields.', $chyby->globalniChyba());
        self::assertSame(
            'please enter your phone number with the country code, using only digits, spaces and +, e.g. +44 20 7946 0958',
            $chyby->klic(Sql::TELEFON_UZIVATELE),
        );
        self::assertSame(
            'please enter the postcode, e.g. 602 00. Living outside the Czech Republic and Slovakia? Choose “(other country)” as your country.',
            $chyby->klic(Sql::PSC_UZIVATELE),
        );
        self::assertSame('the passwords do not match', $chyby->klic('heslo'));
        foreach ($chyby->vsechny() as $klic => $chyba) {
            self::assertDoesNotMatchRegularExpression('~[ěščřžýáíéúůťďň]~u', str_replace('Česká', '', $chyba), "Chyba {$klic} je česky: {$chyba}");
        }

        $chybyVJinemState = AktualniJazyk::provedVJazyce(Jazyk::ANGLICTINA, fn () => $this->chybyRegistrace([
            Sql::PSC_UZIVATELE  => 'SW1A_2AA',
            Sql::STAT_UZIVATELE => (string) Stat::JINY_ID,
        ]));
        self::assertSame(
            'please enter the postcode using only letters, digits, spaces and hyphens, e.g. SW1A 1AA',
            $chybyVJinemState->klic(Sql::PSC_UZIVATELE),
        );
    }

    public function testTelefonSCiziPredvolbouZustaneJakHoUcastnikNapsal(): void
    {
        $data = $this->novyUzivatel()->rawDb();
        $telefon = static fn (string $cislo): \Uzivatel => new \Uzivatel([
            ...$data,
            Sql::TELEFON_UZIVATELE => $cislo,
        ]);

        self::assertSame('+44 20 7946 0958', $telefon('+44  20 7946 0958')->telefon());
        self::assertSame('+1 555 123 4567', $telefon('+1 555 123 4567')->telefon());
        self::assertSame('0049 30 1234567', $telefon('0049 30 1234567')->telefon());
        self::assertSame("<span class='telefon'>+44 20 7946 0958</span>", $telefon('+44 20 7946 0958')->telefon(html: true));
        // česká a slovenská čísla jako dřív
        self::assertSame('777 123 456', trim($telefon('+420 777 123 456')->telefon()));
        self::assertSame('777 123 456', trim($telefon('777123456')->telefon()));
        self::assertSame('+421 905 123 456', trim($telefon('+421 905 123 456')->telefon()));
    }

    public function testFormularRegistraceANastaveniVAnglictineNemajiCeskeTexty(): void
    {
        $u = $this->novyUzivatel();
        $systemoveNastaveni = \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals();

        foreach ([
            'registrace' => null,
            'nastavení'  => $u,
        ] as $formular => $uzivatel) {
            $html = AktualniJazyk::provedVJazyce(Jazyk::ANGLICTINA, static function () use ($systemoveNastaveni, $uzivatel): string {
                ob_start();
                (new Registrace($systemoveNastaveni, $uzivatel))->zobrazHtml();

                return (string) ob_get_clean();
            });
            $text = $this->textFormulare($html);

            foreach (['Postcode', '(other country)', 'I agree to the', 'Nationality', 'Passport'] as $anglicky) {
                self::assertStringContainsString($anglicky, $text, "Formulář {$formular}");
            }
            self::assertStringContainsString(
                $uzivatel === null
                    ? 'Sign up'
                    : 'Log out other devices',
                $text,
            );
            self::assertDoesNotMatchRegularExpression(
                '~[ěščřžýáíéóúůťďňĚŠČŘŽÝÁÍÉÓÚŮŤĎŇ]~u',
                // jméno a adresa ubytovatele (koleje VŠB) jsou vlastní jména
                str_replace(['Studentská', 'Ostrava-Poruba'], '', $text),
                "Ve formuláři {$formular} nezůstal český text.",
            );
        }
    }

    public function testPredvyplneneUdajeVNastaveniSeEscapuji(): void
    {
        // přezdívku si píše účastník sám; uvozovka nesmí ukončit atribut value (vidí to i infopult přepnutý na něj)
        $u = $this->novyUzivatel([
            Sql::LOGIN_UZIVATELE => 'a"><b>x',
        ]);

        ob_start();
        (new Registrace(\Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(), $u))->zobrazHtml();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('value="a&quot;&gt;&lt;b&gt;x"', $html);
        self::assertStringNotContainsString('<b>x', $html);
    }

    public function testFormularRegistraceVCestineZustavaCesky(): void
    {
        ob_start();
        (new Registrace(\Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(), null))->zobrazHtml();
        $html = (string) ob_get_clean();
        $text = $this->textFormulare($html);

        foreach ([
            'Registrace',
            'Jsi jenom krok od toho stát se součástí naprosto boží akce!',
            'Adresa trvalého pobytu',
            'Ulice a číslo popisné',
            '(jiný stát)',
            'Souhlasím se zpracováním osobních údajů',
            'Tím je Vysoká škola báňská - Technická univerzita Ostrava, Koleje Poruba Studentská 1770/1 708 00 Ostrava-Poruba.',
            'Plné právní znění najdeš zde.',
        ] as $cesky) {
            self::assertStringContainsString($cesky, $text);
        }
        self::assertStringContainsString('<input type="submit" value="Jen vytvořit účet" class="formular_sekundarni">', $html);
    }

    /**
     * Text formuláře bez značek a komentářů, mezery sloučené.
     */
    private function textFormulare(string $html): string
    {
        $bezKomentaru = (string) preg_replace(['~<!--.*?-->~s', '~<script\b.*?</script>~s'], '', $html);

        return trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($bezKomentaru))));
    }

    /**
     * @param array<string, string|null> $prepis null = údaj ve formuláři chybí (nevybraný stát)
     */
    private function chybyRegistrace(array $prepis): \Chyby
    {
        $login = 'chyby.' . uniqid('', true);
        $tab = $this->uzivatel([
            Sql::LOGIN_UZIVATELE  => $login,
            Sql::EMAIL1_UZIVATELE => $login . '@example.com',
            ...$prepis,
        ]);
        try {
            \Uzivatel::registruj(array_filter($tab, static fn (?string $hodnota) => $hodnota !== null));
        } catch (\Chyby $chyby) {
            return $chyby;
        }
        self::fail('Registrace měla skončit chybou');
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

    public static function provideNeplatnePohlavi(): iterable
    {
        yield 'prázdné' => [''];
        yield 'chybějící' => [null];
        yield 'neznámé' => ['x'];
    }

    /**
     * @dataProvider provideNeplatnePohlavi
     */
    public function testRegistraceBezPlatnehoPohlaviSeNezapise(?string $pohlavi): void
    {
        $login = 'bez-pohlavi.' . uniqid('', true);
        $tab = $this->uzivatel([
            Sql::LOGIN_UZIVATELE  => $login,
            Sql::EMAIL1_UZIVATELE => $login . '@example.com',
        ]);
        if ($pohlavi === null) {
            unset($tab[Sql::POHLAVI]);
        } else {
            $tab[Sql::POHLAVI] = $pohlavi;
        }

        try {
            \Uzivatel::registruj($tab);
            self::fail('Mělo vyhodit výjimku kvůli chybějícímu nebo neplatnému pohlaví');
        } catch (\Chyby $chyby) {
            self::assertMatchesRegularExpression('/pohlaví/', $chyby->klic(Sql::POHLAVI));
        }

        self::assertNull(\Uzivatel::zNicku($login), 'účet s neplatným pohlavím nesmí vzniknout');
    }

    public function testUpravPrazdnouHodnotouPohlaviNezmeni(): void
    {
        $u = $this->novyUzivatel();

        $u->uprav([
            Sql::POHLAVI => '',
        ]);

        self::assertSame('f', \Uzivatel::zId($u->id())->rawDb()[Sql::POHLAVI]);
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
            Sql::STAT_UZIVATELE         => (string) Stat::SK_ID,
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
            Sql::STAT_UZIVATELE         => (string) Stat::SK_ID,
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
        self::assertSame((string) Stat::SK_ID, $uPoZmene->rawDb()[Sql::STAT_UZIVATELE]);
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
            Sql::STAT_UZIVATELE         => (string) Stat::SK_ID,
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
        self::assertSame((string) Stat::SK_ID, $uPoZmene->rawDb()[Sql::STAT_UZIVATELE]);
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

    public function testUdajeAdresyADokladuJsouVRegistraciNepovinne(): void
    {
        $registrace = new Registrace(
            \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(),
            null,
        );
        $html = $registrace->povinneUdajeProUbytovaniHtml(vyzadovatAdresuADoklad: false);

        foreach ($this->adresaADokladUdajeATypTagu() as $klic => $tag) {
            self::assertDoesNotMatchRegularExpression(
                $this->requiredPatternProPole($klic, $tag),
                $html,
                "Pole '{$klic}' v registraci nemá být required",
            );
        }
    }

    public function testUdajeAdresyADokladuJsouVPrihlascePovinne(): void
    {
        $registrace = new Registrace(
            \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(),
            null,
        );
        $html = $registrace->povinneUdajeProUbytovaniHtml(
            nadpis: 'Povinné údaje pro ubytování',
            vyzadovatAdresuADoklad: true,
        );

        foreach ($this->adresaADokladUdajeATypTagu() as $klic => $tag) {
            self::assertMatchesRegularExpression(
                $this->requiredPatternProPole($klic, $tag),
                $html,
                "Pole '{$klic}' v přihlášce má být required",
            );
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
     * @return array<string, 'input'|'select'>
     */
    private function adresaADokladUdajeATypTagu(): array
    {
        return [
            Sql::ULICE_A_CP_UZIVATELE   => 'input',
            Sql::MESTO_UZIVATELE        => 'input',
            Sql::PSC_UZIVATELE          => 'input',
            Sql::STAT_UZIVATELE         => 'select',
            Sql::TYP_DOKLADU_TOTOZNOSTI => 'select',
            Sql::OP                     => 'input',
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

    /**
     * @param 'input'|'select' $tag
     */
    private function requiredPatternProPole(string $klic, string $tag): string
    {
        return sprintf(
            '~<%s[^>]*name="%s\[%s\]"[^>]*\brequired\b~',
            $tag,
            preg_quote(Registrace::FORM_DATA_KEY, '~'),
            preg_quote($klic, '~'),
        );
    }
}
