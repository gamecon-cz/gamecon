<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Exception\InvalidRequestException;
use App\Service\AccommodationImport;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use App\Tests\Support\SoubeznaTransakce;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Noci jsou varianty typu pokoje a vlastní řádek katalogu nemají; import je dohledá podle
 * „typu" z reportu a dne. Testy hlídají i to, že pravidla zapisovače (návaznost nocí, nejméně
 * dvě) přes import pořád platí.
 */
class AccommodationImportTest extends AbstractDatabaseKernelTestCase
{
    private const ROK = 2026;

    private ?Product $typPokoje = null;

    private function import(): AccommodationImport
    {
        return static::getContainer()->get(AccommodationImport::class);
    }

    private function ucastnik(): User
    {
        /** @var User $ucastnik */
        $ucastnik = UserFactory::createOne([
            UserEntityStructure::login => 'import_' . uniqid(),
            UserEntityStructure::email => 'import_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $ucastnik;
    }

    private function tagUbytovani(): ProductTag
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :code, NOW())',
            [
                'code' => ProductTagCode::UBYTOVANI->value,
            ],
        );

        $tag = $this->entityManager()
            ->getRepository(ProductTag::class)
            ->findOneBy([
                'code' => ProductTagCode::UBYTOVANI->value,
            ]);
        self::assertNotNull($tag);

        return $tag;
    }

    private function typPokoje(): Product
    {
        if ($this->typPokoje === null) {
            $this->typPokoje = new Product();
            $this->typPokoje->setName('Postel na 2L koleji');
            $this->typPokoje->setCode('typ-' . uniqid() . '-typ');
            $this->typPokoje->setCurrentPrice('400.00');
            $this->typPokoje->setDescription('');
            $this->typPokoje->setState(ProductStateEnum::SUSPENDED);
            $this->typPokoje->addTag($this->tagUbytovani());
            $this->entityManager()->persist($this->typPokoje);
            $this->entityManager()->flush();
        }

        return $this->typPokoje;
    }

    /**
     * @return int id varianty té noci
     */
    private function vytvorNoc(int $den, ?string $kod = null): int
    {
        $varianta = new ProductVariant();
        $varianta->setProduct($this->typPokoje());
        $varianta->setName('den ' . $den);
        $varianta->setCode($kod ?? 'noc-' . $den . '-' . uniqid());
        $varianta->setCapacity(10);
        $varianta->setAccommodationDay($den);
        $varianta->setPrice('400.00');
        $varianta->setPosition($den);
        $varianta->setState(ProductStateEnum::PUBLIC);
        $this->typPokoje()->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return (int) $varianta->getId();
    }

    private function pocetNoci(User $ucastnik): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = :idUzivatele AND rok = :rok',
            [
                'idUzivatele' => $ucastnik->getId(),
                'rok'         => self::ROK,
            ],
        );
    }

    /**
     * @test
     */
    public function zapiseNociPodleIdVariant(): void
    {
        $ucastnik = $this->ucastnik();
        $prvni = $this->vytvorNoc(0);
        $druha = $this->vytvorNoc(1);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);

        self::assertSame(2, $this->pocetNoci($ucastnik));
    }

    /**
     * Prázdný seznam = účastník nemá nic. Import tím maže noci lidem, kteří v souboru zbyli
     * bez pokoje.
     *
     * @test
     */
    public function prazdnySeznamNociSmazeCoUcastnikMel(): void
    {
        $ucastnik = $this->ucastnik();
        $prvni = $this->vytvorNoc(0);
        $druha = $this->vytvorNoc(1);
        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [], self::ROK, false);

        self::assertSame(0, $this->pocetNoci($ucastnik));
    }

    /**
     * Pravidla zapisovače platí i přes import — jedna noc bez práva neprojde.
     *
     * @test
     */
    public function jednaNocBezPravaNeprojde(): void
    {
        $ucastnik = $this->ucastnik();
        $jedina = $this->vytvorNoc(0);

        $this->expectException(InvalidRequestException::class);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$jedina], self::ROK, false);
    }

    /**
     * @test
     */
    public function jednaNocSPravemProjde(): void
    {
        $ucastnik = $this->ucastnik();
        $jedina = $this->vytvorNoc(0);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$jedina], self::ROK, povolitJednuNoc: true);

        self::assertSame(1, $this->pocetNoci($ucastnik));
    }

    /**
     * @test
     */
    public function nenavazujiciNociNeprojdou(): void
    {
        $ucastnik = $this->ucastnik();
        $streda = $this->vytvorNoc(0);
        $sobota = $this->vytvorNoc(3);

        $this->expectException(InvalidRequestException::class);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$streda, $sobota], self::ROK, false);
    }

    /**
     * @test
     */
    public function spolubydliciSeUlozi(): void
    {
        $ucastnik = $this->ucastnik();
        $prvni = $this->vytvorNoc(0);
        $druha = $this->vytvorNoc(1);

        $this->import()->ulozNociUcastnika(
            $ucastnik->getId(),
            [$prvni, $druha],
            self::ROK,
            false,
            'Pepa z Depa',
        );

        self::assertSame(
            'Pepa z Depa',
            $this->connection()->fetchOne(
                'SELECT ubytovan_s FROM uzivatele_hodnoty WHERE id_uzivatele = :idUzivatele',
                [
                    'idUzivatele' => $ucastnik->getId(),
                ],
            ),
        );
    }

    /**
     * Import hlásí „Změněno N záznamů" a sčítá k tomu návratové hodnoty zápisů — počítají
     * se **datové** řádky, ne logy. Podruhé už není co měnit, takže nula.
     *
     * @test
     */
    public function vratiPocetZmenenychRadku(): void
    {
        $ucastnik = $this->ucastnik();
        $prvni = $this->vytvorNoc(0);
        $druha = $this->vytvorNoc(1);

        $poprve = $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);
        $podruhe = $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);

        // Tři: dvě noci a řádek `uzivatele_hodnoty`, kde se u čerstvého účastníka mění
        // `ubytovan_s` z NULL na prázdný řetězec. Podruhé už se nemění nic.
        self::assertSame(3, $poprve);
        self::assertSame(0, $podruhe, 'Podruhé se nemění nic');
    }

    /**
     * Spolubydlící je taky datový řádek (`uzivatele_hodnoty`), ale log změny osobních údajů
     * se do počtu nepočítá.
     *
     * @test
     */
    public function zmenaSpolubydlicihoSePocitaJakoJedenRadek(): void
    {
        $ucastnik = $this->ucastnik();
        $prvni = $this->vytvorNoc(0);
        $druha = $this->vytvorNoc(1);
        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);

        $zmen = $this->import()->ulozNociUcastnika(
            $ucastnik->getId(),
            [$prvni, $druha],
            self::ROK,
            false,
            'Pepa z Depa',
        );

        self::assertSame(1, $zmen, 'Noci beze změny, mění se jen spolubydlící');
    }

    /**
     * @test
     */
    public function dohledaNociPodleTypuADnu(): void
    {
        $kod = 'TYP' . strtoupper(substr(uniqid('', false), -6));
        $streda = $this->vytvorNoc(0, $kod . '_st');
        $ctvrtek = $this->vytvorNoc(1, $kod . '_ct');

        $ids = $this->import()->dejIdsNociPodleTypu($kod, [0, 1], self::ROK);

        sort($ids);
        $ocekavano = [$streda, $ctvrtek];
        sort($ocekavano);
        self::assertSame($ocekavano, $ids);
    }

    /**
     * Když se nenajde noc pro každý žádaný den, je to chyba řádku — stejně jako v legacy.
     *
     * @test
     */
    public function chybejiciNocJeChyba(): void
    {
        $kod = 'TYP' . strtoupper(substr(uniqid('', false), -6));
        $this->vytvorNoc(0, $kod . '_st');

        $this->expectException(InvalidRequestException::class);

        $this->import()->dejIdsNociPodleTypu($kod, [0, 1], self::ROK);
    }

    /**
     * @return array<int, string> pokoj podle dne
     */
    private function pokojePodleDnu(User $ucastnik): array
    {
        $radky = $this->connection()->fetchAllAssociative(
            'SELECT den, pokoj FROM ubytovani WHERE id_uzivatele = :idUzivatele AND rok = :rok ORDER BY den',
            [
                'idUzivatele' => $ucastnik->getId(),
                'rok'         => self::ROK,
            ],
        );

        $podleDnu = [];
        foreach ($radky as $radek) {
            $podleDnu[(int) $radek['den']] = (string) $radek['pokoj'];
        }

        return $podleDnu;
    }

    /**
     * @test
     */
    public function pokojSeZapiseNaKazdouNocRozsahu(): void
    {
        $ucastnik = $this->ucastnik();

        $this->import()->ulozPokoj($ucastnik->getId(), 'B301', 1, 3, self::ROK);

        self::assertSame(
            [
                1 => 'B301',
                2 => 'B301',
                3 => 'B301',
            ],
            $this->pokojePodleDnu($ucastnik),
        );
    }

    /**
     * Rozsah je zároveň mazací: dny mimo něj z tabulky zmizí, i když tam byly dřív.
     *
     * @test
     */
    public function uzsiRozsahSmazeDnyMimoNej(): void
    {
        $ucastnik = $this->ucastnik();
        $this->import()->ulozPokoj($ucastnik->getId(), 'B301', 0, 4, self::ROK);

        $this->import()->ulozPokoj($ucastnik->getId(), 'B301', 1, 2, self::ROK);

        self::assertSame(
            [
                1 => 'B301',
                2 => 'B301',
            ],
            $this->pokojePodleDnu($ucastnik),
        );
    }

    /**
     * Prázdný pokoj = smazat přiřazení. Import na tom stojí, prázdná buňka je zrušení.
     *
     * @test
     */
    public function prazdnyPokojSmazeVsechnyNoci(): void
    {
        $ucastnik = $this->ucastnik();
        $this->import()->ulozPokoj($ucastnik->getId(), 'B301', 0, 2, self::ROK);

        $this->import()->ulozPokoj($ucastnik->getId(), '', null, null, self::ROK);

        self::assertSame([], $this->pokojePodleDnu($ucastnik));
    }

    /**
     * @test
     */
    public function jednaZadanaNocBezDruheJeChyba(): void
    {
        $ucastnik = $this->ucastnik();

        $this->expectException(InvalidRequestException::class);

        $this->import()->ulozPokoj($ucastnik->getId(), 'B301', 1, null, self::ROK);
    }

    /**
     * Pokoj i noci po jednom spojení — ze dvou by se druhý zápis zablokoval na zámku, který
     * drží cizí klíč na `uzivatele_hodnoty`.
     *
     * @test
     */
    public function pokojINociZaroven(): void
    {
        $ucastnik = $this->ucastnik();
        $prvni = $this->vytvorNoc(1);
        $druha = $this->vytvorNoc(2);

        $this->import()->ulozPokoj($ucastnik->getId(), 'B309', 1, 2, self::ROK);
        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);

        self::assertSame(
            [
                1 => 'B309',
                2 => 'B309',
            ],
            $this->pokojePodleDnu($ucastnik),
        );
        self::assertSame(2, $this->pocetNoci($ucastnik));
    }

    /**
     * Zapisovač si otvírá vlastní transakci. Pod transakcí importu se musí jen vnořit, aby
     * jeho commit nebyl skutečný — jinak by pád pozdějšího kroku (číslo dokladu, občanství)
     * nechal noci zapsané a řádek by byl importovaný jen napůl.
     *
     * @test
     */
    public function padPoZapisuNociVratiICeleNoci(): void
    {
        $ucastnik = $this->ucastnik();
        $prvni = $this->vytvorNoc(1);
        $druha = $this->vytvorNoc(2);

        $this->import()->zacniTransakci($ucastnik->getId(), [$prvni, $druha], self::ROK);
        $this->import()->ulozPokoj($ucastnik->getId(), 'B309', 1, 2, self::ROK);
        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);
        $this->import()->vratTransakci();

        self::assertSame([], $this->pokojePodleDnu($ucastnik), 'Pokoj se odrolovat musí');
        self::assertSame(0, $this->pocetNoci($ucastnik), 'Noci se odrolovat musí');
    }

    /**
     * Writing the room takes a shared lock on the participant's row (foreign key), while a desk
     * saving the same participant goes the other way: nights first, then an exclusive lock on
     * that row. Taking the nights only after the room write closes the cycle, so the import must
     * hold them before it writes anything.
     */
    public function testDeskHoldingTheNightsDoesNotDeadlockWithTheRoomWrite(): void
    {
        $ucastnik = $this->ucastnikVSql('import_soubeh_');
        $prvni = $this->vytvorNoc(1);
        $druha = $this->vytvorNoc(2);
        $idTypuPokoje = (int) $this->typPokoje()->getId();
        $this->connection()->commit();

        try {
            [$odpovedPultu, $chybaImportu] = $this->importProtiPultu($ucastnik, $prvni, [$prvni, $druha], 1, 2);

            self::assertSame('hotovo', $odpovedPultu);
            self::assertNull($chybaImportu, (string) $chybaImportu?->getMessage());
        } finally {
            $this->smazPotvrzenyImport($ucastnik, [$prvni, $druha], $idTypuPokoje);
            $this->connection()->beginTransaction();
        }
    }

    /**
     * The same cycle through a night the import removes: the desk saving the participant locks
     * what they hold as well, so the import has to wait for that one before it writes the room.
     */
    public function testDeskHoldingANightTheImportRemovesDoesNotDeadlockWithTheRoomWrite(): void
    {
        $ucastnik = $this->ucastnikVSql('import_soubeh_drzena_');
        $drzena = $this->vytvorNoc(1);
        $druha = $this->vytvorNoc(2);
        $treti = $this->vytvorNoc(3);
        $idTypuPokoje = (int) $this->typPokoje()->getId();
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, variant_id, rok, cena_nakupni, datum) VALUES (:uzivatel, :varianta, :rok, 100, NOW())',
            [
                'uzivatel' => $ucastnik,
                'varianta' => $drzena,
                'rok'      => self::ROK,
            ],
        );
        $this->connection()->commit();

        try {
            [$odpovedPultu, $chybaImportu] = $this->importProtiPultu($ucastnik, $drzena, [$druha, $treti], 2, 3);

            self::assertSame('hotovo', $odpovedPultu);
            self::assertNull($chybaImportu, (string) $chybaImportu?->getMessage());
        } finally {
            $this->smazPotvrzenyImport($ucastnik, [$drzena, $druha, $treti], $idTypuPokoje);
            $this->connection()->beginTransaction();
        }
    }

    /**
     * The desk takes `$nocPultu`, then the participant's row, while one import row for the same
     * participant runs in this process.
     *
     * @param int[] $noveNoci
     *
     * @return array{0: string, 1: ?\Throwable} what the desk's process reported, and the import's error
     */
    private function importProtiPultu(int $ucastnik, int $nocPultu, array $noveNoci, int $prvniDen, int $posledniDen): array
    {
        $pult = SoubeznaTransakce::spust($this->connection(), [
            ['sql', "SELECT id FROM product_variant WHERE id = {$nocPultu} FOR UPDATE"],
            ['hlasim', 'pult drzi noc'],
            ['cekej', 700],
            ['sql', "UPDATE uzivatele_hodnoty SET ubytovan_s = 'Karel' WHERE id_uzivatele = {$ucastnik}"],
            ['cekej', 300],
        ]);
        $chybaImportu = null;
        try {
            $this->import()->zacniTransakci($ucastnik, $noveNoci, self::ROK);
            $this->import()->ulozPokoj($ucastnik, 'B309', $prvniDen, $posledniDen, self::ROK);
            $this->import()->ulozNociUcastnika($ucastnik, $noveNoci, self::ROK, false);
            $this->import()->potvrdTransakci();
        } catch (\Throwable $chyba) {
            $chybaImportu = $chyba;
            $this->import()->vratTransakci();
        }

        return [$pult->dokonci(), $chybaImportu];
    }

    /**
     * @param int[] $idsNoci
     */
    private function smazPotvrzenyImport(int $idUzivatele, array $idsNoci, int $idTypuPokoje): void
    {
        $spojeni = $this->connection();
        $uzivatel = [
            'uzivatel' => $idUzivatele,
        ];
        foreach (['ubytovani', 'shop_nakupy', 'uzivatele_hodnoty_log'] as $tabulka) {
            $spojeni->executeStatement("DELETE FROM {$tabulka} WHERE id_uzivatele = :uzivatel", $uzivatel);
        }
        $spojeni->executeStatement('DELETE FROM shop_order WHERE customer_id = :uzivatel', $uzivatel);
        foreach ($idsNoci as $idNoci) {
            $spojeni->executeStatement('DELETE FROM product_variant WHERE id = :id', [
                'id' => $idNoci,
            ]);
        }
        $spojeni->executeStatement('DELETE FROM product_product_tag WHERE product_id = :id', [
            'id' => $idTypuPokoje,
        ]);
        $spojeni->executeStatement('DELETE FROM shop_predmety WHERE id_predmetu = :id', [
            'id' => $idTypuPokoje,
        ]);
        $spojeni->executeStatement('DELETE FROM uzivatele_hodnoty WHERE id_uzivatele = :uzivatel', $uzivatel);
    }

    /**
     * „Nechci ubytování" si drží účastník sám a import o té volbě nic neví. Legacy ji
     * nepřepisovalo, takže ji nesmí přepsat ani převod — jinak by přiřazení pokoje tiše
     * zrušilo, co si účastník nastavil.
     *
     * @test
     */
    public function importNepresepisujeNechciUbytovani(): void
    {
        $ucastnik = $this->ucastnik();
        $prvni = $this->vytvorNoc(1);
        $druha = $this->vytvorNoc(2);
        $this->connection()->executeStatement(
            'UPDATE uzivatele_hodnoty SET nechce_ubytovani = 1 WHERE id_uzivatele = :idUzivatele',
            [
                'idUzivatele' => $ucastnik->getId(),
            ],
        );

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);

        self::assertSame(
            1,
            (int) $this->connection()->fetchOne(
                'SELECT nechce_ubytovani FROM uzivatele_hodnoty WHERE id_uzivatele = :idUzivatele',
                [
                    'idUzivatele' => $ucastnik->getId(),
                ],
            ),
        );
    }

    /**
     * Číslo dokladu i občanství zapisuje import ve stejné transakci jako noci. Musí proto
     * jet po témž spojení — legacy `Uzivatel` je píše přes své vlastní a uvízlo by na zámku,
     * který na řádku účastníka drží zápis spolubydlícího.
     *
     * @test
     */
    public function osobniUdajeSeZapisouVeStejneTransakci(): void
    {
        $ucastnik = $this->ucastnik();
        $prvni = $this->vytvorNoc(1);
        $druha = $this->vytvorNoc(2);

        $this->import()->zacniTransakci($ucastnik->getId(), [$prvni, $druha], self::ROK);
        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false, 'Pepa');
        $zmen = $this->import()->ulozOsobniUdaje($ucastnik->getId(), 'SVK');
        $this->import()->potvrdTransakci();

        self::assertSame(1, $zmen);
        self::assertSame(
            'SVK',
            $this->connection()->fetchOne(
                'SELECT statni_obcanstvi FROM uzivatele_hodnoty WHERE id_uzivatele = :idUzivatele',
                [
                    'idUzivatele' => $ucastnik->getId(),
                ],
            ),
        );
    }

    /**
     * Stejná hodnota není změna, takže se nepřepisuje ani nepočítá.
     *
     * @test
     */
    public function nezmeneneObcanstviSeNepocita(): void
    {
        $ucastnik = $this->ucastnik();
        $this->import()->ulozOsobniUdaje($ucastnik->getId(), 'SVK');

        self::assertSame(0, $this->import()->ulozOsobniUdaje($ucastnik->getId(), 'SVK'));
    }

    /**
     * Neexistující účastník je chyba řádku, ne pád importu — proto `RuntimeException`,
     * kterou si import překládá na `Chyba`.
     *
     * @test
     */
    public function neexistujiciUcastnikJeChyba(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->import()->ulozNociUcastnika(-1, [], self::ROK, false);
    }
}
