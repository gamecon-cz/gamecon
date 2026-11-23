<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Pravo;
use Gamecon\Role\Role;
use Gamecon\Uzivatel\SchvaleniMedailonku;

/**
 * Texty medailonků, které vypravěči napsali v Nastavení na webu, schvaluje kterýkoli org ve Web → Schvalování
 * medailonků. Filtruje se podle sekcí, ve kterých vypravěč letos vede aktivitu; kdo vede ve dvou, je v obou.
 */
class SchvalovaniMedailonkuVAdminuTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const string URL = 'web/schvalovani-medailonku';

    private const int ID_ORGANIZATORA = 9871;
    private const int ID_VYPRAVECE_DVOU_SEKCI = 9872;
    private const int ID_VYPRAVECE_DESKOVEK = 9873;
    private const int ID_LONSKEHO_VYPRAVECE = 9874;
    private const int ID_UCASTNIKA = 9875;
    /**
     * jen právo celé kartičky Web, bez schvalování; id role je stejné
     */
    private const int ID_SPRAVCE_WEBU = 9876;
    private const int ID_VYPRAVECE_WORKSHOPU = 9877;
    private const int ID_PAUZUJICIHO_ORGA = 9878;

    private const string ODESLANO_LONSKEHO = '2026-11-18 10:00:00.000001';
    private const string ODESLANO_DVOU_SEKCI = '2026-11-19 10:00:00.000002';
    private const string ODESLANO_WORKSHOPU = '2026-11-20 10:00:00.000003';
    private const string ODESLANO_DESKOVEK = '2026-11-21 10:00:00.123456';

    protected static bool $disableStrictTransTables = true;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        // schvaluje admin v požadavku na Apache, mimo transakci testu
        return false;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (%1$d, 'test-schvalovani-org', 'Test', 'Organizátor', 'test-schvalovani-org@example.invalid'),
    (%2$d, 'test-schvalovani-dve-sekce', 'Test', 'Dvě sekce', 'test-schvalovani-dve-sekce@example.invalid'),
    (%3$d, 'test-schvalovani-deskovky', 'Ester <b>', 'Deskovková', 'test-schvalovani-deskovky@example.invalid'),
    (%4$d, 'test-schvalovani-lonsky', 'Test', 'Loňský', 'test-schvalovani-lonsky@example.invalid'),
    (%5$d, 'test-schvalovani-ucastnik', 'Test', 'Účastník', 'test-schvalovani-ucastnik@example.invalid'),
    (%6$d, 'test-schvalovani-web', 'Test', 'Správce webu', 'test-schvalovani-web@example.invalid'),
    (%7$d, 'test-schvalovani-workshop', 'Test', 'Workshop', 'test-schvalovani-workshop@example.invalid'),
    (%8$d, 'test-schvalovani-pauza', 'Test', 'Pauzující', 'test-schvalovani-pauza@example.invalid')
SQL,
                self::ID_ORGANIZATORA,
                self::ID_VYPRAVECE_DVOU_SEKCI,
                self::ID_VYPRAVECE_DESKOVEK,
                self::ID_LONSKEHO_VYPRAVECE,
                self::ID_UCASTNIKA,
                self::ID_SPRAVCE_WEBU,
                self::ID_VYPRAVECE_WORKSHOPU,
                self::ID_PAUZUJICIHO_ORGA,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES (%1$d, 'TEST_SCHVALOVANI_WEB', 'Test schvalování web', '', -1, 'trvala', '')
SQL,
                self::ID_SPRAVCE_WEBU,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO prava_role(id_role, id_prava) VALUES (%1$d, %2$d)
SQL,
                self::ID_SPRAVCE_WEBU,
                Pravo::ADMINISTRACE_WEB,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil)
VALUES (%1$d, %2$d, %1$d), (%3$d, %3$d, %3$d), (%4$d, %5$d, %4$d)
SQL,
                self::ID_ORGANIZATORA,
                Role::ORGANIZATOR,
                self::ID_SPRAVCE_WEBU,
                self::ID_PAUZUJICIHO_ORGA,
                Role::PAUZUJICI_FULL_ORG,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES
    (9871, 'Test schvalování RPG', 'test-schvalovani-medailonku-rpg', %1$d, %5$d, 2, 'Popis', 0, 5, 0, 0),
    (9872, 'Test schvalování larp', 'test-schvalovani-medailonku-larp', %2$d, %5$d, 2, 'Popis', 0, 5, 0, 0),
    (9873, 'Test schvalování deskovky', 'test-schvalovani-medailonku-deskovky', %3$d, %5$d, 2, 'Popis', 0, 5, 0, 0),
    (9874, 'Test schvalování loňské RPG', 'test-schvalovani-medailonku-lonske-rpg', %1$d, %6$d, 2, 'Popis', 0, 5, 0, 0),
    (9875, 'Test schvalování workshop', 'test-schvalovani-medailonku-workshop', %4$d, %5$d, 2, 'Popis', 0, 5, 0, 0)
SQL,
                TypAktivity::RPG,
                TypAktivity::LARP,
                TypAktivity::TURNAJ_V_DESKOVKACH,
                TypAktivity::WORKSHOP,
                ROCNIK,
                ROCNIK - 1,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO akce_organizatori(id_akce, id_uzivatele)
VALUES (9871, %1$d), (9872, %1$d), (9873, %2$d), (9874, %3$d), (9875, %4$d)
SQL,
                self::ID_VYPRAVECE_DVOU_SEKCI,
                self::ID_VYPRAVECE_DESKOVEK,
                self::ID_LONSKEHO_VYPRAVECE,
                self::ID_VYPRAVECE_WORKSHOPU,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO medailonky(id_uzivatele, o_sobe, drd)
VALUES
    (%1$d, 'Starý text dvou sekcí', ''),
    (%2$d, 'Starý text deskovek', ''),
    (%3$d, 'Loňský text', ''),
    (%4$d, 'Starý text workshopů', '')
SQL,
                self::ID_VYPRAVECE_DVOU_SEKCI,
                self::ID_VYPRAVECE_DESKOVEK,
                self::ID_LONSKEHO_VYPRAVECE,
                self::ID_VYPRAVECE_WORKSHOPU,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO medailonky_ke_schvaleni(id_uzivatele, o_sobe, drd, odeslano)
VALUES
    (%1$d, 'Nový text dvou sekcí', NULL, '%2$s'),
    (%3$d, 'Vedu turnaje v deskovkach!!!', NULL, '%4$s'),
    (%5$d, 'Nový loňský text', NULL, '%6$s'),
    (%7$d, 'Nový text workshopů', NULL, '%8$s')
SQL,
                self::ID_VYPRAVECE_DVOU_SEKCI,
                self::ODESLANO_DVOU_SEKCI,
                self::ID_VYPRAVECE_DESKOVEK,
                self::ODESLANO_DESKOVEK,
                self::ID_LONSKEHO_VYPRAVECE,
                self::ODESLANO_LONSKEHO,
                self::ID_VYPRAVECE_WORKSHOPU,
                self::ODESLANO_WORKSHOPU,
            ),
        ];
    }

    /**
     * @test
     */
    public function organizatorUpravenyTextSchvaliAJeNaWebu(): void
    {
        self::assertTrue(
            $this->maRolePravo(Role::ORGANIZATOR, Pravo::SCHVALOVANI_MEDAILONKU),
            'Právo schvalovat medailonky dává orgům migrace',
        );
        $verejna = $this->stahniVerejnouStranku('web/' . self::ID_VYPRAVECE_DESKOVEK);
        self::assertSame(200, $verejna['status']);
        self::assertStringContainsString('Starý text deskovek', $verejna['telo']);
        self::assertStringNotContainsString('<strong>turnaje</strong>', $verejna['telo']);

        $relace = $this->prihlas('test-schvalovani-org');
        $stranka = $this->stahni($relace, self::URL);
        $karta = $this->kartaVypravece($stranka, self::ID_VYPRAVECE_DESKOVEK);
        // jméno si vypravěč píše sám
        self::assertStringContainsString('Ester &lt;b&gt;', $karta);
        self::assertStringNotContainsString('Ester <b>', $stranka);
        self::assertStringContainsString($this->stitekSekce(TypAktivity::TURNAJ_V_DESKOVKACH), $karta);
        self::assertStringContainsString(
            'href="' . htmlspecialchars(URL_WEBU . '/' . self::ID_VYPRAVECE_DESKOVEK) . '" target="_blank"',
            $karta,
        );
        self::assertStringContainsString('href="aktivity/medailonky?id=' . self::ID_VYPRAVECE_DESKOVEK . '"', $karta);
        self::assertStringContainsString('Vedu turnaje v deskovkach!!!', $karta);
        self::assertStringContainsString('value="' . self::ODESLANO_DESKOVEK . '"', $karta);

        $filtr = self::URL . '?linie=' . TypAktivity::TURNAJ_V_DESKOVKACH;
        $schvaleni = [
            SchvaleniMedailonku::POST_SCHVALIT => self::ID_VYPRAVECE_DESKOVEK,
            SchvaleniMedailonku::POST_ODESLANO => self::ODESLANO_DESKOVEK,
            SchvaleniMedailonku::POST_O_SOBE   => "Vedu **turnaje** v deskovkách.\r\n",
        ];
        $odpoved = $this->odesliSTelem($relace, $filtr, $schvaleni);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        // zpátky na stránku se stejným filtrem
        self::assertStringEndsWith('/' . $filtr, $odpoved['kam']);

        self::assertSame('Vedu **turnaje** v deskovkách.', $this->oSobe(self::ID_VYPRAVECE_DESKOVEK));
        self::assertSame(
            [
                'schvaleno'   => true,
                'id_schvalil' => self::ID_ORGANIZATORA,
            ],
            $this->schvaleni(self::ID_VYPRAVECE_DESKOVEK),
        );
        $poSchvaleni = $this->stahni($relace, $filtr);
        self::assertStringContainsString('Medailonek je schválený, text je na webu.', $poSchvaleni);
        self::assertStringNotContainsString('id="medailonek-ke-schvaleni-' . self::ID_VYPRAVECE_DESKOVEK . '"', $poSchvaleni);
        self::assertStringContainsString(
            'V sekci ' . htmlspecialchars($this->nazevSekce(TypAktivity::TURNAJ_V_DESKOVKACH)) . ' žádný text nečeká na schválení.',
            $poSchvaleni,
        );

        $verejna = $this->stahniVerejnouStranku('web/' . self::ID_VYPRAVECE_DESKOVEK);
        self::assertSame(200, $verejna['status']);
        self::assertStringContainsString('<strong>turnaje</strong>', $verejna['telo']);
        self::assertStringNotContainsString('Starý text deskovek', $verejna['telo']);

        // formulář odeslaný podruhé (zpět v prohlížeči) už nic neschválí
        dbQuery(<<<'SQL'
UPDATE medailonky SET o_sobe = 'Upraveno v adminu' WHERE id_uzivatele = $0
SQL, [
            0 => self::ID_VYPRAVECE_DESKOVEK,
        ]);
        self::assertSame(303, $this->odesli($relace, $filtr, $schvaleni));
        self::assertSame('Upraveno v adminu', $this->oSobe(self::ID_VYPRAVECE_DESKOVEK));
        self::assertStringContainsString('Text už je schválený, nebo ho vypravěč stáhl.', $this->stahni($relace, $filtr));
    }

    /**
     * Formulář otevřený před tím, než vypravěč poslal nový text, by schválil text, který nikdo nečetl.
     *
     * @test
     */
    public function zastaralyFormularNicNeschvali(): void
    {
        $relace = $this->prihlas('test-schvalovani-org');

        $odpoved = $this->odesliSTelem($relace, self::URL, [
            SchvaleniMedailonku::POST_SCHVALIT => self::ID_LONSKEHO_VYPRAVECE,
            SchvaleniMedailonku::POST_ODESLANO => '2026-11-18 09:59:59.000000',
            SchvaleniMedailonku::POST_O_SOBE   => 'Text, který nikdo nečetl',
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);

        self::assertSame('Loňský text', $this->oSobe(self::ID_LONSKEHO_VYPRAVECE));
        self::assertSame(
            [
                'schvaleno'   => false,
                'id_schvalil' => null,
            ],
            $this->schvaleni(self::ID_LONSKEHO_VYPRAVECE),
        );
        $stranka = $this->stahni($relace, self::URL);
        self::assertStringContainsString('Vypravěč mezitím poslal nový text, zkontroluj ho prosím znovu.', $stranka);
        self::assertStringContainsString('Nový loňský text', $this->kartaVypravece($stranka, self::ID_LONSKEHO_VYPRAVECE));
    }

    /**
     * @test
     */
    public function filtrujePodleSekciAVypravecVeDvouJeVObou(): void
    {
        $relace = $this->prihlas('test-schvalovani-org');

        $vse = $this->stahni($relace, self::URL);
        // od nejdéle čekajícího
        $poradi = array_map(
            fn (int $idUzivatele): int|false => strpos($vse, $this->kotvaKarty($idUzivatele)),
            [self::ID_LONSKEHO_VYPRAVECE, self::ID_VYPRAVECE_DVOU_SEKCI, self::ID_VYPRAVECE_WORKSHOPU],
        );
        self::assertNotContains(false, $poradi, 'Všechny čekající texty jsou bez filtru vidět');
        $serazene = $poradi;
        sort($serazene);
        self::assertSame($serazene, $poradi);
        self::assertStringContainsString('<a href="' . self::URL . '" class="aktivni" aria-current="true">', $vse);
        foreach ([TypAktivity::RPG, TypAktivity::LARP, TypAktivity::WORKSHOP] as $idSekce) {
            self::assertStringContainsString('<a href="' . self::URL . '?linie=' . $idSekce . '">', $vse);
        }
        self::assertStringContainsString('<a href="' . self::URL . '?linie=bez">Bez letošní aktivity (1)</a>', $vse);
        $kartaDvouSekci = $this->kartaVypravece($vse, self::ID_VYPRAVECE_DVOU_SEKCI);
        self::assertStringContainsString($this->stitekSekce(TypAktivity::RPG), $kartaDvouSekci);
        self::assertStringContainsString($this->stitekSekce(TypAktivity::LARP), $kartaDvouSekci);
        self::assertStringContainsString('letos nic nevede', $this->kartaVypravece($vse, self::ID_LONSKEHO_VYPRAVECE));

        foreach ([TypAktivity::RPG, TypAktivity::LARP] as $idSekce) {
            $sekce = $this->stahni($relace, self::URL . '?linie=' . $idSekce);
            self::assertStringContainsString($this->kotvaKarty(self::ID_VYPRAVECE_DVOU_SEKCI), $sekce);
            self::assertStringNotContainsString($this->kotvaKarty(self::ID_VYPRAVECE_WORKSHOPU), $sekce);
            // RPG vedl jen loni
            self::assertStringNotContainsString($this->kotvaKarty(self::ID_LONSKEHO_VYPRAVECE), $sekce);
            self::assertStringContainsString(
                '<a href="' . self::URL . '?linie=' . $idSekce . '" class="aktivni" aria-current="true">',
                $sekce,
            );
            // schválení vrátí na stejný filtr
            self::assertStringContainsString('action="' . self::URL . '?linie=' . $idSekce . '"', $sekce);
        }

        $workshopy = $this->stahni($relace, self::URL . '?linie=' . TypAktivity::WORKSHOP);
        self::assertStringContainsString($this->kotvaKarty(self::ID_VYPRAVECE_WORKSHOPU), $workshopy);
        self::assertStringNotContainsString($this->kotvaKarty(self::ID_VYPRAVECE_DVOU_SEKCI), $workshopy);

        $bezAktivity = $this->stahni($relace, self::URL . '?linie=bez');
        self::assertStringContainsString($this->kotvaKarty(self::ID_LONSKEHO_VYPRAVECE), $bezAktivity);
        self::assertStringNotContainsString($this->kotvaKarty(self::ID_VYPRAVECE_DVOU_SEKCI), $bezAktivity);

        // neznámý filtr i technická linie, ve které se vypravěči nepočítají, ukážou všechny
        foreach (['nesmysl', (string) TypAktivity::TECHNICKA] as $neznamyFiltr) {
            $neznamy = $this->stahni($relace, self::URL . '?linie=' . $neznamyFiltr);
            self::assertStringContainsString($this->kotvaKarty(self::ID_VYPRAVECE_DVOU_SEKCI), $neznamy);
            self::assertStringContainsString($this->kotvaKarty(self::ID_VYPRAVECE_WORKSHOPU), $neznamy);
            self::assertStringContainsString('<a href="' . self::URL . '" class="aktivni" aria-current="true">', $neznamy);
        }
    }

    /**
     * Pauzující full-org a mini-org mají jen tuhle stránku, kartičku Web ani detail medailonku ne.
     *
     * @test
     */
    public function orgBezKartickyWebSchvalovatSmi(): void
    {
        self::assertTrue($this->maRolePravo(Role::PAUZUJICI_FULL_ORG, Pravo::SCHVALOVANI_MEDAILONKU));
        self::assertFalse($this->maRolePravo(Role::PAUZUJICI_FULL_ORG, Pravo::ADMINISTRACE_WEB));

        $stranka = $this->stahni($this->prihlas('test-schvalovani-pauza'), self::URL);
        $karta = $this->kartaVypravece($stranka, self::ID_VYPRAVECE_WORKSHOPU);
        self::assertStringContainsString('name="' . SchvaleniMedailonku::POST_SCHVALIT . '"', $karta);
        self::assertStringNotContainsString('aktivity/medailonky', $karta);
    }

    /**
     * @test
     */
    public function bezPravaSchvalovatStrankuNeotevreANicNeschvali(): void
    {
        $schvaleni = [
            SchvaleniMedailonku::POST_SCHVALIT => self::ID_VYPRAVECE_DVOU_SEKCI,
            SchvaleniMedailonku::POST_ODESLANO => self::ODESLANO_DVOU_SEKCI,
            SchvaleniMedailonku::POST_O_SOBE   => 'Schválil nepovolaný',
        ];

        $ucastnik = $this->prihlas('test-schvalovani-ucastnik');
        self::assertSame(403, $this->otevri($ucastnik, self::URL)['kod']);
        self::assertSame(403, $this->odesli($ucastnik, self::URL, $schvaleni));

        // stránku pustí právo kartičky Web, schvalovat ale smí jen orgové
        $spravceWebu = $this->prihlas('test-schvalovani-web');
        $stranka = $this->otevri($spravceWebu, self::URL);
        self::assertSame(403, $stranka['kod']);
        self::assertStringContainsString('Texty medailonků schvalují organizátoři', $stranka['telo']);
        self::assertStringNotContainsString('Nový text dvou sekcí', $stranka['telo']);
        self::assertSame(303, $this->odesli($spravceWebu, self::URL, $schvaleni));

        self::assertSame('Starý text dvou sekcí', $this->oSobe(self::ID_VYPRAVECE_DVOU_SEKCI));
        self::assertSame(
            [
                'schvaleno'   => false,
                'id_schvalil' => null,
            ],
            $this->schvaleni(self::ID_VYPRAVECE_DVOU_SEKCI),
        );
    }

    private function kotvaKarty(int $idUzivatele): string
    {
        return 'id="medailonek-ke-schvaleni-' . $idUzivatele . '"';
    }

    private function kartaVypravece(string $stranka, int $idUzivatele): string
    {
        $zacatek = strpos($stranka, $this->kotvaKarty($idUzivatele));
        self::assertNotFalse($zacatek, "Text vypravěče {$idUzivatele} na stránce chybí");
        $konec = strpos($stranka, '</section>', $zacatek);
        self::assertNotFalse($konec);

        return substr($stranka, $zacatek, $konec - $zacatek);
    }

    private function nazevSekce(int $idTypu): string
    {
        return (string) TypAktivity::zId($idTypu)?->nazev();
    }

    private function stitekSekce(int $idTypu): string
    {
        return '<span class="schvalovani-medailonku--linie">' . htmlspecialchars($this->nazevSekce($idTypu)) . '</span>';
    }

    private function maRolePravo(int $idRole, int $idPrava): bool
    {
        return (int) dbOneCol(<<<'SQL'
SELECT COUNT(*) FROM prava_role WHERE id_role = $0 AND id_prava = $1
SQL, [
            0 => $idRole,
            1 => $idPrava,
        ]) > 0;
    }

    private function oSobe(int $idUzivatele): string
    {
        return (string) dbOneCol(<<<'SQL'
SELECT o_sobe FROM medailonky WHERE id_uzivatele = $0
SQL, [
            0 => $idUzivatele,
        ]);
    }

    /**
     * @return array{schvaleno: bool, id_schvalil: int|null}
     */
    private function schvaleni(int $idUzivatele): array
    {
        $radek = dbOneLine(<<<'SQL'
SELECT schvaleno, id_schvalil FROM medailonky_ke_schvaleni WHERE id_uzivatele = $0
SQL, [
            0 => $idUzivatele,
        ]);
        self::assertNotEmpty($radek);

        return [
            'schvaleno'   => $radek['schvaleno'] !== null,
            'id_schvalil' => $radek['id_schvalil'] !== null
                ? (int) $radek['id_schvalil']
                : null,
        ];
    }
}
