<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahravaniPoCastech;
use Gamecon\Web\NahravaniPoCastechPozadavek;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class NahravaniPoCastechPozadavekTest extends TestCase
{
    private const int CAST = 10;

    private string $adresar;
    private NahravaniPoCastechPozadavek $pozadavek;

    protected function setUp(): void
    {
        $this->adresar = sys_get_temp_dir() . '/gamecon-pozadavek-po-castech-' . bin2hex(random_bytes(6));
        $this->pozadavek = new NahravaniPoCastechPozadavek(new NahravaniPoCastech(7, 1000, $this->adresar, self::CAST));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->adresar);
    }

    /**
     * @test
     */
    public function zahajiNahraniAVratiIdAVelikostCasti(): void
    {
        $odpoved = $this->zpracuj('zahaj', post: [
            'velikost' => '25',
            'nazev'    => 'archiv.zip',
        ]);

        self::assertSame(200, $odpoved['kod']);
        self::assertTrue($odpoved['telo']['ok']);
        self::assertMatchesRegularExpression('~^[0-9a-f]{32}$~', $odpoved['telo']['id']);
        self::assertSame(self::CAST, $odpoved['telo']['castBajtu']);
    }

    /**
     * @test
     */
    public function castSeZapiseAOdpovedRekneKolikJeUzPrijato(): void
    {
        $id = $this->zahaj(25);

        $prvni = $this->zpracuj('cast', get: [
            'id'    => $id,
            'index' => '0',
        ], telo: str_repeat('a', 10));
        $posledni = $this->zpracuj('cast', get: [
            'id'    => $id,
            'index' => '2',
        ], telo: str_repeat('a', 5));

        self::assertSame([200, 10], [$prvni['kod'], $prvni['telo']['prijato']]);
        self::assertSame(422, $posledni['kod'], 'Prostřední část chybí');
        self::assertFalse($posledni['telo']['ok']);
    }

    /**
     * @test
     */
    public function zrusSmazeRozpracovaneNahrani(): void
    {
        $id = $this->zahaj(25);

        $odpoved = $this->zpracuj('zrus', post: [
            'id' => $id,
        ]);

        self::assertSame(200, $odpoved['kod']);
        self::assertDirectoryDoesNotExist($this->adresar . '/' . $id);
    }

    public static function provideNepripustnePozadavky(): array
    {
        return [
            'GET'                       => ['zahaj', 'GET', true],
            'bez hlavičky prohlížeče'   => ['zahaj', 'POST', false],
            'neznámá metoda i hlavička' => ['zahaj', 'PUT', false],
        ];
    }

    /**
     * Cizí web neumí poslat vlastní hlavičku bez našeho svolení, takže přihlášeného správce k nahrání nepřiměje.
     *
     * @test
     *
     * @dataProvider provideNepripustnePozadavky
     */
    public function pozadavekBezHlavickyNeboJinouMetodouSeOdmitne(string $krok, string $metoda, bool $maHlavicku): void
    {
        $odpoved = $this->pozadavek->zpracuj($krok, $metoda, $maHlavicku, [], [
            'velikost' => '25',
            'nazev'    => 'a.zip',
        ], $this->proud(''));

        self::assertSame(400, $odpoved['kod']);
        self::assertFalse($odpoved['telo']['ok']);
        self::assertSame([], glob($this->adresar . '/*') ?: [], 'Odmítnutý požadavek nic nezačal');
    }

    /**
     * @test
     */
    public function neznamyKrokSeOdmitne(): void
    {
        $odpoved = $this->zpracuj('smaz-vsechno');

        self::assertSame([422, 'Neznámý krok nahrávání.'], [$odpoved['kod'], $odpoved['telo']['zprava']]);
    }

    public static function provideSpatnePoradi(): array
    {
        return [
            'chybí'     => [null],
            'záporné'   => ['-1'],
            'desetinné' => ['1.5'],
            'text'      => ['první'],
            'pole'      => [['0']],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideSpatnePoradi
     */
    public function neplatnePoradiCastiSeOdmitne(mixed $index): void
    {
        $id = $this->zahaj(25);

        $odpoved = $this->zpracuj('cast', get: [
            'id'    => $id,
            'index' => $index,
        ], telo: str_repeat('a', 10));

        self::assertSame([422, 'Neplatné pořadí části souboru.'], [$odpoved['kod'], $odpoved['telo']['zprava']]);
    }

    public static function provideSpatnaZahajeni(): array
    {
        return [
            'bez velikosti' => [[], 'Soubor je prázdný.'],
            'nulová'        => [[
                'velikost' => '0',
            ], 'Soubor je prázdný.'],
            'text' => [[
                'velikost' => 'hodně',
            ], 'Soubor je prázdný.'],
            'příliš velká' => [[
                'velikost' => '1001',
            ], 'Soubor je příliš velký, maximum je 1 kB.'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideSpatnaZahajeni
     *
     * @param array<string, mixed> $post
     */
    public function nahraniSNeplatnouVelikostiSeNezacne(array $post, string $zprava): void
    {
        $odpoved = $this->zpracuj('zahaj', post: $post + [
            'nazev' => 'a.zip',
        ]);

        self::assertSame([422, $zprava], [$odpoved['kod'], $odpoved['telo']['zprava']]);
    }

    private function zahaj(int $velikost): string
    {
        return $this->zpracuj('zahaj', post: [
            'velikost' => (string) $velikost,
            'nazev'    => 'a.zip',
        ])['telo']['id'];
    }

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     *
     * @return array{kod: int, telo: array<string, mixed>}
     */
    private function zpracuj(string $krok, array $get = [], array $post = [], string $telo = ''): array
    {
        return $this->pozadavek->zpracuj($krok, 'POST', true, $get, $post, $this->proud($telo));
    }

    /**
     * @return resource
     */
    private function proud(string $obsah)
    {
        $proud = fopen('php://memory', 'r+b');
        fwrite($proud, $obsah);
        rewind($proud);

        return $proud;
    }
}
