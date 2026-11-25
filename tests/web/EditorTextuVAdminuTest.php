<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Web\ZpracovaneObrazky;

/**
 * Editor textu stránky, novinky a medailonku (CodeMirror, ui/src/editorTextu) přes skutečnou administraci: náhled
 * vykreslený serverem a obrázek vložený do textu ze schránky nebo přetažením, tak jak je posílá editor.
 */
class EditorTextuVAdminuTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_NOVINKY = 9861;
    private const int ID_BEZ_PRAV = 9862;
    private const string URL_BLOGU = 'test-editor-textu';

    protected static bool $disableStrictTransTables = true;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

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
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES (%d, 'test-editor-textu-bez-prav', 'Test', 'Bezprav', 'test-editor-textu-bez-prav@example.invalid')
SQL,
                self::ID_BEZ_PRAV,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO novinky(id, typ, vydat, url, nazev, autor, text)
VALUES (%d, 2, NOW(), '%s', 'Test editoru', 'Autor', 'Text')
SQL,
                self::ID_NOVINKY,
                self::URL_BLOGU,
            ),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->uklidNoveAdresare(ZpracovaneObrazky::adresarObsahu() . '/blog/' . self::URL_BLOGU);
    }

    /**
     * @test
     */
    public function poleSTextemNacteEditorANahledMaVykreslenyText(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $stranka = $this->stahni($session, 'web/novinka?id=' . self::ID_NOVINKY);

        self::assertMatchesRegularExpression('~<textarea [^>]*name="[^"]+" data-editor-textu>Text</textarea>~', $stranka);
        self::assertMatchesRegularExpression('~<script type="module" src="files/ui/editor-textu\.js\?version=[0-9a-f]{32}"></script>~', $stranka);
        self::assertStringContainsString('data-editor-textu-nahled><p>Text</p>', $stranka);
        // editor podle bloku Obrázky pozná, že obrázek jde vložit rovnou do textu
        self::assertMatchesRegularExpression('~<details class="obrazky-obsahu" open data-editor-textu-obrazky="[^"]*image/png~', $stranka);
        self::assertStringNotContainsString('showdown.js', $stranka);
    }

    /**
     * @test
     */
    public function nahledVykresliServerStejneJakoWeb(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        foreach (['web/novinka?id=' . self::ID_NOVINKY, 'web/editace-stranek?akce=nova', 'aktivity/medailonky?id=' . \Uzivatel::SYSTEM] as $adresa) {
            $odpoved = $this->posliEditoru($session, $adresa, 'nahled', [
                'text' => "**Ahoj** a tabulka\n\n| a | b |\n|---|---|\n| 1 | 2 |",
            ]);

            self::assertSame(200, $odpoved['kod'], $adresa . ': ' . $odpoved['telo']);
            self::assertStringContainsString('<strong>Ahoj</strong>', $odpoved['json']['html'], $adresa);
            self::assertStringContainsString('<table>', $odpoved['json']['html'], $adresa);
        }
    }

    /**
     * Obrázek ze schránky má pokaždé stejný název, další se uloží s číslem.
     *
     * @test
     */
    public function obrazekVlozenyDoTextuSeUloziKPrispevkuPodVolnymNazvem(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $adresa = 'web/novinka?id=' . self::ID_NOVINKY;
        $png = $this->png();

        $prvni = $this->posliEditoru($session, $adresa, 'obrazek', [
            'obrazek' => new \CURLFile($png, 'image/png', 'obrazek.png'),
        ]);
        $druhy = $this->posliEditoru($session, $adresa, 'obrazek', [
            'obrazek' => new \CURLFile($png, 'image/png', 'obrazek.png'),
        ]);

        self::assertSame(200, $prvni['kod'], $prvni['telo']);
        self::assertSame(200, $druhy['kod'], $druhy['telo']);
        $adresar = 'soubory/obsah/blog/' . self::URL_BLOGU . '/';
        // přípona podle toho, do čeho obrázky umí převést ImageMagick na serveru (ImagickAvif)
        self::assertMatchesRegularExpression('~^' . preg_quote($adresar, '~') . 'obrazek\.(avif|webp|png)$~', $prvni['json']['cesta']);
        self::assertMatchesRegularExpression('~^' . preg_quote($adresar, '~') . 'obrazek-2\.(avif|webp|png)$~', $druhy['json']['cesta']);
        self::assertSame('![](' . $prvni['json']['cesta'] . ')', $prvni['json']['markdown']);
        $obsah = ZpracovaneObrazky::adresarObsahu();
        self::assertFileExists($obsah . '/' . substr($prvni['json']['cesta'], strlen('soubory/obsah/')));
        self::assertFileExists($obsah . '/' . substr($druhy['json']['cesta'], strlen('soubory/obsah/')));
        // v bloku Obrázky pod editorem je, jako by ho někdo nahrál tam
        self::assertStringContainsString(
            'value="![](' . $prvni['json']['cesta'] . ')"',
            $this->stahni($session, $adresa),
        );
    }

    /**
     * @test
     */
    public function obrazekDoNeulozenehoTextuNeboMedailonkuNejde(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        foreach (['web/editace-stranek?akce=nova', 'aktivity/medailonky?id=' . \Uzivatel::SYSTEM] as $adresa) {
            $odpoved = $this->posliEditoru($session, $adresa, 'obrazek', [
                'obrazek' => new \CURLFile($this->png(), 'image/png', 'obrazek.png'),
            ]);

            self::assertSame(422, $odpoved['kod'], $adresa);
            self::assertSame('Obrázek do textu půjde vložit, až text uložíš.', $odpoved['json']['zprava'], $adresa);
        }
    }

    /**
     * @test
     */
    public function bezHlavickyEditoruSeObrazekNeulozi(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $odpoved = $this->posliEditoru($session, 'web/novinka?id=' . self::ID_NOVINKY, 'obrazek', [
            'obrazek' => new \CURLFile($this->png(), 'image/png', 'obrazek.png'),
        ], sHlavickou: false);

        self::assertSame(400, $odpoved['kod']);
        self::assertDirectoryDoesNotExist(ZpracovaneObrazky::adresarObsahu() . '/blog/' . self::URL_BLOGU);
    }

    /**
     * @test
     */
    public function bezPravaNaStrankuEditorNicNedostane(): void
    {
        $odpoved = $this->posliEditoru(
            $this->prihlas('test-editor-textu-bez-prav'),
            'web/novinka?id=' . self::ID_NOVINKY,
            'nahled',
            [
                'text' => 'a',
            ],
        );

        self::assertSame(403, $odpoved['kod']);
        self::assertNull($odpoved['json']);
    }

    /**
     * Požadavek, jak ho posílá ui/src/editorTextu/server.ts.
     *
     * @param array<string, mixed> $pole
     *
     * @return array{kod: int, telo: string, json: array<string, mixed>|null}
     */
    private function posliEditoru(string $cookieFile, string $adresa, string $akce, array $pole, bool $sHlavickou = true): array
    {
        $curl = $this->curl($cookieFile, $adresa . (str_contains($adresa, '?') ? '&' : '?') . 'editorTextu=' . $akce);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $pole);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $sHlavickou ? ['X-Gamecon-Editor: 1'] : []);
        $odpoved = $this->proved($curl);
        $json = json_decode($odpoved['telo'], true);

        return [
            'kod'  => $odpoved['kod'],
            'telo' => $odpoved['telo'],
            'json' => is_array($json) ? $json : null,
        ];
    }

    private function png(): string
    {
        $cesta = tempnam(sys_get_temp_dir(), 'editor-textu') . '.png';
        $this->kUklidu[] = $cesta;
        imagepng(imagecreatetruecolor(40, 30), $cesta);

        return $cesta;
    }
}
