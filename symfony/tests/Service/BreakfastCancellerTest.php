<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\BreakfastCanceller;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Snídaně v ceně hotelové noci se ruší; tyhle testy drží, co přesně to znamená, protože
 * `cancelCovered()` je nově jediná cesta, jak se ruší (legacy `zrusSnidaneProHotelovePokoje`
 * dělala totéž vlastním dotazem).
 */
class BreakfastCancellerTest extends AbstractDatabaseKernelTestCase
{
    private const ROK = 2026;

    private const STREDA = 0;

    private const CTVRTEK = 1;

    private const PATEK = 2;

    private const SOBOTA = 3;

    private function canceller(): BreakfastCanceller
    {
        return static::getContainer()->get(BreakfastCanceller::class);
    }

    private function ucastnik(): User
    {
        /** @var User $ucastnik */
        $ucastnik = UserFactory::createOne([
            UserEntityStructure::login => 'snidane_' . uniqid(),
            UserEntityStructure::email => 'snidane_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $ucastnik;
    }

    private function tag(ProductTagCode $kod): ProductTag
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :code, NOW())',
            [
                'code' => $kod->value,
            ],
        );

        $tag = $this->entityManager()->getRepository(ProductTag::class)->findOneBy([
            'code' => $kod->value,
        ]);
        self::assertNotNull($tag);

        return $tag;
    }

    private function vytvorVariantu(
        string         $nazev,
        int            $den,
        ProductTagCode $kategorie,
        bool           $snidaneVCene = false,
    ): ProductVariant {
        $kod = strtolower(str_replace(' ', '_', $nazev)) . '_' . uniqid();

        $produkt = new Product();
        $produkt->setName($nazev);
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('500.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->setAccommodationDay($den);
        $produkt->setProducedQuantity(10);
        $produkt->setBreakfastIncluded($snidaneVCene);
        $produkt->addTag($this->tag($kategorie));
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setName($nazev);
        $varianta->setCode($kod);
        $varianta->setAccommodationDay($den);
        $varianta->setPrice('500.00');
        $varianta->setPosition(0);
        $varianta->setRemainingQuantity(10);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return $varianta;
    }

    private function objednej(User $ucastnik, ProductVariant $varianta): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
             VALUES (:customer, :product, :variant, :year, :cena, NOW())',
            [
                'customer' => $ucastnik->getId(),
                'product'  => $varianta->getProduct()?->getId(),
                'variant'  => $varianta->getId(),
                'year'     => self::ROK,
                'cena'     => $varianta->getPrice(),
            ],
        );
    }

    private function pocetNakupu(User $ucastnik, ProductVariant $varianta): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy
             WHERE id_uzivatele = :customer AND variant_id = :variant AND rok = :year',
            [
                'customer' => $ucastnik->getId(),
                'variant'  => $varianta->getId(),
                'year'     => self::ROK,
            ],
        );
    }

    public function testBreakfastCoveredByAHotelNightIsCancelled(): void
    {
        $ucastnik = $this->ucastnik();
        $hotel = $this->vytvorVariantu('Dvojlůžák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $snidane = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $hotel);
        $this->objednej($ucastnik, $snidane);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame([$snidane->getId()], $zrusene);
        self::assertSame(0, $this->pocetNakupu($ucastnik, $snidane));
    }

    /**
     * Snídaně je v ceně jen u hotelu; spacák ani kolej ji nekryjí.
     */
    public function testBreakfastIsKeptForNonHotelAccommodation(): void
    {
        $ucastnik = $this->ucastnik();
        $spacak = $this->vytvorVariantu('Spacák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI);
        $snidane = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $spacak);
        $this->objednej($ucastnik, $snidane);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame([], $zrusene);
        self::assertSame(1, $this->pocetNakupu($ucastnik, $snidane));
    }

    /**
     * V ceně hotelu je jen snídaně, ne celá penze.
     */
    public function testLunchAndDinnerAreNeverCancelled(): void
    {
        $ucastnik = $this->ucastnik();
        $hotel = $this->vytvorVariantu('Dvojlůžák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $obed = $this->vytvorVariantu('Oběd pátek', self::PATEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $hotel);
        $this->objednej($ucastnik, $obed);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame([], $zrusene);
        self::assertSame(1, $this->pocetNakupu($ucastnik, $obed));
    }

    /**
     * Noc ze čtvrtka na pátek kryje páteční ráno, sobotní ne.
     */
    public function testOnlyTheMorningAfterAHotelNightIsCancelled(): void
    {
        $ucastnik = $this->ucastnik();
        $hotel = $this->vytvorVariantu('Dvojlůžák čtvrtek', self::CTVRTEK, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $snidanePatek = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $snidaneSobota = $this->vytvorVariantu('Snídaně sobota', self::SOBOTA, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $hotel);
        $this->objednej($ucastnik, $snidanePatek);
        $this->objednej($ucastnik, $snidaneSobota);

        $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame(0, $this->pocetNakupu($ucastnik, $snidanePatek));
        self::assertSame(1, $this->pocetNakupu($ucastnik, $snidaneSobota), 'Sobotní ráno žádná hotelová noc nekryje');
    }

    public function testNothingIsCancelledWithoutAHotelNight(): void
    {
        $ucastnik = $this->ucastnik();
        $snidane = $this->vytvorVariantu('Snídaně pátek', self::PATEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $snidane);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame([], $zrusene);
        self::assertSame(1, $this->pocetNakupu($ucastnik, $snidane));
    }

    /**
     * Středeční noc kryje čtvrteční ráno — ať je vidět, že se den odvozuje a nejde o pevné
     * „pátek".
     */
    public function testTheCoveredMorningFollowsTheNight(): void
    {
        $ucastnik = $this->ucastnik();
        $hotel = $this->vytvorVariantu('Dvojlůžák středa', self::STREDA, ProductTagCode::UBYTOVANI, snidaneVCene: true);
        $snidane = $this->vytvorVariantu('Snídaně čtvrtek', self::CTVRTEK, ProductTagCode::JIDLO);
        $this->objednej($ucastnik, $hotel);
        $this->objednej($ucastnik, $snidane);

        $zrusene = $this->canceller()->cancelCovered($ucastnik, self::ROK);

        self::assertSame([$snidane->getId()], $zrusene);
        self::assertSame(0, $this->pocetNakupu($ucastnik, $snidane));
    }
}
