<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Ubytovani;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\PodtypPredmetu;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Ubytovani\HotelovePokoje;
use Gamecon\Ubytovani\UbytovaciSkupiny;

class HotelovePokojeTest extends AbstractTestDb
{
    private const int CTVRTEK = DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK;
    private const int PATEK = DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK;
    private const int SOBOTA = DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    private function vytvorUzivatele(): \Uzivatel
    {
        $unikat = uniqid();
        dbQuery(<<<'SQL'
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Host',
    prijmeni_uzivatele = 'Hotelu'
SQL,
            [
                0 => 'test_hotel_' . $unikat,
                1 => 'test.hotel.' . $unikat . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    /**
     * Thursday to Saturday night of one type, coded like the real ones ("Hs-2L-ct").
     *
     * @return array<int, int> item id by night
     */
    private function vytvorTyp(string $typ, ?string $podtyp): array
    {
        $kodTypu = 'X' . uniqid() . '-' . $typ;
        $ids = [];
        foreach ([
            self::CTVRTEK => ['ct', 'čtvrtek'],
            self::PATEK   => ['pa', 'pátek'],
            self::SOBOTA  => ['so', 'sobota'],
        ] as $noc => [$pripona, $den]) {
            dbQuery(<<<'SQL'
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    model_rok = $2,
    cena_aktualni = 900,
    stav = $3,
    kusu_vyrobeno = 10,
    typ = $4,
    podtyp = $5,
    ubytovani_den = $6
SQL,
                [
                    0 => "Postel na {$typ} {$den}",
                    1 => "{$kodTypu}-{$pripona}",
                    2 => ROCNIK,
                    3 => StavPredmetu::VEREJNY,
                    4 => TypPredmetu::UBYTOVANI,
                    5 => $podtyp,
                    6 => $noc,
                ],
            );
            $ids[$noc] = dbInsertId();
        }

        return $ids;
    }

    /**
     * @param int[] $idsPredmetu
     */
    private function objednej(\Uzivatel $uzivatel, array $idsPredmetu): void
    {
        foreach ($idsPredmetu as $idPredmetu) {
            dbQuery(<<<'SQL'
INSERT INTO shop_nakupy SET id_uzivatele = $0, id_predmetu = $1, rok = $2, cena_nakupni = 900, datum = NOW()
SQL,
                [
                    0 => $uzivatel->id(),
                    1 => $idPredmetu,
                    2 => ROCNIK,
                ],
            );
        }
    }

    /**
     * @test
     */
    public function skupinaDrziPokojNaKazdouNocKdyNaNemNekdoZNiSpi(): void
    {
        $hotel = $this->vytvorTyp('Hs-2L', PodtypPredmetu::HOTEL);
        $zakladatel = $this->vytvorUzivatele();
        $kamarad = $this->vytvorUzivatele();
        $this->objednej($zakladatel, [$hotel[self::CTVRTEK], $hotel[self::PATEK]]);
        $this->objednej($kamarad, [$hotel[self::PATEK], $hotel[self::SOBOTA]]);
        $skupiny = new UbytovaciSkupiny(ROCNIK);
        $kod = $skupiny->zaloz($zakladatel);
        $skupiny->pripoj($kamarad, $kod);

        $drzene = (new HotelovePokoje(ROCNIK))->drzenePostele();

        self::assertSame(1, $drzene[$hotel[self::CTVRTEK]] ?? 0, 'Ve čtvrtek spí na pokoji jen zakladatel');
        self::assertSame(0, $drzene[$hotel[self::PATEK]] ?? 0, 'V pátek je pokoj plný');
        self::assertSame(1, $drzene[$hotel[self::SOBOTA]] ?? 0, 'V sobotu spí na pokoji jen kamarád');
    }

    /**
     * @test
     */
    public function postelePrimoVlastniSkupinySeNepocitaji(): void
    {
        $hotel = $this->vytvorTyp('Hs-2L', PodtypPredmetu::HOTEL);
        $zakladatel = $this->vytvorUzivatele();
        $this->objednej($zakladatel, $hotel);
        $kod = (new UbytovaciSkupiny(ROCNIK))->zaloz($zakladatel);

        $drzene = (new HotelovePokoje(ROCNIK))->drzenePostele(kodSkupinyNepocitat: $kod);

        self::assertArrayNotHasKey($hotel[self::CTVRTEK], $drzene);
    }

    /**
     * @test
     */
    public function skupinaNaKolejiPosteleNedrzi(): void
    {
        $kolej = $this->vytvorTyp('2L', null);
        $zakladatel = $this->vytvorUzivatele();
        $this->objednej($zakladatel, $kolej);
        (new UbytovaciSkupiny(ROCNIK))->zaloz($zakladatel);

        $obsazenost = (new HotelovePokoje(ROCNIK))->obsazenost();

        self::assertArrayNotHasKey($kolej[self::CTVRTEK], $obsazenost);
    }

    /**
     * @test
     */
    public function obsazenostScitaProdaneADrzenePostele(): void
    {
        $hotel = $this->vytvorTyp('Hs-2L', PodtypPredmetu::HOTEL);
        $zakladatel = $this->vytvorUzivatele();
        $jednotlivec = $this->vytvorUzivatele();
        $this->objednej($zakladatel, $hotel);
        $this->objednej($jednotlivec, $hotel);
        (new UbytovaciSkupiny(ROCNIK))->zaloz($zakladatel);

        $ctvrtek = (new HotelovePokoje(ROCNIK))->obsazenost()[$hotel[self::CTVRTEK]];

        self::assertSame(10, $ctvrtek['kapacita']);
        self::assertSame(2, $ctvrtek['prodano']);
        self::assertSame(1, $ctvrtek['drzeno']);
        self::assertSame('Postel na Hs-2L', $ctvrtek['nazev']);
    }
}
