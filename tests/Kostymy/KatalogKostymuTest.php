<?php

declare(strict_types=1);

namespace Gamecon\Tests\Kostymy;

use Gamecon\Kostymy\ChybaPujcovny;
use Gamecon\Kostymy\FiltrKatalogu;
use Gamecon\Kostymy\KatalogKostymu;
use Gamecon\Kostymy\StavKostymuEnum;
use Gamecon\Kostymy\StrihKostymuEnum;
use Gamecon\Kostymy\VelikostKostymuEnum;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;
use Gamecon\Web\NahravaniObrazku;
use Symfony\Component\Filesystem\Filesystem;

class KatalogKostymuTest extends AbstractUzivatelTestDb
{
    use KostymyTestTrait;

    protected static bool $disableStrictTransTables = true;

    private string $koren;

    protected function setUp(): void
    {
        parent::setUp();
        $this->koren = sys_get_temp_dir() . '/gamecon-fotky-kostymu-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->koren);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->koren);
        parent::tearDown();
    }

    private function katalog(): KatalogKostymu
    {
        return new KatalogKostymu(new NahravaniObrazku($this->koren, formaty: [NahravaniObrazku::JPG, NahravaniObrazku::PNG]));
    }

    /**
     * @param array<string, string> $zmeny
     */
    private function ulozKostym(array $zmeny = []): int
    {
        return $this->katalog()->uloz([
            'nazev'     => 'Elfí plášť',
            'velikost'  => VelikostKostymuEnum::M->value,
            'strih'     => StrihKostymuEnum::UNISEX->value,
            'kategorie' => 'Fantasy',
            ...$zmeny,
        ]);
    }

    private function nahranaFotka(): \Gamecon\Web\NahranyObrazek
    {
        $soubor = $this->koren . '/nahrany.png';
        imagepng(imagecreatetruecolor(40, 30), $soubor);
        $obrazek = $this->katalog()->nahravaniFotek()->overSoubor($soubor);

        return $obrazek;
    }

    public function testUlozeniAUprava(): void
    {
        $idKostymu = $this->ulozKostym([
            'popis'            => ' Zelený s kápí ',
            'miry'             => 'délka 140 cm',
            'interni_poznamka' => 'skříň 3',
        ]);
        $this->katalog()->uloz([
            'nazev'    => 'Elfí plášť zelený',
            'velikost' => VelikostKostymuEnum::L->value,
            'strih'    => StrihKostymuEnum::DAMSKY->value,
            'stav'     => StavKostymuEnum::V_OPRAVE->value,
        ], $idKostymu);

        $kostym = $this->katalog()->dej($idKostymu);
        self::assertSame('Elfí plášť zelený', $kostym->getNazev());
        self::assertSame(VelikostKostymuEnum::L, $kostym->getVelikost());
        self::assertSame(StrihKostymuEnum::DAMSKY, $kostym->getStrih());
        self::assertSame(StavKostymuEnum::V_OPRAVE, $kostym->getStav());
        self::assertSame('', $kostym->getPopis(), 'Úprava přepíše celý formulář');
    }

    public function testBezNazvuAVelikostiSeNeulozi(): void
    {
        foreach ([
            'Kostým musí mít název.' => [
                'nazev' => ' ',
            ],
            'Vyber velikost kostýmu.' => [
                'velikost' => 'obri',
            ],
            'Vyber střih kostýmu.' => [
                'strih' => '',
            ],
        ] as $zprava => $zmena) {
            try {
                $this->ulozKostym($zmena);
                self::fail("Mělo skončit chybou: {$zprava}");
            } catch (ChybaPujcovny $chyba) {
                self::assertSame($zprava, $chyba->getMessage());
            }
        }
    }

    public function testFiltrPodleVelikostiStrihuKategorieAVolnych(): void
    {
        $plast = $this->ulozKostym();
        $saty = $this->ulozKostym([
            'nazev'     => 'Plesové šaty',
            'velikost'  => VelikostKostymuEnum::S->value,
            'strih'     => StrihKostymuEnum::DAMSKY->value,
            'kategorie' => 'Historie',
        ]);
        $vyrazeny = $this->ulozKostym([
            'nazev' => 'Roztrhaný plášť',
            'stav'  => StavKostymuEnum::VYRAZENY->value,
        ]);
        $ids = static fn (array $kostymy): array => array_map(static fn ($kostym) => $kostym->getId(), $kostymy);
        $jenNase = new FiltrKatalogu(jenIds: [$plast, $saty, $vyrazeny]);

        self::assertSame([$saty], $ids($this->katalog()->vyber(FiltrKatalogu::zParametru([
            'strih' => 'damsky',
        ])->sJenIds([$plast, $saty, $vyrazeny]))));
        self::assertSame([$saty], $ids($this->katalog()->vyber(FiltrKatalogu::zParametru([
            'velikost' => 's',
        ])->sJenIds([$plast, $saty, $vyrazeny]))));
        self::assertSame([$saty], $ids($this->katalog()->vyber(FiltrKatalogu::zParametru([
            'kategorie' => 'Historie',
        ])->sJenIds([$plast, $saty, $vyrazeny]))));
        self::assertSame([$plast, $vyrazeny], $ids($this->katalog()->vyber(FiltrKatalogu::zParametru([
            'strih'     => 'neznamy',
            'kategorie' => 'Fantasy',
        ])->sJenIds([$plast, $saty, $vyrazeny]))));
        self::assertSame([$plast, $saty], $ids($this->katalog()->vyber($jenNase->sJenKDispozici())));
        self::assertSame([], $this->katalog()->vyber(new FiltrKatalogu(jenIds: [])));
        self::assertContains('Historie', $this->katalog()->kategorie());
    }

    public function testFotkyKostymu(): void
    {
        $idKostymu = $this->ulozKostym();
        $prvni = $this->katalog()->pridejFotku($idKostymu, $this->nahranaFotka());
        $druha = $this->katalog()->pridejFotku($idKostymu, $this->nahranaFotka());

        self::assertMatchesRegularExpression("~^{$idKostymu}-[0-9a-f]{16}\\.(avif|webp|png)$~", $prvni->getSoubor());
        self::assertFileExists($this->koren . '/' . $prvni->getSoubor());
        self::assertSame([$prvni->getId(), $druha->getId()], array_map(static fn ($fotka) => $fotka->getId(), $this->katalog()->dej($idKostymu)->getFotky()));

        $this->katalog()->nastavHlavniFotku($idKostymu, $druha->getId());
        self::assertSame($druha->getId(), $this->katalog()->dej($idKostymu)->getHlavniFotka()?->getId());

        $this->katalog()->smazFotku($idKostymu, $prvni->getId());
        self::assertFileDoesNotExist($this->koren . '/' . $prvni->getSoubor());
        self::assertSame([$druha->getId()], array_map(static fn ($fotka) => $fotka->getId(), $this->katalog()->dej($idKostymu)->getFotky()));
    }

    public function testSmazatJdeJenNepouzityKostymISFotkami(): void
    {
        $nepouzity = $this->ulozKostym();
        $fotka = $this->katalog()->pridejFotku($nepouzity, $this->nahranaFotka());
        $this->katalog()->smaz($nepouzity);
        self::assertNull($this->katalog()->dej($nepouzity));
        self::assertFileDoesNotExist($this->koren . '/' . $fotka->getSoubor());

        $rezervovany = $this->ulozKostym();
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        self::vlozPrihlasku($idAktivity, $ucastnik->id());
        self::vlozRezervaci($rezervovany, $idAktivity, $ucastnik->id());
        $this->expectException(ChybaPujcovny::class);
        $this->expectExceptionMessage('Místo smazání ho vyřaď.');
        $this->katalog()->smaz($rezervovany);
    }
}
