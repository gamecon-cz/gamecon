<?php

declare(strict_types=1);

namespace Gamecon\Tests\HodnoceniTurnaje;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\HodnoceniTurnaje\HodnoceniTurnaje;
use Gamecon\HodnoceniTurnaje\HodnoceniTurnajeException;
use Gamecon\HodnoceniTurnaje\KategorieHodnoceni;
use Gamecon\HodnoceniTurnaje\KriteriumHodnoceni;
use Gamecon\HodnoceniTurnaje\ParserSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\SablonaHodnoceni;
use Gamecon\HodnoceniTurnaje\ZmenaKriteriiChcePotvrzeni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Clock\MockClock;

/**
 * Hodnocení skupin turnaje D&D: vypravěč hodnotí skupiny z kola, které vede, od jeho začátku a dokud org hodnocení
 * neuzavře; hodnocení je jedno na skupinu a kolo, cizí přepíše jen org turnaje a cizí hodnoty vypravěč nevidí. Org
 * zadává nové hodnocení za vypravěče kola. Výsledky sčítají body po kategoriích jen za kola, která skupina hraje,
 * shodu celku rozhodne nejlepší, pak nejslabší kategorie, shoda ve všem dělí místo. Kritéria z editoru se ukládají podle id: přejmenování a přeřazení hodnocení nechá, co by
 * smazalo hodnoty, chce potvrzení. Soubor se páruje podle názvů.
 */
class HodnoceniTurnajeTest extends AbstractTestDb
{
    private const int ROK = 2030;

    /**
     * Po prvních dvou kolech prvního běhu a prvním kole druhého, před třetím kolem prvního.
     */
    private const string TED = '2030-07-20 12:00:00';

    private const string SABLONA = <<<'TEXT'
        Škála: 1 2 3 4 5
        # Hraní rolí
        ## Postavy
        ## Spolupráce
        # Řešení
        ## Nápaditost
        TEXT;

    private int $vypravecA;
    private int $vypravecB;
    private int $vypravecDruhehoBehu;
    private int $cizi;
    private int $org;
    private int $prvniKolo;
    private int $druheKolo;
    private int $tretiKolo;
    private int $druhyBehPrvniKolo;
    private int $koloLegend;
    private int $loniKolo;
    private int $paraBola;
    private int $bezNazvu;
    private int $ovecky;

    protected function setUp(): void
    {
        parent::setUp();

        // jinak XTemplate přeloží šablonu stránky vedle zdroje, do stromu repozitáře
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);

        $this->vypravecA = $this->vytvorUzivatele('a');
        $this->vypravecB = $this->vytvorUzivatele('b');
        $this->vypravecDruhehoBehu = $this->vytvorUzivatele('c');
        $this->cizi = $this->vytvorUzivatele('cizi');
        $this->org = $this->vytvorUzivatele('org');

        $prvniBeh = $this->vytvorTurnaj('D&D turnaj 1. běh', self::ROK);
        $druhyBeh = $this->vytvorTurnaj('D&D turnaj 2. běh', self::ROK);
        $legendy = $this->vytvorTurnaj('Legendy', self::ROK);
        $loni = $this->vytvorTurnaj('D&D turnaj loni', self::ROK - 1);

        $this->prvniKolo = $this->vytvorKolo($prvniBeh, 1, '2030-07-18 14:00:00', [$this->vypravecA, $this->vypravecB]);
        $this->druheKolo = $this->vytvorKolo($prvniBeh, 2, '2030-07-18 19:00:00', [$this->vypravecA, $this->vypravecB]);
        $this->tretiKolo = $this->vytvorKolo($prvniBeh, 3, '2030-07-25 09:00:00', [$this->vypravecA, $this->vypravecB]);
        $this->druhyBehPrvniKolo = $this->vytvorKolo($druhyBeh, 1, '2030-07-19 14:00:00', [$this->vypravecDruhehoBehu]);
        $this->koloLegend = $this->vytvorKolo(
            $legendy,
            1,
            '2030-07-18 14:00:00',
            [$this->vypravecA],
            TypAktivity::LKD,
        );
        $this->loniKolo = $this->vytvorKolo($loni, 1, '2029-07-18 14:00:00', [$this->vypravecA], rok: self::ROK - 1);

        $this->paraBola = $this->vytvorTym('Para Bola', [$this->prvniKolo, $this->druheKolo, $this->tretiKolo]);
        $this->bezNazvu = $this->vytvorTym(null, [$this->prvniKolo, $this->druheKolo, $this->tretiKolo]);
        $this->ovecky = $this->vytvorTym('Ovečky', [$this->druhyBehPrvniKolo]);
        $this->vytvorTym('Legendární', [$this->koloLegend]);
    }

    public function testUlozeniVytvoriSablonu(): void
    {
        self::assertNull($this->vytvorHodnoceni()->getSablona());

        $hodnoceni = $this->vytvorHodnoceni();
        self::assertSame(0, $hodnoceni->ulozSablonu(ParserSablonyHodnoceni::rozeber(self::SABLONA), $this->org));

        $sablona = $hodnoceni->getSablona();
        self::assertNotNull($sablona);
        self::assertSame([1, 2, 3, 4, 5], $sablona->skala);
        self::assertSame('Hraní rolí', $sablona->kategorie[0]->nazev);
        self::assertSame(['Postavy', 'Spolupráce'], array_column($sablona->kategorie[0]->kriteria, 'nazev'));
        self::assertSame('Nápaditost', $sablona->kategorie[1]->kriteria[0]->nazev);
        self::assertNotNull($sablona->kategorie[1]->kriteria[0]->id);
        self::assertSame(self::SABLONA, $sablona->getText());
        self::assertSame(self::SABLONA, $this->getZdroj(), 'Textový zápis zůstane pro dohledání');
        self::assertFalse($sablona->isUzavrena());
        // šablona patří ročníku
        self::assertNull($this->vytvorHodnoceni(rok: self::ROK + 1)->getSablona());
    }

    public function testIdZJineSablonyNeprojde(): void
    {
        $hodnoceni = $this->vytvorHodnoceni();
        $sCizimId = $this->sablona([1, 2], [['Hraní', 999999, [['Postavy', null]]]]);

        // formulář otevřený před tím, než jiný org kritéria změnil nebo smazal
        $this->assertZakaz(fn () => $hodnoceni->ulozSablonu($sCizimId, $this->org), 'Kritéria mezitím uložil někdo jiný');
        self::assertNull($hodnoceni->getSablona());

        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $ulozena = $hodnoceni->getSablona();
        $idPostav = $ulozena->kategorie[0]->kriteria[0]->id;
        $dvakrat = $this->sablona([1, 2, 3, 4, 5], [
            [$ulozena->kategorie[0]->nazev, $ulozena->kategorie[0]->id, [['Postavy', $idPostav], ['Postavy znovu', $idPostav]]],
        ]);
        $this->assertZakaz(fn () => $hodnoceni->ulozSablonu($dvakrat, $this->org), 'Kritéria mezitím uložil někdo jiný');
        self::assertSame(self::SABLONA, $hodnoceni->getSablona()->getText());
    }

    public function testVypravecVidiJenKolaTurnajeDnDKtereVede(): void
    {
        $hodnoceni = $this->vytvorHodnoceni();

        $kolaA = $hodnoceni->getKola($this->vypravecA);
        self::assertSame(
            [$this->prvniKolo, $this->druheKolo, $this->tretiKolo],
            array_column($kolaA, 'idAkce'),
            'Kola podle začátku, bez Legend a bez loňska',
        );
        self::assertSame([1, 2, 3], array_column($kolaA, 'kolo'));
        self::assertSame('D&D turnaj 1. běh', $kolaA[0]['nazevTurnaje']);
        self::assertSame(
            [[
                'id'    => $this->paraBola,
                'nazev' => 'Para Bola',
            ], [
                'id'    => $this->bezNazvu,
                'nazev' => 'Tým č. ' . $this->bezNazvu,
            ]],
            $kolaA[0]['tymy'],
        );
        self::assertSame([$this->druhyBehPrvniKolo], array_column($hodnoceni->getKola($this->vypravecDruhehoBehu), 'idAkce'));
        self::assertSame([], $hodnoceni->getKola($this->cizi));
        self::assertSame(
            [$this->prvniKolo, $this->druheKolo, $this->druhyBehPrvniKolo, $this->tretiKolo],
            array_column($hodnoceni->getKola(null), 'idAkce'),
            'Org turnaje vidí všechna kola',
        );
    }

    public function testVypravecUloziAUpraviSveHodnoceni(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();

        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        $ulozene = $hodnoceni->getHodnoceni($this->paraBola, 1);
        self::assertNotNull($ulozene);
        self::assertSame($this->vypravecA, $ulozene['idHodnotitele']);
        self::assertSame($this->prvniKolo, $ulozene['idAkce']);
        self::assertSame([5, 4, 3], array_values($ulozene['hodnoty']));
        self::assertNull($hodnoceni->getHodnoceni($this->paraBola, 2));

        // z formuláře chodí řetězce
        $hodnoceni->ulozHodnoceni(
            $this->prvniKolo,
            $this->paraBola,
            array_map('strval', $this->getHodnoty($hodnoceni, [1, 2, 2])),
            $this->vypravecA,
            false,
        );
        self::assertSame([1, 2, 2], array_values($hodnoceni->getHodnoceni($this->paraBola, 1)['hodnoty']));
        self::assertSame(1, $hodnoceni->getPocetHodnoceni());
    }

    public function testCiziHodnoceniPrepiseJenOrgTurnaje(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);

        self::assertStringContainsString(
            'Skupinu „Para Bola“ v 1. kole už hodnotil(a) Test',
            (string) $hodnoceni->getDuvodZakazu($this->prvniKolo, $this->paraBola, $this->vypravecB, false),
        );
        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni(
                $this->prvniKolo,
                $this->paraBola,
                $this->getHodnoty($hodnoceni, [1, 1, 1]),
                $this->vypravecB,
                false,
            ),
            'už hodnotil',
        );
        self::assertNull($hodnoceni->getDuvodZakazu($this->prvniKolo, $this->paraBola, $this->org, true));

        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [2, 2, 2]), $this->org, true);
        $prepsane = $hodnoceni->getHodnoceni($this->paraBola, 1);
        self::assertSame([2, 2, 2], array_values($prepsane['hodnoty']));
        self::assertSame($this->vypravecA, $prepsane['idHodnotitele'], 'Hodnocení zůstane vypravěči, který ho zadal');
        self::assertSame($this->org, $prepsane['idZadal']);
    }

    public function testHodnotitJdeJenSkupinuZKolaKtereVypravecVede(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoty = $this->getHodnoty($hodnoceni, [3, 3, 3]);

        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $hodnoty, $this->cizi, false),
            'Tohle kolo nevedeš',
        );
        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $hodnoty, $this->vypravecDruhehoBehu, false),
            'Tohle kolo nevedeš',
        );
        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->ovecky, $hodnoty, $this->vypravecA, false),
            'Tahle skupina v tomhle kole nehrála.',
        );
        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->koloLegend, $this->paraBola, $hodnoty, $this->vypravecA, true),
            'Tohle není kolo turnaje',
        );
        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->loniKolo, $this->paraBola, $hodnoty, $this->vypravecA, true),
            'Tohle není kolo turnaje',
        );
        self::assertSame(0, $hodnoceni->getPocetHodnoceni());
    }

    public function testKoloPredZacatkemHodnotiJenOrg(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoty = $this->getHodnoty($hodnoceni, [3, 3, 3]);

        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->tretiKolo, $this->paraBola, $hodnoty, $this->vypravecA, false),
            'Kolo ještě nezačalo, hodnotit jde od 25. 7. 9:00.',
        );
        $hodnoceni->ulozHodnoceni($this->tretiKolo, $this->paraBola, $hodnoty, $this->org, true, $this->vypravecA);
        self::assertNotNull($hodnoceni->getHodnoceni($this->paraBola, 3));

        // od začátku kola už může i vypravěč
        $vZacatkuKola = $this->vytvorHodnoceni('2030-07-25 09:00:00');
        $vZacatkuKola->ulozHodnoceni($this->tretiKolo, $this->bezNazvu, $hodnoty, $this->vypravecA, false);
        self::assertNotNull($vZacatkuKola->getHodnoceni($this->bezNazvu, 3));
    }

    public function testKazdeKriteriumPotrebujeHodnotuZeSkaly(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $mimoSkalu = $this->getHodnoty($hodnoceni, [5, 6, 1]);
        $chybejici = $this->getHodnoty($hodnoceni, [5, 4, 3]);
        array_pop($chybejici);

        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $mimoSkalu, $this->vypravecA, false),
            'Chybí u: Hraní rolí: Spolupráce.',
        );
        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $chybejici, $this->vypravecA, false),
            'Chybí u: Řešení: Nápaditost.',
        );
        self::assertNull($hodnoceni->getHodnoceni($this->paraBola, 1));
    }

    public function testBezSablonyNejdeHodnotit(): void
    {
        $hodnoceni = $this->vytvorHodnoceni();

        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, [], $this->org, true),
            'Kritéria hodnocení ještě nejsou zadaná',
        );
    }

    public function testUzavreneHodnoceniMeniJenOrg(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoty = $this->getHodnoty($hodnoceni, [3, 3, 3]);

        $hodnoceni->setUzavreno(true);
        self::assertTrue($hodnoceni->getSablona()->isUzavrena());
        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $hodnoty, $this->vypravecA, false),
            'Hodnocení je uzavřené.',
        );
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->bezNazvu, $hodnoty, $this->org, true, $this->vypravecB);

        $hodnoceni->setUzavreno(false);
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $hodnoty, $this->vypravecA, false);
        self::assertSame(2, $hodnoceni->getPocetHodnoceni());
    }

    public function testPrejmenovaniAPreradeniPodleIdNechaHodnoceni(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        [$hrani, $reseni] = $hodnoceni->getSablona()->kategorie;
        [$postavy, $spoluprace] = $hrani->kriteria;
        $napaditost = $reseni->kriteria[0];

        // kategorie i kritéria prohozené a přejmenované, škála pozpátku
        $zmenena = $this->sablona([5, 4, 3, 2, 1], [
            ['Řešení zápletky', $reseni->id, [['Nápaditost', $napaditost->id]]],
            ['Hraní rolí', $hrani->id, [['Spolupráce', $spoluprace->id], ['Postavy a příběh', $postavy->id]]],
        ]);
        self::assertSame(0, $hodnoceni->ulozSablonu($zmenena, $this->org), 'Přejmenování ani přeřazení nic nemaže');

        $sablona = $hodnoceni->getSablona();
        self::assertSame(['Řešení zápletky', 'Hraní rolí'], array_column($sablona->kategorie, 'nazev'));
        self::assertSame(['Spolupráce', 'Postavy a příběh'], array_column($sablona->kategorie[1]->kriteria, 'nazev'));
        self::assertSame([5, 4, 3, 2, 1], $sablona->skala);
        self::assertSame($reseni->id, $sablona->kategorie[0]->id);
        self::assertSame($this->getHodnotyPodleNazvu($hodnoceni, $sablona), [
            'Nápaditost'       => 3,
            'Spolupráce'       => 4,
            'Postavy a příběh' => 5,
        ]);
        self::assertSame($sablona->getText(), $this->getZdroj());
    }

    public function testKriteriumJdePresunoutDoJineKategorie(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        [$hrani, $reseni] = $hodnoceni->getSablona()->kategorie;

        $presunuta = $this->sablona([1, 2, 3, 4, 5], [
            ['Hraní rolí', $hrani->id, [['Postavy', $hrani->kriteria[0]->id]]],
            ['Řešení', $reseni->id, [['Spolupráce', $hrani->kriteria[1]->id], ['Nápaditost', $reseni->kriteria[0]->id]]],
        ]);
        self::assertSame(0, $hodnoceni->ulozSablonu($presunuta, $this->org));

        $vysledek = $this->najdiVysledek($hodnoceni, $this->paraBola);
        self::assertSame([5, 7], array_values($vysledek['souctyKategorii']), 'Spolupráce se sčítá do Řešení');
    }

    public function testOdebraniKriteriaChcePotvrzeniAHodnoceniNecha(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        [$hrani, $reseni] = $hodnoceni->getSablona()->kategorie;
        $bezSpoluprace = $this->sablona([1, 2, 3, 4, 5], [
            ['Hraní rolí', $hrani->id, [['Postavy', $hrani->kriteria[0]->id]]],
            ['Řešení', $reseni->id, [['Nápaditost', $reseni->kriteria[0]->id]]],
        ]);

        $zmena = $this->getZmenaChticiPotvrzeni(fn () => $hodnoceni->ulozSablonu($bezSpoluprace, $this->org));
        self::assertFalse($zmena->isSmazaniVsech());
        self::assertSame(
            ['Odebíráš kritérium „Spolupráce“ (Hraní rolí), jeho hodnoty se z hodnocení smažou, ostatní hodnoty zůstanou.'],
            $zmena->getPodrobnosti(),
        );
        self::assertSame(self::SABLONA, $hodnoceni->getSablona()->getText(), 'Bez potvrzení se nic nezmění');

        self::assertSame(0, $hodnoceni->ulozSablonu($bezSpoluprace, $this->org, $zmena->getKlicPotvrzeni()));
        self::assertSame(1, $hodnoceni->getPocetHodnoceni());
        self::assertSame([
            'Postavy'    => 5,
            'Nápaditost' => 3,
        ], $this->getHodnotyPodleNazvu($hodnoceni, $hodnoceni->getSablona()));
        self::assertSame(8, $this->najdiVysledek($hodnoceni, $this->paraBola)['celkem']);
    }

    public function testNoveKriteriumSmazeVsechnaHodnoceniJenSPotvrzenim(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        $sRychlosti = $this->sRychlosti($hodnoceni->getSablona());

        $zmena = $this->getZmenaChticiPotvrzeni(fn () => $hodnoceni->ulozSablonu($sRychlosti, $this->org));
        self::assertTrue($zmena->isSmazaniVsech());
        self::assertSame(1, $zmena->getPocetHodnoceni());
        self::assertSame(
            ['Přibude kritérium „Rychlost“ (Řešení). Hodnocení k němu nemají hodnotu, proto se smažou všechna.'],
            $zmena->getPodrobnosti(),
        );
        self::assertSame(1, $hodnoceni->getPocetHodnoceni());

        self::assertSame(1, $hodnoceni->ulozSablonu($sRychlosti, $this->org, $zmena->getKlicPotvrzeni()));
        self::assertSame(0, $hodnoceni->getPocetHodnoceni());
        self::assertSame(['Nápaditost', 'Rychlost'], array_column($hodnoceni->getSablona()->kategorie[1]->kriteria, 'nazev'));
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3, 2]), $this->vypravecA, false);
        self::assertSame(1, $hodnoceni->getPocetHodnoceni());
    }

    public function testNoveKriteriumBezHodnoceniPotvrzeniNepotrebuje(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();

        self::assertSame(0, $hodnoceni->ulozSablonu($this->sRychlosti($hodnoceni->getSablona()), $this->org));
        self::assertCount(4, $hodnoceni->getSablona()->getKriteriaPodleId());
    }

    public function testSkalaBezPouziteHodnotySmazeHodnoceni(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        $ulozena = $hodnoceni->getSablona();

        // škála, ve které použité hodnoty zůstanou, hodnocení nechá
        self::assertSame(0, $hodnoceni->ulozSablonu(new SablonaHodnoceni(null, [0, 1, 2, 3, 4, 5, 6], $ulozena->kategorie), $this->org));
        self::assertSame(1, $hodnoceni->getPocetHodnoceni());

        $zmena = $this->getZmenaChticiPotvrzeni(
            fn () => $hodnoceni->ulozSablonu(new SablonaHodnoceni(null, [1, 2, 3], $ulozena->kategorie), $this->org),
        );
        self::assertTrue($zmena->isSmazaniVsech());
        self::assertSame(
            ['V nové škále chybí použité hodnoty 4, 5, proto se smažou všechna hodnocení.'],
            $zmena->getPodrobnosti(),
        );
    }

    public function testPotvrzeniPlatiJenProStejneMazani(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        [$hrani, $reseni] = $hodnoceni->getSablona()->kategorie;
        $bezSpoluprace = $this->sablona([1, 2, 3, 4, 5], [
            ['Hraní rolí', $hrani->id, [['Postavy', $hrani->kriteria[0]->id]]],
            ['Řešení', $reseni->id, [['Nápaditost', $reseni->kriteria[0]->id]]],
        ]);
        $klic = $this->getZmenaChticiPotvrzeni(fn () => $hodnoceni->ulozSablonu($bezSpoluprace, $this->org))->getKlicPotvrzeni();

        // potvrzení odebrání Spolupráce neplatí na odebrání celé kategorie Řešení
        $bezReseni = $this->sablona([1, 2, 3, 4, 5], [['Hraní rolí', $hrani->id, [['Postavy', $hrani->kriteria[0]->id]]]]);
        $this->getZmenaChticiPotvrzeni(fn () => $hodnoceni->ulozSablonu($bezReseni, $this->org, $klic));

        // přejmenování k tomu potvrzení nezruší
        $bezSpolupracePrejmenovana = $this->sablona([1, 2, 3, 4, 5], [
            ['Hraní', $hrani->id, [['Postavy', $hrani->kriteria[0]->id]]],
            ['Řešení', $reseni->id, [['Nápaditost', $reseni->kriteria[0]->id]]],
        ]);
        self::assertSame(0, $hodnoceni->ulozSablonu($bezSpolupracePrejmenovana, $this->org, $klic));
        self::assertSame('Hraní', $hodnoceni->getSablona()->kategorie[0]->nazev);
    }

    public function testSouborSeParujePodleNazvu(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $ulozena = $hodnoceni->getSablona();

        $sparovana = $hodnoceni->sparujPodleNazvu(ParserSablonyHodnoceni::rozeber(<<<'TEXT'
            Škála: 1 2 3
            # ŘEŠENÍ
            ## nápaditost
            ## Rychlost
            # Hraní rolí
            ## Postavy a příběh
            ## Spolupráce
            TEXT));

        self::assertSame([1, 2, 3], $sparovana->skala);
        self::assertSame([$ulozena->kategorie[1]->id, $ulozena->kategorie[0]->id], array_column($sparovana->kategorie, 'id'));
        self::assertSame(['ŘEŠENÍ', 'Hraní rolí'], array_column($sparovana->kategorie, 'nazev'), 'Názvy jsou ze souboru');
        self::assertSame(
            [$ulozena->kategorie[1]->kriteria[0]->id, null],
            array_column($sparovana->kategorie[0]->kriteria, 'id'),
        );
        self::assertSame(
            [null, $ulozena->kategorie[0]->kriteria[1]->id],
            array_column($sparovana->kategorie[1]->kriteria, 'id'),
            'Přejmenované v souboru je nové kritérium',
        );
        $bezUlozenych = $this->vytvorHodnoceni(rok: self::ROK + 1)->sparujPodleNazvu($sparovana);
        self::assertSame([null, null], array_column($bezUlozenych->kategorie, 'id'), 'Bez uložených kritérií je všechno nové');
        self::assertSame([], $bezUlozenych->getKriteriaPodleId());
    }

    public function testSouborPresunuteKriteriumNajdeVJineKategorii(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        $idSpoluprace = $hodnoceni->getSablona()->kategorie[0]->kriteria[1]->id;

        $sparovana = $hodnoceni->sparujPodleNazvu(ParserSablonyHodnoceni::rozeber(<<<'TEXT'
            Škála: 1 2 3 4 5
            # Hraní rolí
            ## Postavy
            # Řešení
            ## Nápaditost
            ## Spolupráce
            TEXT));

        self::assertSame($idSpoluprace, $sparovana->kategorie[1]->kriteria[1]->id);
        self::assertSame(0, $hodnoceni->ulozSablonu($sparovana, $this->org), 'Přesun nic nemaže');
        self::assertSame([5, 7], array_values($this->najdiVysledek($hodnoceni, $this->paraBola)['souctyKategorii']));
    }

    public function testSouborNejednoznacneKriteriumJindeNeparuje(): void
    {
        $hodnoceni = $this->vytvorHodnoceni();
        $hodnoceni->ulozSablonu(ParserSablonyHodnoceni::rozeber("Škála: 1 2\n# A\n## Kritérium 1\n# B\n## Kritérium 1"), $this->org);
        $idKriteriaB = $hodnoceni->getSablona()->kategorie[1]->kriteria[0]->id;

        // A i B zmizely, „Kritérium 1“ je mezi uloženými dvakrát: nejde poznat, odkud přišlo
        $nejednoznacne = $hodnoceni->sparujPodleNazvu(ParserSablonyHodnoceni::rozeber("Škála: 1 2\n# C\n## Kritérium 1"));
        self::assertNull($nejednoznacne->kategorie[0]->kriteria[0]->id);

        // A zůstala a své kritérium si vzala, zbylé je jen to z B: B se v souboru přejmenovala na C
        $prejmenovanaKategorie = $hodnoceni->sparujPodleNazvu(ParserSablonyHodnoceni::rozeber(<<<'TEXT'
            Škála: 1 2
            # A
            ## Kritérium 1
            # C
            ## Kritérium 1
            TEXT));
        self::assertNotNull($prejmenovanaKategorie->kategorie[0]->kriteria[0]->id);
        self::assertSame($idKriteriaB, $prejmenovanaKategorie->kategorie[1]->kriteria[0]->id);
    }

    public function testVysledkySectouKategorieAShoduCelkuRozhodneNejlepsiKategorie(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        // Para Bola: 1. kolo 5+4 a 3, 2. kolo 1+1 a 1 → hraní 11, řešení 4, celkem 15, 2 ze 3 kol
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        $hodnoceni->ulozHodnoceni($this->druheKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [1, 1, 1]), $this->vypravecB, false);
        // Ovečky: jediné kolo druhého běhu 5+5 a 5 → hraní 10, řešení 5, celkem 15, úplné
        $hodnoceni->ulozHodnoceni(
            $this->druhyBehPrvniKolo,
            $this->ovecky,
            $this->getHodnoty($hodnoceni, [5, 5, 5]),
            $this->vypravecDruhehoBehu,
            false,
        );
        // tým bez názvu bez hodnocení

        $vysledky = $hodnoceni->getVysledky();
        $sablona = $hodnoceni->getSablona();
        $idHrani = $sablona->kategorie[0]->id;
        $idReseni = $sablona->kategorie[1]->id;

        // celkem stejně, Para Bola má lepší nejlepší kategorii (11 proti 10), přednost nemá úplnost ani běh
        self::assertSame(['Para Bola', 'Ovečky', 'Tým č. ' . $this->bezNazvu], array_column($vysledky, 'nazevTymu'));
        self::assertSame([15, 15, 0], array_column($vysledky, 'celkem'));
        self::assertSame([1, 2, 3], array_column($vysledky, 'poradiOd'));
        self::assertSame([1, 2, 3], array_column($vysledky, 'poradiDo'));
        self::assertSame([
            $idHrani  => 11,
            $idReseni => 4,
        ], $vysledky[0]['souctyKategorii']);
        self::assertSame([
            $idHrani  => 0,
            $idReseni => 0,
        ], $vysledky[2]['souctyKategorii']);
        self::assertSame([2, 1, 0], array_column($vysledky, 'hodnocenoKol'));
        self::assertSame([3, 1, 3], array_column($vysledky, 'kol'));
        self::assertSame([false, true, false], array_column($vysledky, 'uplna'));
        self::assertSame(['D&D turnaj 1. běh', 'D&D turnaj 2. běh', 'D&D turnaj 1. běh'], array_column($vysledky, 'nazevTurnaje'));

        self::assertSame('1.–2.', HodnoceniTurnaje::getPopisPoradi(1, 2));
        self::assertSame('3.', HodnoceniTurnaje::getPopisPoradi(3, 3));
    }

    public function testShoduCelkuINejlepsiKategorieRozhodneNejslabsiKategorieJinakDeliMisto(): void
    {
        $hodnoceni = $this->vytvorHodnoceni();
        $hodnoceni->ulozSablonu(ParserSablonyHodnoceni::rozeber(<<<'TEXT'
            Škála: 1 2 3 4 5
            # Hraní rolí
            ## Postavy
            # Řešení
            ## Nápaditost
            # Boj
            ## Taktika
            TEXT), $this->org);
        // všechny celkem 9 a nejlepší kategorie 5; nejslabší: bez názvu 2, Para Bola a Ovečky 1
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 1, 3]), $this->vypravecA, false);
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->bezNazvu, $this->getHodnoty($hodnoceni, [2, 5, 2]), $this->vypravecB, false);
        $hodnoceni->ulozHodnoceni(
            $this->druhyBehPrvniKolo,
            $this->ovecky,
            $this->getHodnoty($hodnoceni, [1, 3, 5]),
            $this->vypravecDruhehoBehu,
            false,
        );

        $vysledky = $hodnoceni->getVysledky();

        // shodné ve všem jsou mezi sebou podle názvu, to pořadí neurčuje
        self::assertSame(['Tým č. ' . $this->bezNazvu, 'Ovečky', 'Para Bola'], array_column($vysledky, 'nazevTymu'));
        self::assertSame([9, 9, 9], array_column($vysledky, 'celkem'));
        self::assertSame([1, 2, 2], array_column($vysledky, 'poradiOd'));
        self::assertSame([1, 3, 3], array_column($vysledky, 'poradiDo'));
    }

    public function testExportVysledkuAVsechHodnoceni(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);

        $vysledky = $hodnoceni->getExportVysledku();
        self::assertSame(
            ['Pořadí', 'Skupina', 'Běh', 'Hraní rolí', 'Řešení', 'Celkem', 'Ohodnocených kol', 'Kol', 'Úplná'],
            $vysledky['hlavicky'],
        );
        self::assertSame(['1.', 'Para Bola', 'D&D turnaj 1. běh', 9, 3, 12, 1, 3, 'ne'], $vysledky['radky'][0]);
        self::assertCount(3, $vysledky['radky']);

        $vsechna = $hodnoceni->getExportHodnoceni();
        self::assertSame(
            [
                'Běh',
                'Skupina',
                'Kolo',
                'Hodnotil',
                'Zadal',
                'Upraveno',
                'Hraní rolí: Postavy',
                'Hraní rolí: Spolupráce',
                'Řešení: Nápaditost',
                'Celkem',
                'Poznámka',
            ],
            $vsechna['hlavicky'],
        );
        self::assertSame(
            [
                'D&D turnaj 1. běh',
                'Para Bola',
                1,
                'Test „test_hodnoceni_a“ Hodnotitel',
                'Test „test_hodnoceni_a“ Hodnotitel',
                '2030-07-20 12:00',
                5,
                4,
                3,
                12,
                '',
            ],
            $vsechna['radky'][0],
        );
    }

    public function testOrgSmazeHodnoceni(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        $idHodnoceni = $hodnoceni->getHodnoceni($this->paraBola, 1)['id'];

        $hodnoceni->smazHodnoceni($idHodnoceni);

        self::assertNull($hodnoceni->getHodnoceni($this->paraBola, 1));
        self::assertSame([], $hodnoceni->getVsechnaHodnoceni());
        $this->assertZakaz(fn () => $hodnoceni->smazHodnoceni($idHodnoceni), 'Tohle hodnocení už neexistuje.');
        // po smazání může skupinu ohodnotit jiný vypravěč kola
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [1, 1, 1]), $this->vypravecB, false);
        self::assertSame($this->vypravecB, $hodnoceni->getHodnoceni($this->paraBola, 1)['idHodnotitele']);
    }

    public function testHodnoceniKolaKtereSkupinaNehrajeSeDoVysledkuNepocita(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        $hodnoceni->ulozHodnoceni($this->druheKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [1, 1, 1]), $this->vypravecB, false);
        // skupina 2. kolo nakonec hrát nebude
        dbQuery(<<<'SQL'
            DELETE FROM akce_tym_akce WHERE id_tymu = $0 AND id_akce = $1
            SQL, [
            0 => $this->paraBola,
            1 => $this->druheKolo,
        ]);

        $paraBola = $this->najdiVysledek($hodnoceni, $this->paraBola);
        self::assertSame(12, $paraBola['celkem'], 'Hodnocení 2. kola se nepočítá');
        self::assertSame(1, $paraBola['hodnocenoKol']);
        self::assertSame(2, $paraBola['kol']);
        self::assertFalse($paraBola['uplna'], 'Chybí hodnocení 3. kola, které skupina hraje');

        $vsechna = $hodnoceni->getVsechnaHodnoceni();
        self::assertSame([true, false], array_column($vsechna, 'hrajeKolo'));
        self::assertNull($vsechna[1]['idAkce']);
        $export = $hodnoceni->getExportHodnoceni();
        self::assertSame(
            ['', 'skupina už v kole není, do výsledků se nepočítá'],
            array_map(static fn (array $radek): int|string => $radek[array_key_last($radek)], $export['radky']),
        );

        $html = $this->vykresliStranku($this->org, true, []);
        self::assertStringContainsString('skupina už v kole není', $html);
        self::assertStringContainsString('<td title="Chybí hodnocení některého kola">1/2 ⚠️</td>', $html);
    }

    public function testSmazanaAktivitaHodnoceniNesmaze(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);

        dbQuery(<<<'SQL'
            DELETE FROM akce_seznam WHERE id_akce = $0
            SQL, [
            0 => $this->prvniKolo,
        ]);

        $ulozene = $hodnoceni->getHodnoceni($this->paraBola, 1);
        self::assertNotNull($ulozene, 'Hodnocení patří ke kolu, ne k aktivitě');
        self::assertNull($ulozene['idAkce']);
        self::assertSame([5, 4, 3], array_values($ulozene['hodnoty']));
        $vsechna = $hodnoceni->getVsechnaHodnoceni();
        self::assertSame('D&D turnaj 1. běh', $vsechna[0]['nazevTurnaje'], 'Běh podle kol, která skupina hraje');
        self::assertFalse($vsechna[0]['hrajeKolo']);
        self::assertStringContainsString('skupina už v kole není', $this->vykresliStranku($this->org, true, []));
    }

    public function testOrgZadaHodnoceniZaVypraveceKola(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoty = $this->getHodnoty($hodnoceni, [5, 4, 3]);

        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $hodnoty, $this->org, true),
            'Vyber vypravěče kola',
        );
        $this->assertZakaz(
            fn () => $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $hodnoty, $this->org, true, $this->vypravecDruhehoBehu),
            'Vyber vypravěče kola',
        );
        self::assertSame([$this->vypravecA, $this->vypravecB], array_column($hodnoceni->getVypraveciKola($this->prvniKolo), 'id'));

        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $hodnoty, $this->org, true, $this->vypravecA);
        $zadane = $hodnoceni->getHodnoceni($this->paraBola, 1);
        self::assertSame($this->vypravecA, $zadane['idHodnotitele']);
        self::assertSame($this->org, $zadane['idZadal']);
        self::assertSame(
            'Test „test_hodnoceni_a“ Hodnotitel (zadal(a) org Test „test_hodnoceni_org“ Hodnotitel)',
            HodnoceniTurnaje::getPopisHodnotitele($zadane['idHodnotitele'], $zadane['hodnotitel'], $zadane['idZadal'], $zadane['zadal']),
        );

        // vypravěč, za kterého org hodnotil, ho upraví jako své; druhý vypravěč kola ne
        self::assertNull($hodnoceni->getDuvodZakazu($this->prvniKolo, $this->paraBola, $this->vypravecA, false));
        self::assertNotNull($hodnoceni->getDuvodZakazu($this->prvniKolo, $this->paraBola, $this->vypravecB, false));
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [1, 2, 3]), $this->vypravecA, false);
        $upravene = $hodnoceni->getHodnoceni($this->paraBola, 1);
        self::assertSame($this->vypravecA, $upravene['idZadal']);
        self::assertSame('Test „test_hodnoceni_a“ Hodnotitel', HodnoceniTurnaje::getPopisHodnotitele(
            $upravene['idHodnotitele'],
            $upravene['hodnotitel'],
            $upravene['idZadal'],
            $upravene['zadal'],
        ));

        // org upravuje stávající: hodnotitel zůstane, výběr vypravěče se nebere v potaz
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [2, 2, 2]), $this->org, true, $this->vypravecB);
        $poOrgovi = $hodnoceni->getHodnoceni($this->paraBola, 1);
        self::assertSame($this->vypravecA, $poOrgovi['idHodnotitele']);
        self::assertSame($this->org, $poOrgovi['idZadal']);
    }

    public function testStrankaOrgVybiraZaKohoHodnoti(): void
    {
        $this->vytvorHodnoceniSeSablonou();

        $html = $this->vykresliStranku($this->org, true, [
            'aktivita' => $this->prvniKolo,
            'tym'      => $this->paraBola,
        ]);

        self::assertStringContainsString('name="za_vypravece"', $html);
        self::assertStringContainsString('<option value="' . $this->vypravecA . '" >Test „test_hodnoceni_a“ Hodnotitel</option>', $html);
    }

    public function testStrankaCiziVypravecNevidiHodnotyHodnoceni(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        $get = [
            'aktivita' => $this->prvniKolo,
            'tym'      => $this->paraBola,
        ];

        $html = $this->vykresliStranku($this->vypravecB, false, $get);

        self::assertStringContainsString('Hodnotil(a) Test „test_hodnoceni_a“ Hodnotitel', $html);
        self::assertSame(0, substr_count($html, 'name="hodnoty['), 'Žádné hodnoty cizího hodnocení');
        self::assertDoesNotMatchRegularExpression('~<option value="\d+" selected>\d+</option>~', $html);

        $autor = $this->vykresliStranku($this->vypravecA, false, $get);
        self::assertStringContainsString('<option value="5" selected>5</option>', $autor);
        $org = $this->vykresliStranku($this->org, true, $get);
        self::assertStringContainsString('<option value="5" selected>5</option>', $org);
    }

    public function testStrankaVypravecVidiFormularSvehoKola(): void
    {
        $this->vytvorHodnoceniSeSablonou();

        $html = $this->vykresliStranku($this->vypravecA, false, [
            'aktivita' => $this->prvniKolo,
            'tym'      => $this->paraBola,
        ]);

        self::assertStringContainsString('<option value="' . $this->prvniKolo . '" selected>1. kolo · D&amp;D turnaj 1. běh', $html);
        self::assertStringContainsString('<option value="' . $this->paraBola . '" selected>Para Bola</option>', $html);
        self::assertStringNotContainsString('D&amp;D turnaj 2. běh', $html, 'Cizí kolo v nabídce není');
        self::assertStringContainsString('<legend>Hraní rolí</legend>', $html);
        self::assertSame(3, substr_count($html, 'name="hodnoty['));
        self::assertStringContainsString('<summary>📖 Jak hodnotit</summary>', $html);
        self::assertStringContainsString('vyber hodnotu ze škály 1 2 3 4 5. Vyšší je lepší', $html);
        self::assertStringNotContainsString('Jak na hodnocení turnaje', $html, 'Návod orga vypravěč nevidí');
        // stránka běží s opravdovými hodinami, kolo ročníku 2030 ještě nezačalo
        self::assertStringContainsString('Kolo ještě nezačalo', $html);
        self::assertStringNotContainsString('Uložit hodnocení', $html);
        self::assertStringNotContainsString('<h3>Výsledky</h3>', $html);
    }

    public function testStrankaOrgVidiVysledkyAKriteria(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);

        $html = $this->vykresliStranku($this->org, true, [
            'aktivita' => $this->tretiKolo,
            'tym'      => $this->paraBola,
        ]);

        self::assertStringContainsString('<h3>Výsledky</h3>', $html);
        self::assertStringContainsString('<td>Para Bola</td>', $html);
        self::assertStringContainsString('Stáhnout výsledky (XLSX)', $html);
        self::assertStringContainsString('Uložit hodnocení', $html, 'Org hodnotí i kolo, které ještě nezačalo');
        // kritéria jsou v editoru s id, aby uložení poznalo přejmenování
        $sablona = $hodnoceni->getSablona();
        $idHrani = $sablona->kategorie[0]->id;
        $idPostav = $sablona->kategorie[0]->kriteria[0]->id;
        self::assertStringContainsString('name="skala" value="1 2 3 4 5"', $html);
        self::assertStringContainsString("name=\"kategorie[i{$idHrani}][id]\" value=\"{$idHrani}\"", $html);
        self::assertStringContainsString("name=\"kategorie[i{$idHrani}][nazev]\" value=\"Hraní rolí\"", $html);
        self::assertStringContainsString(
            "name=\"kategorie[i{$idHrani}][kriteria][i{$idPostav}][nazev]\" value=\"Postavy\"",
            $html,
        );
        // štítek „nové“ mají jen vzory řádků pro tlačítka Přidat, uložená kritéria ne
        self::assertSame(2, substr_count($html, 'class="hodnoceni-editor-nove"'));
        self::assertStringContainsString('Stáhnout kritéria (XLSX)', $html);
        // s uloženými kritérii jsou návody zavřené
        self::assertMatchesRegularExpression('~<details class="hodnoceni-navod"\s*>\s*<summary>📖 Jak na hodnocení turnaje~u', $html);
        self::assertStringContainsString('<summary>📖 Jak kritéria zadat</summary>', $html);
        self::assertStringNotContainsString('Jak hodnotit</summary>', $html, 'Org má svůj návod, ne vypravěčský');
    }

    public function testStrankaOrgBezKriteriiMaOtevrenyNavod(): void
    {
        $html = $this->vykresliStranku($this->org, true, []);

        self::assertMatchesRegularExpression('~<details class="hodnoceni-navod" open>\s*<summary>📖 Jak na hodnocení turnaje~u', $html);
        self::assertMatchesRegularExpression('~<details class="hodnoceni-navod" open>\s*<summary>📖 Jak kritéria zadat~u', $html);
        // prázdný editor s výchozí škálou a vzor ke stažení
        self::assertStringContainsString('name="skala" value="1 2 3 4 5"', $html);
        self::assertStringContainsString('name="kategorie[n1][kriteria][n1][nazev]" value=""', $html);
        self::assertStringContainsString('Stáhnout vzor (XLSX)', $html);
        self::assertStringNotContainsString('data-neulozeno', $html);
    }

    public function testEditorPoOdeslaniChceticimPotvrzeniUkazeCoSeSmazeAOdeslanaKriteria(): void
    {
        $hodnoceni = $this->vytvorHodnoceniSeSablonou();
        $hodnoceni->ulozHodnoceni($this->prvniKolo, $this->paraBola, $this->getHodnoty($hodnoceni, [5, 4, 3]), $this->vypravecA, false);
        [$hrani, $reseni] = $hodnoceni->getSablona()->kategorie;
        $idPostav = $hrani->kriteria[0]->id;
        $idNapaditosti = $reseni->kriteria[0]->id;

        // Spolupráce odebraná, Hraní přejmenované, k Řešení nové kritérium z editoru
        $html = $this->vykresliStranku($this->org, true, [], [
            'akce'      => 'ulozit-kriteria',
            'skala'     => '1 2 3 4 5',
            'kategorie' => [
                "i{$hrani->id}" => [
                    'id'       => (string) $hrani->id,
                    'nazev'    => 'Hraní',
                    'kriteria' => [
                        "i{$idPostav}" => [
                            'id'    => (string) $idPostav,
                            'nazev' => 'Postavy',
                        ],
                    ],
                ],
                "i{$reseni->id}" => [
                    'id'       => (string) $reseni->id,
                    'nazev'    => 'Řešení',
                    'kriteria' => [
                        "i{$idNapaditosti}" => [
                            'id'    => (string) $idNapaditosti,
                            'nazev' => 'Nápaditost',
                        ],
                        'j1' => [
                            'id'    => '',
                            'nazev' => 'Rychlost',
                        ],
                    ],
                ],
            ],
        ]);

        self::assertStringContainsString('Přibude kritérium „Rychlost“ (Řešení).', $html);
        self::assertStringContainsString('Odebíráš kritérium „Spolupráce“ (Hraní rolí), jeho hodnoty zmizí se všemi hodnoceními.', $html);
        self::assertStringContainsString('Ano, uložit a smazat všech 1 hodnocení', $html);
        self::assertMatchesRegularExpression('~name="potvrzeni" value="[0-9a-f]{64}"~', $html);
        // editor drží odeslaná kritéria, ne uložená, a hlídá odchod
        self::assertStringContainsString("name=\"kategorie[i{$hrani->id}][nazev]\" value=\"Hraní\"", $html);
        self::assertStringContainsString('name="kategorie[i' . $reseni->id . '][kriteria][j1][nazev]" value="Rychlost"', $html);
        self::assertStringNotContainsString('value="Spolupráce"', $html);
        self::assertStringContainsString('data-editor-kriterii data-neulozeno', $html);
        self::assertSame(1, $hodnoceni->getPocetHodnoceni(), 'Bez potvrzení se nic nesmaže');
    }

    public function testEditorPoChybeNechaOdeslanaKriteria(): void
    {
        $this->vytvorHodnoceniSeSablonou();

        $html = $this->vykresliStranku($this->org, true, [], [
            'akce'      => 'ulozit-kriteria',
            'skala'     => '1 2 3',
            'kategorie' => [
                'j1' => [
                    'id'       => '',
                    'nazev'    => 'Nová kategorie',
                    'kriteria' => [],
                ],
            ],
        ]);

        self::assertStringContainsString('name="kategorie[j1][nazev]" value="Nová kategorie"', $html);
        self::assertStringContainsString('name="skala" value="1 2 3"', $html);
        self::assertStringContainsString('class="hodnoceni-editor-nove"', $html);
        self::assertSame(self::SABLONA, $this->vytvorHodnoceni()->getSablona()->getText(), 'Nic se neuložilo');
    }

    public function testStrankaBezKolaABezSablony(): void
    {
        self::assertStringContainsString(
            'Org turnaje ještě nezadal kritéria hodnocení.',
            $this->vykresliStranku($this->vypravecA, false, []),
        );
        $this->vytvorHodnoceniSeSablonou();
        self::assertStringContainsString(
            'nevedeš žádné kolo turnaje D&amp;D',
            $this->vykresliStranku($this->cizi, false, []),
        );
    }

    /**
     * Vykreslí modul tak, jak ho spouští admin/index.php, jen bez rámu adminu.
     *
     * @param array<string, int>   $get
     * @param array<string, mixed> $post jen formuláře, které po chybě vykreslí stránku znovu; úspěch přesměruje a skončí
     */
    private function vykresliStranku(int $idUzivatele, bool $jeOrgTurnaje, array $get, array $post = []): string
    {
        $u = $this->createPartialMock(\Uzivatel::class, ['id', 'maPravo']);
        $u->method('id')->willReturn($idUzivatele);
        $u->method('maPravo')->willReturnCallback(
            static fn (int $pravo): bool => $jeOrgTurnaje && $pravo === \Gamecon\Pravo::ADMINISTRACE_HODNOCENI_TURNAJE,
        );
        $systemoveNastaveni = $this->createPartialMock(\Gamecon\SystemoveNastaveni\SystemoveNastaveni::class, ['rocnik']);
        $systemoveNastaveni->method('rocnik')->willReturn(self::ROK);

        $puvodniGet = $_GET;
        $puvodniPost = $_POST;
        // hlášky chyba() jdou do cookies
        $puvodniCookie = $_COOKIE;
        $_GET = array_map('strval', $get);
        $_POST = $post;
        ob_start();
        try {
            // modul čte $u a $systemoveNastaveni z rozsahu, ve kterém ho admin/index.php spouští
            require __DIR__ . '/../../admin/scripts/modules/hodnoceni-turnaje.php';
        } finally {
            $html = (string) ob_get_clean();
            $_GET = $puvodniGet;
            $_POST = $puvodniPost;
            $_COOKIE = $puvodniCookie;
        }

        return $html;
    }

    private function vytvorHodnoceni(string $ted = self::TED, int $rok = self::ROK): HodnoceniTurnaje
    {
        return new HodnoceniTurnaje($rok, new MockClock(new \DateTimeImmutable($ted)));
    }

    private function vytvorHodnoceniSeSablonou(): HodnoceniTurnaje
    {
        $hodnoceni = $this->vytvorHodnoceni();
        $hodnoceni->ulozSablonu(ParserSablonyHodnoceni::rozeber(self::SABLONA), $this->org);

        return $hodnoceni;
    }

    /**
     * @param list<int> $hodnoty v pořadí kritérií šablony
     *
     * @return array<int, int> hodnota podle id kritéria
     */
    private function getHodnoty(HodnoceniTurnaje $hodnoceni, array $hodnoty): array
    {
        $idKriterii = array_keys($hodnoceni->getSablona()->getKriteriaPodleId());

        return array_combine(array_slice($idKriterii, 0, count($hodnoty)), $hodnoty);
    }

    /**
     * Šablona, jak ji pošle editor.
     *
     * @param list<int>                                            $skala
     * @param list<array{string, ?int, list<array{string, ?int}>}> $kategorie název, id, kritéria (název, id)
     */
    private function sablona(array $skala, array $kategorie): SablonaHodnoceni
    {
        return new SablonaHodnoceni(null, $skala, array_map(
            static fn (array $jednaKategorie): KategorieHodnoceni => new KategorieHodnoceni(
                $jednaKategorie[1],
                $jednaKategorie[0],
                array_map(
                    static fn (array $kriterium): KriteriumHodnoceni => new KriteriumHodnoceni($kriterium[1], $kriterium[0]),
                    $jednaKategorie[2],
                ),
            ),
            $kategorie,
        ));
    }

    /**
     * Uložená šablona a k ní v Řešení nové kritérium Rychlost.
     */
    private function sRychlosti(SablonaHodnoceni $ulozena): SablonaHodnoceni
    {
        [$hrani, $reseni] = $ulozena->kategorie;

        return new SablonaHodnoceni(null, $ulozena->skala, [
            $hrani,
            new KategorieHodnoceni($reseni->id, $reseni->nazev, [...$reseni->kriteria, new KriteriumHodnoceni(null, 'Rychlost')]),
        ]);
    }

    private function getZmenaChticiPotvrzeni(callable $akce): ZmenaKriteriiChcePotvrzeni
    {
        try {
            $akce();
        } catch (ZmenaKriteriiChcePotvrzeni $zmenaKriteriiChcePotvrzeni) {
            return $zmenaKriteriiChcePotvrzeni;
        }
        self::fail('Uložení mělo chtít potvrzení');
    }

    /**
     * @return array<string, int> hodnota hodnocení Para Boly v 1. kole podle názvu kritéria
     */
    private function getHodnotyPodleNazvu(HodnoceniTurnaje $hodnoceni, SablonaHodnoceni $sablona): array
    {
        $ulozene = $hodnoceni->getHodnoceni($this->paraBola, 1)['hodnoty'];
        $hodnoty = [];
        foreach ($sablona->kategorie as $kategorie) {
            foreach ($kategorie->kriteria as $kriterium) {
                if (isset($ulozene[$kriterium->id])) {
                    $hodnoty[$kriterium->nazev] = $ulozene[$kriterium->id];
                }
            }
        }

        return $hodnoty;
    }

    private function getZdroj(): ?string
    {
        return dbOneCol(<<<'SQL'
            SELECT zdroj FROM turnaj_hodnoceni_sablona WHERE rok = $0
            SQL, [
            0 => self::ROK,
        ]);
    }

    /**
     * @return array<string, mixed> řádek výsledků skupiny
     */
    private function najdiVysledek(HodnoceniTurnaje $hodnoceni, int $idTymu): array
    {
        foreach ($hodnoceni->getVysledky() as $vysledek) {
            if ($vysledek['idTymu'] === $idTymu) {
                return $vysledek;
            }
        }
        self::fail("Skupina {$idTymu} ve výsledcích chybí");
    }

    private function assertZakaz(callable $akce, string $ocekavanaZprava): void
    {
        try {
            $akce();
        } catch (HodnoceniTurnajeException $hodnoceniTurnajeException) {
            self::assertStringContainsString($ocekavanaZprava, $hodnoceniTurnajeException->getMessage());

            return;
        }
        self::fail("Čekal jsem zákaz: {$ocekavanaZprava}");
    }

    private function vytvorUzivatele(string $suffix): int
    {
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_hodnoty SET
                login_uzivatele = $0,
                email1_uzivatele = $1,
                jmeno_uzivatele = 'Test',
                prijmeni_uzivatele = 'Hodnotitel'
            SQL, [
            0 => 'test_hodnoceni_' . $suffix,
            1 => 'test.hodnoceni.' . $suffix . '@example.org',
        ]);

        return (int) dbInsertId();
    }

    private function vytvorTurnaj(string $nazev, int $rok): int
    {
        dbQuery(<<<'SQL'
            INSERT INTO turnaje SET nazev = $0, rok = $1
            SQL, [
            0 => $nazev,
            1 => $rok,
        ]);

        return (int) dbInsertId();
    }

    /**
     * @param list<int> $vypraveci
     */
    private function vytvorKolo(
        int $idTurnaje,
        int $kolo,
        string $zacatek,
        array $vypraveci,
        int $typ = TypAktivity::DND,
        int $rok = self::ROK,
    ): int {
        dbQuery(<<<'SQL'
            INSERT INTO akce_seznam SET
                nazev_akce = $0,
                kapacita = 25,
                kapacita_f = 0,
                kapacita_m = 0,
                cena = 0,
                bez_slevy = 0,
                nedava_bonus = 0,
                typ = $1,
                rok = $2,
                teamova = 1,
                popis = '',
                popis_kratky = '',
                vybaveni = '',
                id_turnaje = $3,
                turnaj_kolo = $4,
                zacatek = $5,
                konec = $5 + INTERVAL 4 HOUR
            SQL, [
            0 => "Turnaj {$idTurnaje} kolo {$kolo}",
            1 => $typ,
            2 => $rok,
            3 => $idTurnaje,
            4 => $kolo,
            5 => $zacatek,
        ]);
        $idAkce = (int) dbInsertId();
        foreach ($vypraveci as $idVypravece) {
            dbQuery(<<<'SQL'
                INSERT INTO akce_organizatori SET id_akce = $0, id_uzivatele = $1
                SQL, [
                0 => $idAkce,
                1 => $idVypravece,
            ]);
        }

        return $idAkce;
    }

    /**
     * @param list<int> $kola aktivity, na kterých tým hraje
     */
    private function vytvorTym(?string $nazev, array $kola): int
    {
        static $kod = 1000;
        dbQuery(<<<'SQL'
            INSERT INTO akce_tym SET kod = $0, nazev = $1, id_kapitan = $2, zamceny = 1
            SQL, [
            0 => ++$kod,
            1 => $nazev,
            2 => $this->cizi,
        ]);
        $idTymu = (int) dbInsertId();
        foreach ($kola as $idAkce) {
            dbQuery(<<<'SQL'
                INSERT INTO akce_tym_akce SET id_tymu = $0, id_akce = $1
                SQL, [
                0 => $idTymu,
                1 => $idAkce,
            ]);
        }

        return $idTymu;
    }
}
