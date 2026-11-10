<?php

declare(strict_types=1);

namespace Gamecon\Tests\ArchivPrednasek;

use Gamecon\ArchivPrednasek\ChybaArchivu;
use Gamecon\ArchivPrednasek\SpravaArchivu;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Administration of the archive: checks, pairing with a card, speakers shared by name, cleanup.
 */
class SpravaArchivuTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    private const int LETOS = 2026;

    /**
     * @return list<string>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            // the main activity first, the instance points to it and the activities of the card to the instance
            <<<'SQL'
INSERT INTO akce_seznam(id_akce, patri_pod, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES
    (990301, NULL, 'Karta se dvěma termíny', 'karta-dva-terminy', 3, 2025, 7, '', 0, 40, 0, 0),
    (990303, NULL, 'Samostatná přednáška', 'samostatna', 3, 2025, 7, '', 0, 40, 0, 0)
SQL,
            <<<'SQL'
INSERT INTO akce_instance(id_instance, id_hlavni_akce) VALUES (990301, 990301)
SQL,
            <<<'SQL'
UPDATE akce_seznam SET patri_pod = 990301 WHERE id_akce = 990301
SQL,
            <<<'SQL'
INSERT INTO akce_seznam(id_akce, patri_pod, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES (990302, 990301, 'Karta se dvěma termíny', '', 3, 2025, 7, '', 0, 40, 0, 0)
SQL,
            <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (990301, 'prvni-sprava', 'První', 'Správa', 'prvni-sprava@example.invalid'),
    (990302, 'druhy-sprava', 'Druhý', 'Správa', 'druhy-sprava@example.invalid')
SQL,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        // the migration already filled the archive of the test database
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek
            SQL);
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek_prednasejici
            SQL);
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek_klicove_slovo
            SQL);
    }

    private static function sprava(): SpravaArchivu
    {
        return new SpravaArchivu(self::LETOS);
    }

    /**
     * @param list<array{jmeno: string, idUzivatele: int|null, menitUzivatele: bool}> $prednasejici
     * @param list<string>                                                            $klicovaSlova
     */
    private static function uloz(
        string $nazev = 'Přednáška',
        string $rok = '2025',
        string $youtube = '',
        ?int $idAkce = null,
        array $prednasejici = [],
        array $klicovaSlova = [],
        ?int $idPrednasky = null,
        bool $skryta = false,
    ): int {
        return self::sprava()->uloz($idPrednasky, $nazev, $rok, $youtube, $skryta, $idAkce, $prednasejici, $klicovaSlova);
    }

    public function testUlozeniUpraviNazevOdkazAKlicovaSlova(): void
    {
        $id = self::uloz(
            nazev: '  Mistrovství DrD - Rozuzlení ',
            youtube: ' https://youtu.be/QaGjJIZJ4to?si=abc ',
            klicovaSlova: ['RPG', ' rpg ', '', 'Nové   slovo'],
        );

        $detail = self::sprava()->getDetail($id);
        self::assertSame('Mistrovství DrD – Rozuzlení', $detail['nazev']);
        self::assertSame('QaGjJIZJ4to', $detail['youtubeId']);
        self::assertSame(['Nové slovo', 'RPG'], $detail['klicovaSlova']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function provideChyby(): array
    {
        return [
            'bez názvu' => [[
                'nazev' => '  ',
            ], 'Přednáška musí mít název.'],
            'rok v budoucnu' => [[
                'rok' => '2027',
            ], 'Rok musí být mezi 2000 a 2026.'],
            'rok není číslo' => [[
                'rok' => 'loni',
            ], 'Rok musí být mezi 2000 a 2026.'],
            'odkaz jinam' => [[
                'youtube' => 'https://vimeo.com/1',
            ], 'Odkaz na YouTube nepoznávám'],
            'neexistující akce' => [[
                'idAkce' => 990399,
            ], 'Aktivita 990399 v programu není.'],
            'neexistující účet' => [[
                'prednasejici' => [[
                    'jmeno'          => 'Někdo',
                    'idUzivatele'    => 990399,
                    'menitUzivatele' => true,
                ]],
            ], 'Uživatel 990399 neexistuje.'],
            'účet bez jména' => [[
                'prednasejici' => [[
                    'jmeno'          => '',
                    'idUzivatele'    => 990301,
                    'menitUzivatele' => true,
                ]],
            ], 'musí mít jméno pro web'],
        ];
    }

    /**
     * @param array<string, mixed> $parametry
     *
     * @dataProvider provideChyby
     */
    public function testChybuMusiAdminOpravit(array $parametry, string $zprava): void
    {
        $this->expectException(ChybaArchivu::class);
        $this->expectExceptionMessage($zprava);

        self::uloz(...$parametry);
    }

    public function testNazevARokJsouJednaPrednaska(): void
    {
        self::uloz(nazev: 'Umíte mluvit?', rok: '2012');
        self::uloz(nazev: 'Umíte mluvit?', rok: '2014');

        $this->expectException(ChybaArchivu::class);
        $this->expectExceptionMessage('z roku 2012 už v archivu je.');
        self::uloz(nazev: 'umíte mluvit?', rok: '2012');
    }

    public function testSparujeKartuPresHlavniAktivituAJednuKartuJenJednou(): void
    {
        // the second term of a card pairs the card, stored is its main activity
        $id = self::uloz(nazev: 'Karta', idAkce: 990302);
        self::assertSame(990301, self::sprava()->getDetail($id)['idAkce']);

        try {
            self::uloz(nazev: 'Jiná přednáška', idAkce: 990301);
            self::fail('Karta patří jen jedné přednášce');
        } catch (ChybaArchivu $chyba) {
            self::assertSame('Karta programu už patří přednášce „Karta“ (2025).', $chyba->getMessage());
        }

        // the same lecture may keep its card, an empty field unpairs
        self::uloz(nazev: 'Karta', idAkce: 990302, idPrednasky: $id);
        self::uloz(nazev: 'Karta', idAkce: null, idPrednasky: $id);
        self::assertNull(self::sprava()->getDetail($id)['idAkce']);
    }

    public function testPrednasejiciSeStejnymJmenemJeTentyzAZmenaUzivatelePlatiVsude(): void
    {
        $prvni = self::uloz(nazev: 'První', prednasejici: [[
            'jmeno'          => 'Lektor',
            'idUzivatele'    => 990301,
            'menitUzivatele' => true,
        ]]);
        // added again without a user, the link stays
        $druha = self::uloz(nazev: 'Druhá', prednasejici: [[
            'jmeno'          => 'lektor',
            'idUzivatele'    => null,
            'menitUzivatele' => false,
        ]]);

        self::assertSame(1, (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM archiv_prednasek_prednasejici WHERE jmeno = 'Lektor'
            SQL));
        self::assertSame([[
            'jmeno'       => 'Lektor',
            'idUzivatele' => 990301,
        ]], self::sprava()->getDetail($druha)['prednasejici']);

        // a changed user applies to every lecture of the speaker
        self::uloz(nazev: 'Druhá', prednasejici: [[
            'jmeno'          => 'Lektor',
            'idUzivatele'    => 990302,
            'menitUzivatele' => true,
        ]], idPrednasky: $druha);
        self::assertSame(990302, self::sprava()->getDetail($prvni)['prednasejici'][0]['idUzivatele']);

        // and an emptied user unlinks him
        self::uloz(nazev: 'Druhá', prednasejici: [[
            'jmeno'          => 'Lektor',
            'idUzivatele'    => null,
            'menitUzivatele' => true,
        ]], idPrednasky: $druha);
        self::assertNull(self::sprava()->getDetail($prvni)['prednasejici'][0]['idUzivatele']);
    }

    /**
     * One speaker per user: a new name or another user would otherwise make a second speaker of the same person,
     * listed twice in the filter of the public archive.
     */
    public function testUzivatelMaJednohoPrednasejiciho(): void
    {
        $id = self::uloz(nazev: 'První', prednasejici: [[
            'jmeno'          => 'Lektor',
            'idUzivatele'    => 990301,
            'menitUzivatele' => true,
        ]]);
        self::uloz(nazev: 'Druhá', prednasejici: [[
            'jmeno'          => 'Jiný',
            'idUzivatele'    => null,
            'menitUzivatele' => false,
        ]]);

        $chyby = [];
        foreach ([
            // the name changed in the form, the user kept
            [
                'jmeno'          => 'Lektor!',
                'idUzivatele'    => 990301,
                'menitUzivatele' => false,
            ],
            // another speaker linked to the same user
            [
                'jmeno'          => 'Jiný',
                'idUzivatele'    => 990301,
                'menitUzivatele' => true,
            ],
        ] as $prednasejici) {
            try {
                self::uloz(nazev: 'První', prednasejici: [$prednasejici], idPrednasky: $id);
                self::fail('Druhý přednášející téhož uživatele');
            } catch (ChybaArchivu $chyba) {
                $chyby[] = $chyba->getMessage();
            }
        }
        self::assertSame(
            array_fill(0, 2, 'Uživatel 990301 už v archivu přednáší jako „Lektor“: napiš u něj tohle jméno, nebo pole uživatele vymaž.'),
            $chyby,
        );

        try {
            self::uloz(nazev: 'První', prednasejici: [
                [
                    'jmeno'          => 'Lektor',
                    'idUzivatele'    => 990301,
                    'menitUzivatele' => false,
                ],
                [
                    'jmeno'          => 'Lektor 2',
                    'idUzivatele'    => 990301,
                    'menitUzivatele' => true,
                ],
            ], idPrednasky: $id);
            self::fail('Týž uživatel dvakrát');
        } catch (ChybaArchivu $chyba) {
            self::assertSame('Uživatel 990301 je u přednášky dvakrát, pod dvěma jmény.', $chyba->getMessage());
        }

        self::assertSame(1, (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM archiv_prednasek_prednasejici WHERE id_uzivatele = 990301
            SQL));
        self::assertSame([[
            'jmeno'       => 'Lektor',
            'idUzivatele' => 990301,
        ]], self::sprava()->getDetail($id)['prednasejici']);
    }

    /**
     * The form says a row without a name is not saved: emptying the name removes even a linked speaker.
     */
    public function testRadekBezJmenaOdebereINapojenehoPrednasejiciho(): void
    {
        $id = self::uloz(prednasejici: [
            [
                'jmeno'          => 'Lektor',
                'idUzivatele'    => 990301,
                'menitUzivatele' => true,
            ],
            [
                'jmeno'          => 'Druhý',
                'idUzivatele'    => null,
                'menitUzivatele' => false,
            ],
        ]);

        self::uloz(prednasejici: [
            [
                'jmeno'          => '',
                'idUzivatele'    => 990301,
                'menitUzivatele' => false,
            ],
            [
                'jmeno'          => 'Druhý',
                'idUzivatele'    => null,
                'menitUzivatele' => false,
            ],
        ], idPrednasky: $id);

        self::assertSame([[
            'jmeno'       => 'Druhý',
            'idUzivatele' => null,
        ]], self::sprava()->getDetail($id)['prednasejici']);
    }

    /**
     * Namesakes linked to different users come from the job after GC; the row keeps the one of its user.
     */
    public function testZJmenovcuZustaneTenNapojenyNaUzivateleRadku(): void
    {
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek_prednasejici(id_prednasejiciho, jmeno, id_uzivatele)
            VALUES (990301, 'Jan Novák', 990301), (990302, 'Jan Novák', 990302)
            SQL);

        $id = self::uloz(prednasejici: [[
            'jmeno'          => 'Jan Novák',
            'idUzivatele'    => 990302,
            'menitUzivatele' => false,
        ]]);

        self::assertSame([[
            'jmeno'       => 'Jan Novák',
            'idUzivatele' => 990302,
        ]], self::sprava()->getDetail($id)['prednasejici']);
    }

    /**
     * A card attached under another one later can hold the terms of two lectures; pairing a third one with it is
     * a message for the administrator, not a failure of the page.
     */
    public function testKartaSTerminyDvouPrednasekJeChybaProAdmina(): void
    {
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek(nazev, rok, id_akce) VALUES ('Hlavní', 2025, 990301), ('Připojená', 2025, 990303)
            SQL);
        dbQuery(<<<'SQL'
            UPDATE akce_seznam SET patri_pod = 990301 WHERE id_akce = 990303
            SQL);

        $this->expectException(ChybaArchivu::class);
        $this->expectExceptionMessage('Karta programu už patří přednášce „Hlavní“ (2025).');
        self::uloz(nazev: 'Třetí', idAkce: 990302);
    }

    public function testPoradiPrednasejicichAUklidNepouzitych(): void
    {
        $id = self::uloz(
            prednasejici: [
                [
                    'jmeno'          => 'Druhý',
                    'idUzivatele'    => null,
                    'menitUzivatele' => false,
                ],
                [
                    'jmeno'          => 'První',
                    'idUzivatele'    => null,
                    'menitUzivatele' => false,
                ],
                [
                    'jmeno'          => '',
                    'idUzivatele'    => null,
                    'menitUzivatele' => false,
                ],
            ],
            klicovaSlova: ['Staré'],
        );
        self::assertSame(['Druhý', 'První'], array_column(self::sprava()->getDetail($id)['prednasejici'], 'jmeno'));

        self::uloz(prednasejici: [[
            'jmeno'          => 'První',
            'idUzivatele'    => null,
            'menitUzivatele' => false,
        ]], klicovaSlova: ['Nové'], idPrednasky: $id);

        self::assertSame(['První'], self::sprava()->getJmenaPrednasejicich());
        self::assertSame(['Nové'], self::sprava()->getKlicovaSlova());
    }

    public function testSkrytaAFiltrSeznamu(): void
    {
        $sparovana = self::uloz(nazev: 'Spárovaná', idAkce: 990303, youtube: 'QaGjJIZJ4to');
        $skryta = self::uloz(nazev: 'Skrytá', rok: '2019');
        self::sprava()->nastavSkrytou($skryta, true);

        $nazvy = static fn (array $filtr) => array_column(self::sprava()->getSeznam($filtr), 'nazev');
        self::assertSame(['Spárovaná', 'Skrytá'], $nazvy([]));
        self::assertSame(['Skrytá'], $nazvy([
            'skryta' => true,
        ]));
        self::assertSame(['Spárovaná'], $nazvy([
            'sparovana' => true,
        ]));
        self::assertSame(['Skrytá'], $nazvy([
            'sparovana' => false,
        ]));
        self::assertSame(['Spárovaná'], $nazvy([
            'zaznam' => true,
        ]));
        self::assertSame(['Skrytá'], $nazvy([
            'rok' => 2019,
        ]));
        self::assertSame(['Spárovaná'], $nazvy([
            'hledat' => 'párov',
        ]));
        self::assertSame([2025, 2019], self::sprava()->getRoky());
        self::assertSame($sparovana, self::sprava()->getSeznam([
            'sparovana' => true,
        ])[0]['id']);
    }

    public function testHledaniKaretVraciKartuJednouAKomuPatri(): void
    {
        self::uloz(nazev: 'V archivu', idAkce: 990301);

        $nalezene = self::sprava()->hledejAktivity('Karta se', 2025);
        self::assertCount(1, $nalezene, 'Dva termíny jedné karty jsou jedna karta');
        self::assertSame(990301, $nalezene[0]['value']);
        self::assertStringContainsString('v archivu jako „V archivu“', $nalezene[0]['label']);
        self::assertSame([], self::sprava()->hledejAktivity('Karta se', 2024));
        self::assertSame([], self::sprava()->hledejAktivity('K', null), 'Jeden znak nehledá');
    }
}
