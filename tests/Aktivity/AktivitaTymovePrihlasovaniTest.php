<?php

namespace Gamecon\Tests\Aktivity;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;
use Gamecon\Aktivita\Aktivita;
use Uzivatel;

class AktivitaTymovePrihlasovaniTest extends AbstractUzivatelTestDb
{
    use ProbihaRegistraceAktivitTrait;

    private ?SystemoveNastaveni $systemoveNastaveni = null;
    private ?Aktivita           $ctvrtfinale        = null;
    private ?Aktivita           $semifinaleA        = null;
    private ?Aktivita           $semifinaleB        = null;
    private ?Aktivita           $finale             = null;
    private ?Uzivatel           $tymlidr            = null;
    private ?Uzivatel           $clen1              = null;
    private ?Uzivatel           $clen2              = null;

    protected static bool $disableStrictTransTables = true;

    protected static function getInitData(): string
    {
        return file_get_contents(__DIR__ . '/data/aktivita_tymove_prihlasovani_test.csv');
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->systemoveNastaveni = self::vytvorSystemoveNastaveni();
            $this->ctvrtfinale        = Aktivita::zId(1, false, $this->systemoveNastaveni);
            $this->semifinaleA        = Aktivita::zId(2, false, $this->systemoveNastaveni);
            $this->semifinaleB        = Aktivita::zId(3, false, $this->systemoveNastaveni);
            $this->finale             = Aktivita::zId(4, false, $this->systemoveNastaveni);

            $this->tymlidr = self::prihlasenyUzivatel();
            $this->clen1   = self::prihlasenyUzivatel();
            $this->clen2   = self::prihlasenyUzivatel();
        } catch (\Throwable $throwable) {
            $this->tearDown();
            throw $throwable;
        }
    }

    public function testOdhlaseniPosledniho()
    {
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr, Aktivita::UKAZAT_DETAILY_CHYBY);
        $this->ctvrtfinale->prihlasTym(
            [$this->clen1],
            $this->tymlidr,
            'Miiistřiii světááá',
            2,
            [$this->semifinaleA, $this->finale],
            Aktivita::UKAZAT_DETAILY_CHYBY,
        );

        self::assertEquals(2, $this->ctvrtfinale->rawDb()['kapacita']);

        // počet míst se obnoví
        $this->ctvrtfinale->odhlas($this->tymlidr, $this->tymlidr, 'test');
        $this->ctvrtfinale->odhlas($this->clen1, $this->clen1, 'test');
        self::assertEquals(3, $this->ctvrtfinale->rawDb()['kapacita']);

        // opětovné přihlášení se chová jako u týmovky, tj. jako přihlášení týmlídra
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr);

        self::expectException(\Chyba::class);
        self::expectExceptionMessage('Aktivitu už někdo zabral');
        $this->ctvrtfinale->prihlas($this->clen1, $this->clen1);
    }

    public function testOdhlaseniPredPotvrzenim()
    {
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr);

        $this->ctvrtfinale->odhlas($this->tymlidr, $this->tymlidr, 'test');
        $this->ctvrtfinale->prihlas($this->clen1, $this->clen1);
        self::assertTrue($this->ctvrtfinale->prihlasen($this->clen1));
    }

    public function testOmezeniKapacity()
    {
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr);
        $this->ctvrtfinale->prihlasTym([$this->clen1], $this->tymlidr, null, 2, [$this->semifinaleA, $this->finale]);
        $this->expectException(\Chyba::class);
        $this->expectExceptionMessage('Místa jsou už plná');
        $this->ctvrtfinale->prihlas($this->clen2, $this->clen2);
    }

    public static function provideNastaveniKapacity(): array
    {
        return [
            [null, 3],
            [2, 2],
        ];
    }

    /**
     * @dataProvider provideNastaveniKapacity
     */
    public function testZmenaKapacity(
        $nastaveno,
        $ocekavano,
    ) {
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr);
        $this->ctvrtfinale->prihlasTym([$this->clen1], $this->tymlidr, null, $nastaveno, [$this->semifinaleA, $this->finale]);
        $this->ctvrtfinale->refresh();
        $this->assertEquals($ocekavano, $this->ctvrtfinale->rawDb()['kapacita']);
    }

    public function testPrihlaseniDalsiho()
    {
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr);
        $this->ctvrtfinale->prihlasTym([$this->clen1], $this->tymlidr, null, 3, [$this->semifinaleA, $this->finale]);
        $this->ctvrtfinale->prihlas($this->clen2, $this->clen2);

        // TODO nutnost refreshování vyplývá z chybějících identity map, spravit
        $this->ctvrtfinale->refresh();
        $this->semifinaleA->refresh();
        $this->semifinaleB->refresh();
        $this->finale->refresh();

        self::assertTrue($this->ctvrtfinale->prihlasen($this->clen2));
        self::assertTrue($this->semifinaleA->prihlasen($this->clen2));
        self::assertTrue($this->finale->prihlasen($this->clen2));

        self::assertFalse($this->semifinaleB->prihlasen($this->clen2));
    }

    public function testPrihlaseniTymlidra()
    {
        // aktivita se zamče
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr);
        try {
            $this->ctvrtfinale->prihlas($this->clen1, $this->clen1);
            self::fail('Aktivita musí být zamčená a přihlášení dalšího člověka musí selhat.');
        } catch (\Exception $e) {
        }

        // je přihlášen na první kolo
        self::assertTrue($this->ctvrtfinale->prihlasen($this->tymlidr));

        // není přihlášen na další kola
        foreach ($this->ctvrtfinale->dalsiKola() as $kolo) {
            foreach ($kolo as $varianta) {
                self::assertFalse($varianta->prihlasen($this->tymlidr));
            }
        }
    }

    public function testPrihlaseniTymu()
    {
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr);
        $this->ctvrtfinale->prihlasTym([$this->clen1], $this->tymlidr, null, null, [$this->semifinaleA, $this->finale]);

        // TODO nutnost refreshování vyplývá z chybějících identity map, spravit
        $this->ctvrtfinale->refresh();
        $this->semifinaleA->refresh();
        $this->semifinaleB->refresh();
        $this->finale->refresh();

        foreach ([$this->tymlidr, $this->clen1] as $hrac) {
            self::assertTrue($this->ctvrtfinale->prihlasen($hrac));
            self::assertTrue($this->semifinaleA->prihlasen($hrac));
            self::assertTrue($this->finale->prihlasen($hrac));

            self::assertFalse($this->semifinaleB->prihlasen($hrac));
        }
    }

    public function testPrihlaseniTymuOpakovaneNelze()
    {
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr);
        $this->ctvrtfinale->prihlasTym([], $this->tymlidr, null, null, [$this->semifinaleA, $this->finale]);
        // TODO co když by se přihlašoval na jiné čtvrtfinále?
        $this->expectException(\Exception::class);
        $this->ctvrtfinale->prihlasTym([], $this->tymlidr, null, null, [$this->semifinaleB, $this->finale]);
    }

    /**
     * @dataProvider provideSpatnaVolbaDalsichKol
     */
    public function testSpatnaVolbaDalsichKolNelze(array $dalsiKolaIds)
    {
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nepovolený výběr dalších kol.');
        $this->ctvrtfinale->prihlasTym([], $this->tymlidr, null, null, array_map(function (
            $id,
        ) {
            return Aktivita::zId($id);
        }, $dalsiKolaIds));
    }

    public static function provideSpatnaVolbaDalsichKol(): array
    {
        return [
            'nevybrání ničeho'        => [[]],
            'vybrání i čtvrtfinále'   => [[1, 2, 4]],
            'vybrání dvou semifinále' => [[2, 3, 4]],
            'nevybrání finále'        => [[2]],
            'špatné pořadí'           => [[4, 2]],
            'smetí navíc'             => [[2, 4, 5]],
        ];
    }


    // TODO další scénáře:
    //  nevalidní ne-první člen
    //    všechno se rollbackne
    //    (že přihlášení jednoho člověka háže výjimku např. při překrytí tady netřeba testovat)

}
