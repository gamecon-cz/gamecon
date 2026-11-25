<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Admin\FormularPracovnihoUzivatele;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Role\Role;
use Gamecon\Tests\Model\Web\TestovaciObrazky;
use Gamecon\Uzivatel\MedailonekVNastaveni;
use Gamecon\Uzivatel\SchvaleniMedailonku;
use Gamecon\Web\BezpecneHtml;
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
    use TestovaciObrazky;
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
    public function vedouciAktivityPosleTextKeSchvaleniANaWebJdeJenBezpecneHtml(): void
    {
        $relace = $this->prihlas('test-medailonek-vedouci');

        $nastaveni = $this->nastaveni($relace);
        self::assertStringContainsString('id="medailonek"', $nastaveni);
        self::assertStringContainsString('name="' . MedailonekVNastaveni::POST_O_SOBE . '"', $nastaveni);
        // profil Pána jeskyně z Mistrovství v DrD (skončilo v roce 2025) se na webu neupravuje
        self::assertStringNotContainsString('Profil Pána jeskyně', $nastaveni);
        self::assertStringNotContainsString('Čeká na schválení', $nastaveni);

        // profil DrD, který mu dřív napsal organizátor v adminu, web nemění
        dbQuery(<<<'SQL'
INSERT INTO medailonky(id_uzivatele, o_sobe, drd) VALUES ($0, '', 'Profil od organizátora')
ON DUPLICATE KEY UPDATE drd = 'Profil od organizátora'
SQL, [
            0 => self::ID_VEDOUCIHO,
        ]);
        $text = "Hraju **RPG**.\n\n<script>alert('medailonek')</script>[klik](javascript:alert(1))";
        $odpoved = $this->odesliZNastaveni($relace, [
            MedailonekVNastaveni::POST_ULOZIT => 1,
            MedailonekVNastaveni::POST_O_SOBE => str_replace("\n", "\r\n", $text),
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertStringEndsWith('/registrace#medailonek', $odpoved['kam']);

        // na web až po schválení
        self::assertSame(
            [
                'o_sobe' => '',
                'drd'    => 'Profil od organizátora',
            ],
            $this->medailonek(self::ID_VEDOUCIHO),
        );
        self::assertSame(
            [
                'o_sobe'    => $text,
                'schvaleno' => null,
            ],
            $this->keSchvaleni(self::ID_VEDOUCIHO),
        );
        $nastaveni = $this->nastaveni($relace);
        self::assertStringContainsString('Text medailonku je odeslaný ke schválení.', $nastaveni);
        self::assertStringContainsString('Čeká na schválení', $nastaveni);
        // v textovém poli jako text, ne jako značka
        self::assertStringContainsString('&lt;script&gt;alert(&#039;medailonek&#039;)&lt;/script&gt;', $nastaveni);
        self::assertStringNotContainsString('<strong>RPG</strong>', $this->stahniVerejnouStranku('web/' . self::ID_VEDOUCIHO)['telo']);

        // organizátor ho schválí na záložce Uživatel, kde s vypravěčem pracuje
        $organizator = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $this->vyberPracovnihoUzivatele($organizator, self::ID_VEDOUCIHO);
        $zalozka = $this->stahni($organizator, 'uzivatel');
        self::assertStringContainsString('Medailonek ke schválení', $zalozka);
        self::assertStringContainsString('<strong>RPG</strong>', $zalozka);
        self::assertStringNotContainsString("alert('medailonek')", $zalozka);
        self::assertStringNotContainsString('href="javascript:', $zalozka);
        self::assertSame(303, $this->odesli($organizator, 'uzivatel', [
            SchvaleniMedailonku::POST_SCHVALIT   => self::ID_VEDOUCIHO,
            SchvaleniMedailonku::POST_ODESLANO   => $this->odeslano(self::ID_VEDOUCIHO),
            SchvaleniMedailonku::POST_O_SOBE     => $text,
            FormularPracovnihoUzivatele::POST_ID => self::ID_VEDOUCIHO,
        ]));
        self::assertStringContainsString('Medailonek je schválený', $this->stahni($organizator, 'uzivatel'));
        self::assertSame(
            [
                'o_sobe' => $text,
                'drd'    => 'Profil od organizátora',
            ],
            $this->medailonek(self::ID_VEDOUCIHO),
        );
        self::assertStringContainsString('Schváleno', $this->nastaveni($relace));

        $stranka = $this->stahniVerejnouStranku('web/' . self::ID_VEDOUCIHO);
        self::assertSame(200, $stranka['status']);
        self::assertStringContainsString('<strong>RPG</strong>', $stranka['telo']);
        self::assertStringNotContainsString("alert('medailonek')", $stranka['telo']);
        self::assertStringNotContainsString('javascript:alert', $stranka['telo']);

        // a ani organizátorovi, který medailonek otevře v adminu
        $admin = $this->stahni($organizator, 'aktivity/medailonky?id=' . self::ID_VEDOUCIHO);
        self::assertStringContainsString('<strong>RPG</strong>', $admin);
        self::assertStringNotContainsString("alert('medailonek')", $admin);
        // v textovém poli zůstane, jak ho vypravěč napsal, odkaz z něj ale v náhledu není
        self::assertStringNotContainsString('href="javascript:', $admin);
        // náhled, který si editor při psaní načítá ze serveru, je vyčištěný stejně (EditorTextuPozadavekTest), profil
        // Pána jeskyně píše jen organizátor
        self::assertMatchesRegularExpression('~<textarea [^>]*name="[^"]*\[o_sobe\][^"]*" data-editor-textu data-editor-textu-od-uzivatele>~', $admin);
        self::assertMatchesRegularExpression('~<textarea [^>]*name="[^"]*\[drd\][^"]*" data-editor-textu>~', $admin);
    }

    /**
     * @test
     */
    public function vypravecPosleTextAVratitSeKeZverejnenemuJdeBezSchvaleni(): void
    {
        $relace = $this->prihlas('test-medailonek-pj');
        // ani kdo vede aktivitu DrD, profil Pána jeskyně na webu neupravuje
        self::assertStringNotContainsString('Profil Pána jeskyně', $this->nastaveni($relace));

        $odpoved = $this->odesliZNastaveni($relace, [
            MedailonekVNastaveni::POST_ULOZIT => 1,
            MedailonekVNastaveni::POST_O_SOBE => 'O mně ![](soubory/obsah/mapa.png)',
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertSame(
            [
                'o_sobe'    => 'O mně ![](soubory/obsah/mapa.png)',
                'schvaleno' => null,
            ],
            $this->keSchvaleni(self::ID_PANA_JESKYNE),
        );
        // stejný text poslaný znovu (dvojklik) čeká dál od původního času: organizátorovi, který ho má otevřený, by
        // jinak schválení odmítlo, jako by vypravěč poslal jiný text
        $odeslano = $this->odeslano(self::ID_PANA_JESKYNE);
        $odpoved = $this->odesliZNastaveni($relace, [
            MedailonekVNastaveni::POST_ULOZIT => 1,
            MedailonekVNastaveni::POST_O_SOBE => "O mně ![](soubory/obsah/mapa.png)\r\n",
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertSame($odeslano, $this->odeslano(self::ID_PANA_JESKYNE));
        // formulář ukazuje text, který čeká
        self::assertStringContainsString('O mně ![](soubory/obsah/mapa.png)</textarea>', $this->nastaveni($relace));
        // organizátor ho najde i v detailu medailonku, a v seznamu medailonků je vidět, že čeká
        $organizator = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $detail = $this->stahni($organizator, 'aktivity/medailonky?id=' . self::ID_PANA_JESKYNE);
        self::assertStringContainsString('Text ke schválení', $detail);
        self::assertStringContainsString('name="' . SchvaleniMedailonku::POST_O_SOBE . '"', $detail);
        // obrázek z textu v náhledu z webu, ne z adminu (<base href> adminu)
        self::assertStringContainsString('src="' . URL_WEBU . '/soubory/obsah/mapa.png"', $detail);
        self::assertStringContainsString('⏳', $this->stahni($organizator, 'aktivity/medailonky'));

        // zpátky ke zveřejněnému (prázdnému) textu: není co schvalovat
        $odpoved = $this->odesliZNastaveni($relace, [
            MedailonekVNastaveni::POST_ULOZIT => 1,
            MedailonekVNastaveni::POST_O_SOBE => '',
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertNull($this->keSchvaleni(self::ID_PANA_JESKYNE));
        $nastaveni = $this->nastaveni($relace);
        self::assertStringContainsString('Text je stejný jako na webu', $nastaveni);
        self::assertStringNotContainsString('Čeká na schválení', $nastaveni);
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
     * JPEG z mobilu se při nahrání vždy převádí, a právě převod stojí paměť: limit pixelů z webu platí i pro něj.
     * Hlavička hlásí obří rozměry, obsah je malý, nic se tedy nedekóduje.
     *
     * @test
     */
    public function fotkaZWebuPresLimitPixeluNeprojde(): void
    {
        $fotky = ZpracovaneObrazky::adresarFotekUzivatelu() . '/' . self::ID_VEDOUCIHO;
        $obri = $this->zdroje . '/obri.jpg';
        file_put_contents($obri, self::jpgSRozmeryVHlavicce(6000, 6000));
        $relace = $this->prihlas('test-medailonek-vedouci');

        $odpoved = $this->odesliZNastaveni($relace, [
            FotkaUzivatele::POST_AKCE   => FotkaUzivatele::AKCE_NAHRAT,
            FotkaUzivatele::POST_SOUBOR => new \CURLFile($obri, 'image/jpeg', 'IMG_0001.JPG'),
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertSame([], glob($fotky . '.*'));
        self::assertStringContainsString(
            'nejvýš na ' . NahravaniObrazku::FOTKA_Z_WEBU_MAX_PIXELU / 1_000_000 . ' Mpx',
            $this->nastaveni($relace),
        );
    }

    /**
     * Organizátor bez aktivit stránku /<id> nemá, s fotkou nebo textem si ji založí. Odesláním prázdného textu ne.
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
            MedailonekVNastaveni::POST_ULOZIT => 1,
            MedailonekVNastaveni::POST_O_SOBE => '',
        ]);
        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        self::assertNull($this->medailonek(self::ID_ORGANIZATORA));
        self::assertSame(404, $this->stahniVerejnouStranku('web/' . self::ID_ORGANIZATORA)['status']);
        self::assertStringContainsString('Text je prázdný, není co poslat ke schválení.', $this->nastaveni($relace));

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
     * Text z adminu má konce řádků \r\n, z formuláře přijde sjednocený: beze změny nic nečeká na schválení. Příliš
     * vnořený text se neodešle, Markdown by na něm spotřeboval stovky MB.
     *
     * @test
     */
    public function konceRadkuAVnoreni(): void
    {
        dbQuery(<<<'SQL'
INSERT INTO medailonky(id_uzivatele, o_sobe, drd) VALUES ($0, $1, '')
ON DUPLICATE KEY UPDATE o_sobe = $1
SQL, [
            0 => self::ID_VYPRAVECE_V_PRIPRAVE,
            1 => "Řádek jedna\r\n\r\nŘádek dva",
        ]);
        $relace = $this->prihlas('test-medailonek-priprava');

        self::assertSame(303, $this->odesliZNastaveni($relace, [
            MedailonekVNastaveni::POST_ULOZIT => 1,
            MedailonekVNastaveni::POST_O_SOBE => "Řádek jedna\r\n\r\nŘádek dva\r\n",
        ])['kod']);
        self::assertNull($this->keSchvaleni(self::ID_VYPRAVECE_V_PRIPRAVE));
        self::assertStringContainsString('Text je stejný jako na webu', $this->nastaveni($relace));

        self::assertSame(303, $this->odesliZNastaveni($relace, [
            MedailonekVNastaveni::POST_ULOZIT => 1,
            MedailonekVNastaveni::POST_O_SOBE => str_repeat('>', 30) . ' citát',
        ])['kod']);
        self::assertStringContainsString('víc než ' . BezpecneHtml::MAX_VNORENI . ' úrovní', $this->nastaveni($relace));
        self::assertNull($this->keSchvaleni(self::ID_VYPRAVECE_V_PRIPRAVE));
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

    /**
     * @return array{o_sobe: string, schvaleno: string|null}|null
     */
    private function keSchvaleni(int $idUzivatele): ?array
    {
        $radek = dbOneLine(<<<'SQL'
SELECT o_sobe, schvaleno FROM medailonky_ke_schvaleni WHERE id_uzivatele = $0
SQL,
            [
                0 => $idUzivatele,
            ],
        );

        return $radek ?: null;
    }

    /**
     * Čas odeslání, jak ho formulář schválení posílá zpět
     */
    private function odeslano(int $idUzivatele): string
    {
        return (string) dbOneCol(<<<'SQL'
SELECT DATE_FORMAT(odeslano, '%Y-%m-%d %H:%i:%s.%f') FROM medailonky_ke_schvaleni WHERE id_uzivatele = $0
SQL,
            [
                0 => $idUzivatele,
            ],
        );
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
