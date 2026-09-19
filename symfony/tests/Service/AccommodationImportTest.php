<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\AccommodationImport;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Import posílá id předmětů, `AccommodationWriter` chce id variant — u ubytování to není
 * totéž, protože rodičem variant je nedělní noc. Testy hlídají ten překlad a to, že pravidla
 * zapisovače (návaznost nocí, nejméně dvě) přes import pořád platí.
 */
class AccommodationImportTest extends AbstractDatabaseKernelTestCase
{
    private const ROK = 2026;

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

    /**
     * Noc daného dne. Varianta nese týž kód jako produkt — tak to dělá migrace den-variant
     * a právě na tom stojí překlad id v importu.
     *
     * @return array{0: int, 1: int} id předmětu (legacy) a id varianty
     */
    private function vytvorNoc(int $den): array
    {
        $kod = 'noc-' . $den . '-' . uniqid();

        $produkt = new Product();
        $produkt->setName('Postel na 2L koleji den ' . $den);
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('400.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->setAccommodationDay($den);
        $produkt->setProducedQuantity(10);
        $produkt->addTag($this->tagUbytovani());
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setName('den ' . $den);
        $varianta->setCode($kod);
        // Den musí sedět i na variantě — zapisovač podle něj pozná, že je to noc.
        $varianta->setAccommodationDay($den);
        $varianta->setPrice('400.00');
        $varianta->setPosition(0);
        $varianta->setRemainingQuantity(10);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return [(int) $produkt->getId(), (int) $varianta->getId()];
    }

    /**
     * Noc s předem daným kódem — dohledávání podle „typu" krájí z kódu poslední 3 znaky.
     */
    private function vytvorNocSKodem(string $kod, int $den): int
    {
        $produkt = new Product();
        $produkt->setName('Postel ' . $kod);
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('400.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->setAccommodationDay($den);
        $produkt->setProducedQuantity(10);
        $produkt->addTag($this->tagUbytovani());
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        return (int) $produkt->getId();
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
    public function zapiseNociPodleIdPredmetu(): void
    {
        $ucastnik = $this->ucastnik();
        [$prvni] = $this->vytvorNoc(0);
        [$druha] = $this->vytvorNoc(1);

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
        [$prvni] = $this->vytvorNoc(0);
        [$druha] = $this->vytvorNoc(1);
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
        [$jedina] = $this->vytvorNoc(0);

        $this->expectException(\RuntimeException::class);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$jedina], self::ROK, false);
    }

    /**
     * @test
     */
    public function jednaNocSPravemProjde(): void
    {
        $ucastnik = $this->ucastnik();
        [$jedina] = $this->vytvorNoc(0);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$jedina], self::ROK, povolitJednuNoc: true);

        self::assertSame(1, $this->pocetNoci($ucastnik));
    }

    /**
     * @test
     */
    public function nenavazujiciNociNeprojdou(): void
    {
        $ucastnik = $this->ucastnik();
        [$streda] = $this->vytvorNoc(0);
        [$sobota] = $this->vytvorNoc(3);

        $this->expectException(\RuntimeException::class);

        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$streda, $sobota], self::ROK, false);
    }

    /**
     * @test
     */
    public function spolubydliciSeUlozi(): void
    {
        $ucastnik = $this->ucastnik();
        [$prvni] = $this->vytvorNoc(0);
        [$druha] = $this->vytvorNoc(1);

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
        [$prvni] = $this->vytvorNoc(0);
        [$druha] = $this->vytvorNoc(1);

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
        [$prvni] = $this->vytvorNoc(0);
        [$druha] = $this->vytvorNoc(1);
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
        $streda = $this->vytvorNocSKodem($kod . '_st', 0);
        $ctvrtek = $this->vytvorNocSKodem($kod . '_ct', 1);

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
        $this->vytvorNocSKodem($kod . '_st', 0);

        $this->expectException(\RuntimeException::class);

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

        $this->expectException(\RuntimeException::class);

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
        [$prvni] = $this->vytvorNoc(1);
        [$druha] = $this->vytvorNoc(2);

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
        [$prvni] = $this->vytvorNoc(1);
        [$druha] = $this->vytvorNoc(2);

        $this->import()->zacniTransakci();
        $this->import()->ulozPokoj($ucastnik->getId(), 'B309', 1, 2, self::ROK);
        $this->import()->ulozNociUcastnika($ucastnik->getId(), [$prvni, $druha], self::ROK, false);
        $this->import()->vratTransakci();

        self::assertSame([], $this->pokojePodleDnu($ucastnik), 'Pokoj se odrolovat musí');
        self::assertSame(0, $this->pocetNoci($ucastnik), 'Noci se odrolovat musí');
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
        [$prvni] = $this->vytvorNoc(1);
        [$druha] = $this->vytvorNoc(2);
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
        [$prvni] = $this->vytvorNoc(1);
        [$druha] = $this->vytvorNoc(2);

        $this->import()->zacniTransakci();
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
        $this->expectException(\RuntimeException::class);

        $this->import()->ulozNociUcastnika(-1, [], self::ROK, false);
    }
}
