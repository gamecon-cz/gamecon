<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Role\Role;
use Gamecon\Uzivatel\MedailonekVNastaveni;
use Gamecon\Web\FotkaUzivatele;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Vypravěč, organizátor a vedoucí aktivity si v Nastavení na webu sami upraví medailonek, tedy text a fotku na své
 * stránce /<id>. Text píšou sami, takže na stránku z něj smí jen bezpečné HTML.
 */
class MedailonekVNastaveniTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_VEDOUCIHO = 9861;
    private const int ID_PANA_JESKYNE = 9862;
    private const int ID_ORGANIZATORA = 9863;
    private const int ID_UCASTNIKA = 9864;
    private const int ID_BRIGADNIKA = 9865;
    private const int ID_VYPRAVECE_V_PRIPRAVE = 9866;
    private const int ID_AKTIVITY = 9861;
    private const int ID_AKTIVITY_DRD = 9862;
    private const int ID_TECHNICKE_AKTIVITY = 9863;
    private const int ID_AKTIVITY_V_PRIPRAVE = 9864;

    protected static bool $disableStrictTransTables = true;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];
    private string $zdroje;

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        // ukládá web v požadavku na Apache, mimo transakci testu
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
    (%1$d, 'test-medailonek-vedouci', 'Test', 'Vedoucí', 'test-medailonek-vedouci@example.invalid'),
    (%2$d, 'test-medailonek-pj', 'Test', 'Pán jeskyně', 'test-medailonek-pj@example.invalid'),
    (%3$d, 'test-medailonek-org', 'Test', 'Organizátor', 'test-medailonek-org@example.invalid'),
    (%4$d, 'test-medailonek-ucastnik', 'Test', 'Účastník', 'test-medailonek-ucastnik@example.invalid'),
    (%5$d, 'test-medailonek-brigadnik', 'Test', 'Brigádník', 'test-medailonek-brigadnik@example.invalid'),
    (%6$d, 'test-medailonek-priprava', 'Test', 'V přípravě', 'test-medailonek-priprava@example.invalid')
SQL,
                self::ID_VEDOUCIHO,
                self::ID_PANA_JESKYNE,
                self::ID_ORGANIZATORA,
                self::ID_UCASTNIKA,
                self::ID_BRIGADNIKA,
                self::ID_VYPRAVECE_V_PRIPRAVE,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES (%1$d, %2$d, %1$d)
SQL,
                self::ID_ORGANIZATORA,
                Role::ORGANIZATOR,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES
    (%1$d, 'Test medailonku', 'test-medailonku-v-nastaveni', %3$d, %5$d, 2, 'Popis', 0, 5, 0, 0),
    (%2$d, 'Test medailonku DrD', 'test-medailonku-v-nastaveni-drd', %4$d, %5$d, 2, 'Popis', 100, 5, 0, 0),
    (%6$d, 'Test medailonku technická', 'test-medailonku-v-nastaveni-technicka', %7$d, %5$d, 2, 'Popis', 0, 5, 0, 0),
    (%8$d, 'Test medailonku v přípravě', 'test-medailonku-v-nastaveni-priprava', %3$d, %5$d, %9$d, 'Popis', 0, 5, 0, 0)
SQL,
                self::ID_AKTIVITY,
                self::ID_AKTIVITY_DRD,
                TypAktivity::RPG,
                TypAktivity::DRD,
                ROCNIK,
                self::ID_TECHNICKE_AKTIVITY,
                TypAktivity::TECHNICKA,
                self::ID_AKTIVITY_V_PRIPRAVE,
                StavAktivity::NOVA,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES (%1$d, %2$d), (%3$d, %4$d), (%5$d, %6$d), (%7$d, %8$d)
SQL,
                self::ID_AKTIVITY,
                self::ID_VEDOUCIHO,
                self::ID_AKTIVITY_DRD,
                self::ID_PANA_JESKYNE,
                self::ID_TECHNICKE_AKTIVITY,
                self::ID_BRIGADNIKA,
                self::ID_AKTIVITY_V_PRIPRAVE,
                self::ID_VYPRAVECE_V_PRIPRAVE,
            ),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->zdroje = sys_get_temp_dir() . '/gamecon-medailonek-v-nastaveni-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->zdroje);
        $this->kUklidu[] = $this->zdroje;
        foreach ([self::ID_VEDOUCIHO, self::ID_PANA_JESKYNE, self::ID_ORGANIZATORA, self::ID_UCASTNIKA, self::ID_BRIGADNIKA] as $id) {
            $fotka = ZpracovaneObrazky::adresarFotekUzivatelu() . '/' . $id;
            $this->kUklidu = [...$this->kUklidu, $fotka . '.png', $fotka . '.avif', $fotka . '.webp'];
        }
    }

    /**
     * @test
     */
    public function vedouciAktivitySiUloziTextANaStrankuJdeJenBezpecneHtml(): void
    {
        $relace = $this->prihlas('test-medailonek-vedouci');

        $nastaveni = $this->nastaveni($relace);
        self::assertStringContainsString('id="medailonek"', $nastaveni);
        self::assertStringContainsString('name="' . MedailonekVNastaveni::POST_O_SOBE . '"', $nastaveni);
        // druhý text je jen pro Pány jeskyně DrD
        self::assertStringNotContainsString('name="' . MedailonekVNastaveni::POST_DRD . '"', $nastaveni);

        // mezitím mu organizátor v adminu napsal profil DrD, formulář bez pole DrD ho uložením nesmaže
        dbQuery(<<<'SQL'
INSERT INTO medailonky(id_uzivatele, o_sobe, drd) VALUES ($0, '', 'Profil od organizátora')
ON DUPLICATE KEY UPDATE drd = 'Profil od organizátora'
SQL, [
            0 => self::ID_VEDOUCIHO,
        ]);
        $odpoved = $this->odesliZNastaveni($relace, [
            MedailonekVNastaveni::POST_ULOZIT => 1,
            MedailonekVNastaveni::POST_O_SOBE => "Hraju **RPG**.\r\n\r\n<script>alert('medailonek')</script>[klik](javascript:alert(1))",
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertStringEndsWith('/registrace#medailonek', $odpoved['kam']);
        self::assertSame(
            [
                'o_sobe' => "Hraju **RPG**.\n\n<script>alert('medailonek')</script>[klik](javascript:alert(1))",
                'drd'    => 'Profil od organizátora',
            ],
            $this->medailonek(self::ID_VEDOUCIHO),
        );

        $nastaveni = $this->nastaveni($relace);
        self::assertStringContainsString('Medailonek je uložený.', $nastaveni);
        // v textovém poli jako text, ne jako značka
        self::assertStringContainsString('&lt;script&gt;alert(&#039;medailonek&#039;)&lt;/script&gt;', $nastaveni);

        $stranka = $this->stahniVerejnouStranku('web/' . self::ID_VEDOUCIHO);
        self::assertSame(200, $stranka['status']);
        self::assertStringContainsString('<strong>RPG</strong>', $stranka['telo']);
        self::assertStringNotContainsString("alert('medailonek')", $stranka['telo']);
        self::assertStringNotContainsString('javascript:alert', $stranka['telo']);

        // a ani organizátorovi, který medailonek otevře v adminu
        $admin = $this->stahni($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'aktivity/medailonky?id=' . self::ID_VEDOUCIHO);
        self::assertStringContainsString('<strong>RPG</strong>', $admin);
        self::assertStringNotContainsString("alert('medailonek')", $admin);
        // v textovém poli zůstane, jak ho vypravěč napsal, odkaz z něj ale v náhledu není
        self::assertStringNotContainsString('href="javascript:', $admin);
        // živý náhled při psaní čistí prohlížeč podle stejných pravidel
        self::assertStringContainsString('data-bezpecny-nahled="', $admin);
        self::assertStringContainsString('files/bezpecny-nahled-markdownu.js', $admin);
    }

    /**
     * @test
     */
    public function panJeskyneUpraviIProfilProDrd(): void
    {
        $relace = $this->prihlas('test-medailonek-pj');
        self::assertStringContainsString('name="' . MedailonekVNastaveni::POST_DRD . '"', $this->nastaveni($relace));

        $odpoved = $this->odesliZNastaveni($relace, [
            MedailonekVNastaveni::POST_ULOZIT => 1,
            MedailonekVNastaveni::POST_O_SOBE => 'O mně',
            MedailonekVNastaveni::POST_DRD    => 'Vedu družiny od roku 2010.',
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertSame(
            [
                'o_sobe' => 'O mně',
                'drd'    => 'Vedu družiny od roku 2010.',
            ],
            $this->medailonek(self::ID_PANA_JESKYNE),
        );
    }

    /**
     * @test
     */
    public function vedouciNahrajeASmazeFotku(): void
    {
        $fotky = ZpracovaneObrazky::adresarFotekUzivatelu() . '/' . self::ID_VEDOUCIHO;
        $relace = $this->prihlas('test-medailonek-vedouci');
        $nastaveni = $this->nastaveni($relace);
        self::assertStringContainsString('Fotku zatím nemáš', $nastaveni);
        // z webu menší limit než v adminu
        self::assertStringContainsString('nejvýš ' . NahravaniObrazku::FOTKA_Z_WEBU_MAX_MB . ' MB', $nastaveni);

        $odpoved = $this->odesliZNastaveni($relace, [
            FotkaUzivatele::POST_AKCE   => FotkaUzivatele::AKCE_NAHRAT,
            FotkaUzivatele::POST_SOUBOR => new \CURLFile($this->png(), 'image/png', 'portret.png'),
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertStringEndsWith('/registrace#medailonek', $odpoved['kam']);
        self::assertCount(1, glob($fotky . '.*'));
        $nastaveni = $this->nastaveni($relace);
        self::assertStringContainsString('Fotka je nahraná.', $nastaveni);
        self::assertStringContainsString('Smazat fotku', $nastaveni);

        // odkaz na fotku z cizího medailonku ji nezamkne, majitel by ho nemohl odstranit
        dbQuery(<<<'SQL'
INSERT INTO medailonky(id_uzivatele, o_sobe, drd) VALUES ($0, $1, '')
ON DUPLICATE KEY UPDATE o_sobe = $1
SQL, [
            0 => self::ID_PANA_JESKYNE,
            1 => '![](' . ZpracovaneObrazky::cestaVuciWebu(glob($fotky . '.*')[0]) . ')',
        ]);

        $odpoved = $this->odesliZNastaveni($relace, [
            FotkaUzivatele::POST_AKCE => FotkaUzivatele::AKCE_SMAZAT,
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertSame([], glob($fotky . '.*'));
        self::assertStringContainsString('Fotka je smazaná', $this->nastaveni($relace));
    }

    /**
     * Organizátor bez aktivit stránku /<id> nemá, s fotkou nebo textem si ji založí.
     *
     * @test
     */
    public function organizatorSiNahranimFotkyZaloziStranku(): void
    {
        self::assertSame(404, $this->stahniVerejnouStranku('web/' . self::ID_ORGANIZATORA)['status']);
        $relace = $this->prihlas('test-medailonek-org');
        $nastaveni = $this->nastaveni($relace);
        self::assertStringContainsString('id="medailonek"', $nastaveni);
        self::assertStringContainsString('Po prvním uložení je najdou', $nastaveni);

        $odpoved = $this->odesliZNastaveni($relace, [
            FotkaUzivatele::POST_AKCE   => FotkaUzivatele::AKCE_NAHRAT,
            FotkaUzivatele::POST_SOUBOR => new \CURLFile($this->png(), 'image/png', 'portret.png'),
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);

        self::assertSame([
            'o_sobe' => '',
            'drd'    => '',
        ], $this->medailonek(self::ID_ORGANIZATORA));
        self::assertSame(200, $this->stahniVerejnouStranku('web/' . self::ID_ORGANIZATORA)['status']);
    }

    /**
     * @test
     */
    public function ucastnikSekciNevidiANicSiNezalozi(): void
    {
        $relace = $this->prihlas('test-medailonek-ucastnik');
        self::assertStringNotContainsString('id="medailonek"', $this->nastaveni($relace));

        foreach (
            [
                [
                    MedailonekVNastaveni::POST_ULOZIT => 1,
                    MedailonekVNastaveni::POST_O_SOBE => 'Nejsem vypravěč',
                ],
                [
                    FotkaUzivatele::POST_AKCE   => FotkaUzivatele::AKCE_NAHRAT,
                    FotkaUzivatele::POST_SOUBOR => new \CURLFile($this->png(), 'image/png', 'portret.png'),
                ],
            ] as $pole
        ) {
            $odpoved = $this->odesliZNastaveni($relace, $pole);
            self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        }

        self::assertNull($this->medailonek(self::ID_UCASTNIKA));
        self::assertSame([], glob(ZpracovaneObrazky::adresarFotekUzivatelu() . '/' . self::ID_UCASTNIKA . '.*'));
        self::assertStringContainsString('Medailonek si může upravit jen vypravěč nebo organizátor.', $this->nastaveni($relace));
        // stránka /<id> pořád jen pro vypravěče
        self::assertSame(404, $this->stahniVerejnouStranku('web/' . self::ID_UCASTNIKA)['status']);
    }

    /**
     * Vypravěč není, kdo vede jen technickou nebo brigádnickou aktivitu, stejně jako v přehledu vypravěčů; uložením
     * by si založil veřejnou stránku vypravěče.
     *
     * @test
     */
    public function vedouciJenTechnickeAktivitySekciNevidi(): void
    {
        $relace = $this->prihlas('test-medailonek-brigadnik');
        self::assertStringNotContainsString('id="medailonek"', $this->nastaveni($relace));

        $odpoved = $this->odesliZNastaveni($relace, [
            MedailonekVNastaveni::POST_ULOZIT => 1,
            MedailonekVNastaveni::POST_O_SOBE => 'Stavím stany',
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertNull($this->medailonek(self::ID_BRIGADNIKA));
    }

    /**
     * Medailonek si vypravěč napíše dřív, než se jeho aktivita zveřejní.
     *
     * @test
     */
    public function vedouciAktivityVPripraveSekciVidi(): void
    {
        self::assertStringContainsString(
            'id="medailonek"',
            $this->nastaveni($this->prihlas('test-medailonek-priprava')),
        );
    }

    private function nastaveni(string $relace): string
    {
        return $this->stahniVerejnou($relace, $this->cestaWebu() . '/registrace');
    }

    /**
     * @param array<string, mixed> $pole
     *
     * @return array{kod: int, kam: string, telo: string}
     */
    private function odesliZNastaveni(string $relace, array $pole): array
    {
        $adresa = 'http://localhost' . $this->cestaWebu() . '/registrace';
        $curl = $this->curlNaAdresu($relace, $adresa);
        curl_setopt($curl, CURLOPT_POST, true);
        // POST bez odkazující stránky z téhož webu web zahodí jako CSRF, viz omezCsrf()
        curl_setopt($curl, CURLOPT_REFERER, $adresa);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $pole);

        return $this->proved($curl);
    }

    private function cestaWebu(): string
    {
        return (string) parse_url(URL_WEBU, PHP_URL_PATH);
    }

    /**
     * @return array{o_sobe: string, drd: string}|null
     */
    private function medailonek(int $idUzivatele): ?array
    {
        $medailonek = dbOneLine(<<<'SQL'
SELECT o_sobe, drd FROM medailonky WHERE id_uzivatele = $0
SQL,
            [
                0 => $idUzivatele,
            ],
        );

        return $medailonek ?: null;
    }

    private function png(): string
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . '.png';
        imagepng(imagecreatetruecolor(40, 40), $cesta);

        return $cesta;
    }
}
