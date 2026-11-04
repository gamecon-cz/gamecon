<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\NahravaniObrazkuException;
use Gamecon\Web\NahravaniPoCastech;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class NahravaniPoCastechTest extends TestCase
{
    use TestovaciDokumenty;

    private const int CAST = 10;
    private const int UZIVATEL = 7;

    private string $adresar;

    protected function setUp(): void
    {
        $this->adresar = sys_get_temp_dir() . '/gamecon-nahravani-po-castech-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->adresar);
    }

    /**
     * @test
     */
    public function castiSeSloziDoCelehoSouboru(): void
    {
        $nahravani = $this->nahravani();
        $obsah = 'Dvacet pět znaků a kus';

        $id = $this->poslCelyPoCastech($nahravani, $obsah);

        $dokonceny = $nahravani->dokonceny($id);
        self::assertSame($obsah, file_get_contents($dokonceny->cesta));
        self::assertSame(strlen($obsah), $dokonceny->velikostBajtu);
        self::assertSame('soubor.zip', $dokonceny->puvodniNazev);
    }

    /**
     * @test
     */
    public function posledniCastMuzeBytKratsi(): void
    {
        $nahravani = $this->nahravani();
        $id = $nahravani->zahaj(self::CAST + 3, 'a.pdf');

        self::assertSame(self::CAST, $nahravani->prijmiCast($id, 0, $this->proud(str_repeat('a', self::CAST))));
        self::assertSame(self::CAST + 3, $nahravani->prijmiCast($id, 1, $this->proud('bbb')));
    }

    /**
     * Prohlížeč po výpadku spojení pošle stejnou část znovu, nová se zapíše na její místo.
     *
     * @test
     */
    public function stejnouCastJdePoslatZnovu(): void
    {
        $nahravani = $this->nahravani();
        $id = $nahravani->zahaj(2 * self::CAST, 'a.pdf');
        $nahravani->prijmiCast($id, 0, $this->proud(str_repeat('a', self::CAST)));
        $nahravani->prijmiCast($id, 1, $this->proud(str_repeat('b', self::CAST)));

        $nahravani->prijmiCast($id, 1, $this->proud(str_repeat('c', self::CAST)));

        self::assertSame(
            str_repeat('a', self::CAST) . str_repeat('c', self::CAST),
            file_get_contents($nahravani->dokonceny($id)->cesta),
        );
    }

    /**
     * @test
     */
    public function castPoslanaZnovuUzVicePrijataZahodiVsechnyZaNi(): void
    {
        $nahravani = $this->nahravani();
        $id = $nahravani->zahaj(3 * self::CAST, 'a.pdf');
        foreach ([0, 1, 2] as $index) {
            $nahravani->prijmiCast($id, $index, $this->proud(str_repeat('x', self::CAST)));
        }

        $nahravani->prijmiCast($id, 1, $this->proud(str_repeat('y', self::CAST)));

        $this->ocekavejChybu('Soubor se nenahrál celý');
        $nahravani->dokonceny($id);
    }

    /**
     * @test
     */
    public function castBezPredchoziNejdePoslat(): void
    {
        $nahravani = $this->nahravani();
        $id = $nahravani->zahaj(3 * self::CAST, 'a.pdf');

        $this->ocekavejChybu('Chybí předchozí část souboru');
        $nahravani->prijmiCast($id, 1, $this->proud(str_repeat('x', self::CAST)));
    }

    public static function provideSpatneDelkyCasti(): array
    {
        return [
            'krátká část uprostřed' => [3 * self::CAST, 0, self::CAST - 1],
            'dlouhá část uprostřed' => [3 * self::CAST, 0, self::CAST + 1],
            'krátká poslední část'  => [self::CAST + 5, 1, 4],
            'dlouhá poslední část'  => [self::CAST + 5, 1, 6],
            'prázdná část'          => [3 * self::CAST, 0, 0],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideSpatneDelkyCasti
     */
    public function castSJinouDelkouNezSeCekaloSeOdmitneAUbereSeZeSouboru(int $velikost, int $index, int $delkaCasti): void
    {
        $nahravani = $this->nahravani();
        $id = $nahravani->zahaj($velikost, 'a.pdf');
        if ($index > 0) {
            $nahravani->prijmiCast($id, 0, $this->proud(str_repeat('a', self::CAST)));
        }

        try {
            $nahravani->prijmiCast($id, $index, $this->proud(str_repeat('b', $delkaCasti)));
            self::fail('Část se špatnou délkou se měla odmítnout');
        } catch (NahravaniObrazkuException $chyba) {
            self::assertStringContainsString('nahrávání se musí začít znovu', $chyba->getMessage());
        }

        // správná část hned potom projde, špatná po sobě nic nenechala
        $nahravani->prijmiCast($id, $index, $this->proud(str_repeat('c', min(self::CAST, $velikost - $index * self::CAST))));
        self::addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function castMimoSouborNejdePoslat(): void
    {
        $nahravani = $this->nahravani();
        $id = $nahravani->zahaj(self::CAST, 'a.pdf');

        $this->ocekavejChybu('Tahle část souboru tam nepatří');
        $nahravani->prijmiCast($id, 1, $this->proud('x'));
    }

    /**
     * @test
     */
    public function zapornePoradiNejdePoslat(): void
    {
        $nahravani = $this->nahravani();
        $id = $nahravani->zahaj(self::CAST, 'a.pdf');

        $this->ocekavejChybu('Tahle část souboru tam nepatří');
        $nahravani->prijmiCast($id, -1, $this->proud('x'));
    }

    /**
     * @test
     */
    public function nedokonceneNahraniNejdeZpracovat(): void
    {
        $nahravani = $this->nahravani();
        $id = $nahravani->zahaj(2 * self::CAST, 'a.pdf');
        $nahravani->prijmiCast($id, 0, $this->proud(str_repeat('a', self::CAST)));

        $this->ocekavejChybu('Soubor se nenahrál celý');
        $nahravani->dokonceny($id);
    }

    /**
     * @test
     */
    public function prazdnyAPrilisVelkySouborSeOdmitnouUzNaZacatku(): void
    {
        $nahravani = $this->nahravani(maxVelikost: 100);

        foreach ([[0, 'Soubor je prázdný.'], [101, 'Soubor je příliš velký, maximum je 1 kB.']] as [$velikost, $zprava]) {
            try {
                $nahravani->zahaj($velikost, 'a.pdf');
                self::fail("Soubor o velikosti {$velikost} se měl odmítnout");
            } catch (NahravaniObrazkuException $chyba) {
                self::assertSame($zprava, $chyba->getMessage());
            }
        }
        self::assertSame([], glob($this->adresar . '/*') ?: [], 'Odmítnuté nahrávání po sobě nic nenechalo');
    }

    /**
     * @test
     */
    public function najednouJdeRozpracovatJenOmezenyPocetNahrani(): void
    {
        $nahravani = $this->nahravani();
        $ids = [];
        for ($poradi = 0; $poradi < 3; ++$poradi) {
            $ids[] = $nahravani->zahaj(self::CAST, 'a.pdf');
        }

        $this->ocekavejChybu('Rozpracovaných nahrávání je moc');
        try {
            $nahravani->zahaj(self::CAST, 'a.pdf');
        } finally {
            // po smazání jednoho se místo uvolní
            $nahravani->smaz($ids[0]);
            self::assertNotSame('', $nahravani->zahaj(self::CAST, 'a.pdf'));
        }
    }

    /**
     * @test
     */
    public function limitRozpracovanychSeUplatniJenNaVlastniNahrani(): void
    {
        $cizi = $this->nahravani(idUzivatele: self::UZIVATEL + 1);
        for ($poradi = 0; $poradi < 3; ++$poradi) {
            $cizi->zahaj(self::CAST, 'a.pdf');
        }

        self::assertNotSame('', $this->nahravani()->zahaj(self::CAST, 'a.pdf'));
    }

    /**
     * @test
     */
    public function nahraniBezPohybuStarsiNezDenSeUklidi(): void
    {
        $nahravani = $this->nahravani();
        $stare = $nahravani->zahaj(self::CAST, 'stare.pdf');
        $cerstve = $nahravani->zahaj(self::CAST, 'cerstve.pdf');
        $stary = $this->adresar . '/' . $stare;
        foreach (glob($stary . '/*') ?: [] as $soubor) {
            touch($soubor, time() - 90_000);
        }
        // až po souborech: zápis do adresáře mění jeho čas
        touch($stary, time() - 90_000);

        $nahravani->zahaj(self::CAST, 'nove.pdf');

        self::assertDirectoryDoesNotExist($stary);
        self::assertDirectoryExists($this->adresar . '/' . $cerstve);
    }

    /**
     * @test
     */
    public function cizimNahranimNeniVideta(): void
    {
        $nahravani = $this->nahravani();
        $id = $nahravani->zahaj(self::CAST, 'a.pdf');
        $nahravani->prijmiCast($id, 0, $this->proud(str_repeat('a', self::CAST)));
        $cizi = $this->nahravani(idUzivatele: self::UZIVATEL + 1);

        foreach ([
            'dokončit'    => static fn () => $cizi->dokonceny($id),
            'poslat část' => static fn () => $cizi->prijmiCast($id, 0, fopen('php://memory', 'rb')),
        ] as $co => $akce) {
            try {
                $akce();
                self::fail("Cizí nahrávání šlo {$co}");
            } catch (NahravaniObrazkuException $chyba) {
                self::assertStringContainsString('Nahrávání už neexistuje', $chyba->getMessage());
            }
        }

        $cizi->smaz($id);
        self::assertSame(self::CAST, $nahravani->dokonceny($id)->velikostBajtu, 'Cizí smazání nic neudělalo');
    }

    public static function provideNeplatnaId(): array
    {
        return [
            'prázdné'           => [''],
            'cesta nahoru'      => ['../../etc/passwd'],
            'velká písmena'     => [str_repeat('A', 32)],
            'příliš krátké'     => [str_repeat('a', 31)],
            'příliš dlouhé'     => [str_repeat('a', 33)],
            'jiné než šestnáct' => [str_repeat('g', 32)],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideNeplatnaId
     */
    public function neplatneIdSeOdmitne(string $id): void
    {
        $nahravani = $this->nahravani();

        $this->ocekavejChybu('Neplatné nahrávání');
        $nahravani->dokonceny($id);
    }

    /**
     * @test
     */
    public function neexistujiciIdSeOdmitne(): void
    {
        $this->ocekavejChybu('Nahrávání už neexistuje');
        $this->nahravani()->dokonceny(str_repeat('a', 32));
    }

    /**
     * @test
     */
    public function smazaneNahraniUzNeexistuje(): void
    {
        $nahravani = $this->nahravani();
        $id = $this->poslCelyPoCastech($nahravani, 'obsah souboru');

        $nahravani->smaz($id);

        self::assertDirectoryDoesNotExist($this->adresar . '/' . $id);
        $this->ocekavejChybu('Nahrávání už neexistuje');
        $nahravani->dokonceny($id);
    }

    /**
     * @test
     */
    public function smazaniNeplatnehoIdNicNesmaze(): void
    {
        $nahravani = $this->nahravani();
        $nahravani->zahaj(self::CAST, 'a.pdf');

        $nahravani->smaz('../');
        $nahravani->smaz('');

        self::assertCount(1, glob($this->adresar . '/*') ?: []);
    }

    /**
     * Část musí projít jedním požadavkem, takže ji omezuje post_max_size.
     *
     * @test
     */
    public function velikostCastiNepresahneCoPhpPrijmeVJednomPozadavku(): void
    {
        $nahravani = new NahravaniPoCastech(self::UZIVATEL, 1000, $this->adresar);

        self::assertGreaterThanOrEqual(262_144, $nahravani->getCastBajtu());
        self::assertLessThanOrEqual(NahravaniPoCastech::CAST_BAJTU, $nahravani->getCastBajtu());
    }

    /**
     * Souběžné zahájení nesmí smazat adresář, který se právě zakládá a ještě nemá žádný soubor.
     *
     * @test
     */
    public function novyAdresarBezSouboruSeNeuklidiJakoStary(): void
    {
        $zakladany = $this->adresar . '/' . str_repeat('a', 32);
        (new Filesystem())->mkdir($zakladany);

        $this->nahravani()->zahaj(self::CAST, 'a.pdf');

        self::assertDirectoryExists($zakladany);
    }

    /**
     * Soubor mezi limitem jednoho požadavku a velikostí části by obyčejný formulář odmítl, i když by po částech prošel.
     *
     * @test
     */
    public function prahProPosilaniPoCastechJeMensiNezCastALimitPhp(): void
    {
        $nahravani = new NahravaniPoCastech(self::UZIVATEL, 1000, $this->adresar);
        $limity = array_filter([
            NahravaniObrazku::bajtyZNastaveniPhp('upload_max_filesize'),
            NahravaniObrazku::bajtyZNastaveniPhp('post_max_size'),
        ], static fn (int $limit): bool => $limit > 0);

        self::assertLessThanOrEqual($nahravani->getCastBajtu(), $nahravani->getPrahBajtu());
        self::assertLessThan(min($limity ?: [PHP_INT_MAX]), $nahravani->getPrahBajtu());
    }

    /**
     * @test
     */
    public function idSNovymRadkemNaKonciNeniPlatne(): void
    {
        $nahravani = $this->nahravani();
        $id = $nahravani->zahaj(self::CAST, 'a.pdf');

        $this->ocekavejChybu('Neplatné nahrávání');
        $nahravani->dokonceny($id . "\n");
    }

    /**
     * @test
     */
    public function sUklidemSmazeNahraniIKdyzAkceSelze(): void
    {
        $nahravani = $this->nahravani();
        $id = $this->poslCelyPoCastech($nahravani, 'obsah souboru');

        try {
            $nahravani->sUklidem($id, static fn () => throw new NahravaniObrazkuException('Špatná složka.'));
            self::fail('Akce měla selhat');
        } catch (NahravaniObrazkuException) {
            self::addToAssertionCount(1);
        }

        self::assertDirectoryDoesNotExist($this->adresar . '/' . $id);
        self::assertSame('výsledek', $nahravani->sUklidem($id, static fn () => 'výsledek'), 'Smazání neexistujícího nic nerozbije');
    }

    /**
     * @test
     */
    public function poslanySouborProjdeKontrolouSluzbyJakoKazdyJiny(): void
    {
        $nahravani = $this->nahravani(maxVelikost: 1_000_000);
        $pdf = self::pdfBajty();
        $id = $nahravani->zahaj(strlen($pdf), 'cokoli.mp3');
        foreach (str_split($pdf, self::CAST) as $index => $cast) {
            $nahravani->prijmiCast($id, $index, $this->proud($cast));
        }
        $koren = $this->adresar . '-koren';
        (new Filesystem())->mkdir($koren);

        try {
            $nahrany = $nahravani->over($id, new NahravaniObrazku($koren, formaty: [NahravaniObrazku::PDF], poCastech: true));

            self::assertSame([NahravaniObrazku::PDF, 'pdf', 'cokoli.mp3'], [$nahrany->mime, $nahrany->pripona, $nahrany->puvodniNazev]);
            self::assertFalse($nahrany->zHttpUploadu, 'Soubor neprošel přes $_FILES, takže se zkopíruje, ne přesune');
        } finally {
            (new Filesystem())->remove($koren);
        }
    }

    private function nahravani(int $maxVelikost = 1000, int $idUzivatele = self::UZIVATEL): NahravaniPoCastech
    {
        return new NahravaniPoCastech($idUzivatele, $maxVelikost, $this->adresar, self::CAST);
    }

    private function poslCelyPoCastech(NahravaniPoCastech $nahravani, string $obsah): string
    {
        $id = $nahravani->zahaj(strlen($obsah), 'soubor.zip');
        foreach (str_split($obsah, self::CAST) as $index => $cast) {
            $nahravani->prijmiCast($id, $index, $this->proud($cast));
        }

        return $id;
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

    private function ocekavejChybu(string $castZpravy): void
    {
        $this->expectException(NahravaniObrazkuException::class);
        $this->expectExceptionMessage($castZpravy);
    }
}
