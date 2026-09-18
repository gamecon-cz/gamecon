<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\AccommodationAvailability;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Kapacita ubytování se nepočítá ze zásoby na variantě, ale z `kusu_vyrobeno` proti
 * `shop_nakupy` — legacy to tak dělá a legacy do `shop_nakupy` pořád zapisuje. Testy drží
 * tři pravidla, která se z toho čtou špatně: rezervace pro orgy se odečítá jen účastníkovi,
 * vlastní koupená noc se přičítá zpátky, a nabídku rozhoduje řádek té noci, ne rodiče.
 */
class AccommodationAvailabilityTest extends AbstractDatabaseKernelTestCase
{
    private const ROK = 2026;

    private function availability(): AccommodationAvailability
    {
        return static::getContainer()->get(AccommodationAvailability::class);
    }

    private function zakaznik(): User
    {
        /** @var User $zakaznik */
        $zakaznik = UserFactory::createOne([
            UserEntityStructure::login => 'host_' . uniqid(),
            UserEntityStructure::email => 'host_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $zakaznik;
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
     * Jedna noc jednoho typu pokoje. `kusu_vyrobeno` i `rezervovano` sedí na řádku té noci,
     * protože právě odtud se čtou — rodič variant je jiná noc.
     */
    private function vytvorNoc(?int $kapacita, ?int $rezervovano = null): ProductVariant
    {
        $kod = 'noc-' . uniqid();

        $produkt = new Product();
        $produkt->setName('Postel na 2L koleji středa');
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('400.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->setAccommodationDay(0);
        $produkt->setProducedQuantity($kapacita);
        $produkt->addTag($this->tagUbytovani());
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setName('středa');
        // Varianta ubytování nese tentýž kod_predmetu jako produkt — kapacita se dohledává
        // podle kódu varianty.
        $varianta->setCode($kod);
        $varianta->setPrice('400.00');
        $varianta->setPosition(0);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        if ($rezervovano !== null) {
            $this->connection()->executeStatement(
                'UPDATE shop_predmety SET reserved_for_organizers = :rez WHERE id_predmetu = :id',
                [
                    'rez' => $rezervovano,
                    'id'  => $produkt->getId(),
                ],
            );
        }

        return $varianta;
    }

    private function prodej(ProductVariant $varianta, User $komu): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
             VALUES (:u, :p, :v, :rok, 400, NOW())',
            [
                'u'   => $komu->getId(),
                'p'   => $varianta->getProduct()->getId(),
                'v'   => $varianta->getId(),
                'rok' => self::ROK,
            ],
        );
    }

    /**
     * @test
     */
    public function volnaNocHlasiCelouKapacitu(): void
    {
        $zakaznik = $this->zakaznik();
        $noc = $this->vytvorNoc(kapacita: 5);

        $dostupnost = $this->availability()->proZakaznika($zakaznik, self::ROK, jeOrganizator: false);

        self::assertSame(5, $dostupnost[$noc->getCode()]->remaining);
        self::assertFalse($dostupnost[$noc->getCode()]->soldOut());
    }

    /**
     * @test
     */
    public function prodanaMistaSeOdectou(): void
    {
        $zakaznik = $this->zakaznik();
        $noc = $this->vytvorNoc(kapacita: 5);
        $this->prodej($noc, $this->zakaznik());
        $this->prodej($noc, $this->zakaznik());

        $dostupnost = $this->availability()->proZakaznika($zakaznik, self::ROK, jeOrganizator: false);

        self::assertSame(3, $dostupnost[$noc->getCode()]->remaining);
    }

    /**
     * Vlastní koupená noc se přičte zpátky, jinak by ji zákazník nemohl odškrtnout —
     * mřížka by ji ukázala jako vyprodanou.
     *
     * @test
     */
    public function vlastniKoupenaNocSePricteZpatky(): void
    {
        $zakaznik = $this->zakaznik();
        $noc = $this->vytvorNoc(kapacita: 1);
        $this->prodej($noc, $zakaznik);

        $dostupnost = $this->availability()->proZakaznika($zakaznik, self::ROK, jeOrganizator: false);

        self::assertSame(1, $dostupnost[$noc->getCode()]->remaining, 'Svou noc musí zákazník pořád vidět jako dostupnou');
        self::assertFalse($dostupnost[$noc->getCode()]->soldOut());
    }

    /**
     * @test
     */
    public function rezervaceProOrgySeUcastnikoviOdecte(): void
    {
        $zakaznik = $this->zakaznik();
        $noc = $this->vytvorNoc(kapacita: 5, rezervovano: 2);

        $ucastnik = $this->availability()->proZakaznika($zakaznik, self::ROK, jeOrganizator: false);
        $organizator = $this->availability()->proZakaznika($zakaznik, self::ROK, jeOrganizator: true);

        self::assertSame(3, $ucastnik[$noc->getCode()]->remaining, 'Účastník rezervu nevidí');
        self::assertSame(5, $organizator[$noc->getCode()]->remaining, 'Organizátor na rezervu dosáhne');
    }

    /**
     * @test
     */
    public function neomezenaKapacitaNemaZbytek(): void
    {
        $zakaznik = $this->zakaznik();
        $noc = $this->vytvorNoc(kapacita: null);

        $dostupnost = $this->availability()->proZakaznika($zakaznik, self::ROK, jeOrganizator: false);

        self::assertNull($dostupnost[$noc->getCode()]->remaining);
        self::assertFalse($dostupnost[$noc->getCode()]->soldOut());
    }

    /**
     * Zbytek nesmí spadnout pod nulu — pult smí přeprodat, ale mřížka pak nemá ukazovat
     * záporné číslo jako „volno".
     *
     * @test
     */
    public function preprodanaNocNejdeDoMinusu(): void
    {
        $zakaznik = $this->zakaznik();
        $noc = $this->vytvorNoc(kapacita: 1);
        $this->prodej($noc, $this->zakaznik());
        $this->prodej($noc, $this->zakaznik());

        $dostupnost = $this->availability()->proZakaznika($zakaznik, self::ROK, jeOrganizator: false);

        self::assertSame(0, $dostupnost[$noc->getCode()]->remaining);
        self::assertTrue($dostupnost[$noc->getCode()]->soldOut());
    }
}
