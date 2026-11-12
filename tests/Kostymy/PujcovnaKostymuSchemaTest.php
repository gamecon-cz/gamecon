<?php

declare(strict_types=1);

namespace Gamecon\Tests\Kostymy;

use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;
use Gamecon\Uzivatel\UzivatelSlucovani;

/**
 * Co hlídá samotná databáze: rezervace stojí na přihlášce a zmizí s ní, výdej rezervaci přežije.
 */
class PujcovnaKostymuSchemaTest extends AbstractUzivatelTestDb
{
    use KostymyTestTrait;

    protected static bool $disableStrictTransTables = true;

    public function testSmazanimPrihlaskyZmiziRezervace(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        self::vlozPrihlasku($idAktivity, $ucastnik->id());
        self::vlozRezervaci(self::vlozKostym(), $idAktivity, $ucastnik->id());

        dbQuery('DELETE FROM akce_prihlaseni WHERE id_akce = $0 AND id_uzivatele = $1', [$idAktivity, $ucastnik->id()]);

        self::assertSame([], self::rezervaceAktivity($idAktivity));
    }

    public function testVydejPrezijeZanikRezervaceADrziSiAktivitu(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        $idKostymu = self::vlozKostym();
        self::vlozPrihlasku($idAktivity, $ucastnik->id());
        $idRezervace = self::vlozRezervaci($idKostymu, $idAktivity, $ucastnik->id());
        $idVydeje = self::vlozVydej($idKostymu, $idRezervace, $idAktivity, $ucastnik->id());

        dbQuery('DELETE FROM akce_prihlaseni WHERE id_akce = $0 AND id_uzivatele = $1', [$idAktivity, $ucastnik->id()]);

        $vydej = dbOneLine('SELECT id_rezervace, id_akce, id_uzivatele, vraceno FROM kostym_vydej WHERE id = $0', [$idVydeje]);
        self::assertNull($vydej['id_rezervace']);
        self::assertSame((string) $idAktivity, $vydej['id_akce']);
        self::assertSame((string) $ucastnik->id(), $vydej['id_uzivatele']);
        self::assertNull($vydej['vraceno']);
    }

    public function testRezervaceBezPrihlaskyNeprojde(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();

        $this->expectException(\DbException::class);
        self::vlozRezervaci(self::vlozKostym(), $idAktivity, $ucastnik->id());
    }

    public function testSledujiciNemaPrihlaskuANeprojde(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        dbInsert('akce_prihlaseni_spec', [
            'id_akce'             => $idAktivity,
            'id_uzivatele'        => $ucastnik->id(),
            'id_stavu_prihlaseni' => StavPrihlaseni::SLEDUJICI,
        ]);

        $this->expectException(\DbException::class);
        self::vlozRezervaci(self::vlozKostym(), $idAktivity, $ucastnik->id());
    }

    public function testJedenKostymNaOsobuAAktivitu(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        self::vlozPrihlasku($idAktivity, $ucastnik->id());
        self::vlozRezervaci(self::vlozKostym('Plášť'), $idAktivity, $ucastnik->id());

        $this->expectException(\DbDuplicateEntryException::class);
        self::vlozRezervaci(self::vlozKostym('Klobouk'), $idAktivity, $ucastnik->id());
    }

    public function testKostymSRezervaciNejdeSmazat(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        $idKostymu = self::vlozKostym();
        self::vlozPrihlasku($idAktivity, $ucastnik->id());
        self::vlozRezervaci($idKostymu, $idAktivity, $ucastnik->id());

        $this->expectException(\DbException::class);
        dbQuery('DELETE FROM kostym WHERE id = $0', [$idKostymu]);
    }

    public function testSmazanimKostymuZmiziJehoFotky(): void
    {
        $idKostymu = self::vlozKostym();
        dbInsert('kostym_foto', [
            'id_kostymu' => $idKostymu,
            'soubor'     => "{$idKostymu}-1.avif",
            'poradi'     => 1,
        ]);

        dbQuery('DELETE FROM kostym WHERE id = $0', [$idKostymu]);

        self::assertSame('0', (string) dbOneCol('SELECT COUNT(*) FROM kostym_foto WHERE id_kostymu = $0', [$idKostymu]));
    }

    public function testSloucenimSeRezervacePreneseSPrihlaskou(): void
    {
        $stary = self::prihlasenyUzivatel();
        $novy = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        $idKostymu = self::vlozKostym();
        self::vlozPrihlasku($idAktivity, $stary->id());
        $idRezervace = self::vlozRezervaci($idKostymu, $idAktivity, $stary->id());
        $idVydeje = self::vlozVydej($idKostymu, $idRezervace, $idAktivity, $stary->id());

        (new UzivatelSlucovani())->sluc($stary, $novy, []);

        self::assertSame(
            [[
                'id_kostymu'   => (string) $idKostymu,
                'id_akce'      => (string) $idAktivity,
                'id_uzivatele' => (string) $novy->id(),
            ]],
            self::rezervaceAktivity($idAktivity),
        );
        self::assertSame(
            [
                'id_rezervace' => (string) $idRezervace,
                'id_uzivatele' => (string) $novy->id(),
            ],
            dbOneLine('SELECT id_rezervace, id_uzivatele FROM kostym_vydej WHERE id = $0', [$idVydeje]),
        );
    }

    /**
     * Oba účty na stejné aktivitě: přihláška starého koliduje s přihláškou nového, slučování ji smaže
     * a s ní i rezervaci starého. Zůstane rezervace nového účtu, vydaný kostým zůstane u nového účtu.
     */
    public function testSlouceniObouUctuNaStejneAktiviteProjde(): void
    {
        $stary = self::prihlasenyUzivatel();
        $novy = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        $idKostymuStareho = self::vlozKostym('Plášť');
        $idKostymuNoveho = self::vlozKostym('Klobouk');
        self::vlozPrihlasku($idAktivity, $stary->id());
        self::vlozPrihlasku($idAktivity, $novy->id());
        $idRezervaceStareho = self::vlozRezervaci($idKostymuStareho, $idAktivity, $stary->id());
        self::vlozRezervaci($idKostymuNoveho, $idAktivity, $novy->id());
        $idVydeje = self::vlozVydej($idKostymuStareho, $idRezervaceStareho, $idAktivity, $stary->id());

        (new UzivatelSlucovani())->sluc($stary, $novy, []);

        self::assertSame(
            [[
                'id_kostymu'   => (string) $idKostymuNoveho,
                'id_akce'      => (string) $idAktivity,
                'id_uzivatele' => (string) $novy->id(),
            ]],
            self::rezervaceAktivity($idAktivity),
        );
        self::assertSame(
            [
                'id_rezervace' => null,
                'id_uzivatele' => (string) $novy->id(),
            ],
            dbOneLine('SELECT id_rezervace, id_uzivatele FROM kostym_vydej WHERE id = $0', [$idVydeje]),
        );
    }

    /**
     * Rezervace starého účtu stojí na jeho přihlášce, ta koliduje s přihláškou nového účtu a zmizí i s rezervací.
     */
    public function testSloucenimZmiziRezervaceStarehoUctuKdyzJeNovyNaStejneAktivite(): void
    {
        $stary = self::prihlasenyUzivatel();
        $novy = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        self::vlozPrihlasku($idAktivity, $stary->id());
        self::vlozPrihlasku($idAktivity, $novy->id());
        self::vlozRezervaci(self::vlozKostym(), $idAktivity, $stary->id());

        (new UzivatelSlucovani())->sluc($stary, $novy, []);

        self::assertSame([], self::rezervaceAktivity($idAktivity));
        self::assertSame(
            [(string) $novy->id()],
            dbFetchColumn('SELECT id_uzivatele FROM akce_prihlaseni WHERE id_akce = $0', [$idAktivity]),
        );
    }
}
