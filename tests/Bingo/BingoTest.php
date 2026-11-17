<?php

declare(strict_types=1);

namespace Gamecon\Tests\Bingo;

use Gamecon\Bingo\Bingo;
use Gamecon\Bingo\BingoException;
use Gamecon\Bingo\Jednani;
use Gamecon\Bingo\StavSouteze;
use Gamecon\Tests\Db\AbstractTestDb;
use Symfony\Component\Clock\MockClock;

/**
 * Bingo z jednání rady: soutěž začne v 19:00 nebo když ji někdo spustí, pak jsou karty zamčené, odškrtnutí platí
 * všem kartám jednání a první bingo soutěž ukončí a uloží vítěze. Kdo za běhu nemá kartu, nevidí, co zaznělo.
 * Po bingu jde zrušit jen odškrtnutí z vítězné řady a vrátit ho jde i s původním časem.
 */
class BingoTest extends AbstractTestDb
{
    private const string DEN_JEDNANI = '2030-01-10';

    private const string DALSI_DEN_JEDNANI = '2030-01-24';

    private const string PRED_STARTEM = self::DEN_JEDNANI . ' 18:00:00';

    private const string ZA_BEHU = self::DEN_JEDNANI . ' 19:30:00';

    protected function setUp(): void
    {
        parent::setUp();
        // migrace zakládá skutečné termíny jednání, testy si dělají vlastní
        dbQuery(<<<'SQL'
            DELETE FROM bingo_zazneni
            SQL);
        dbQuery(<<<'SQL'
            DELETE FROM bingo_karta
            SQL);
        dbQuery(<<<'SQL'
            DELETE FROM bingo_jednani
            SQL);
        dbQuery(<<<'SQL'
            DELETE FROM bingo_fraze
            SQL);
    }

    public function testRadyJsouRadkySloupceAObeUhlopricky(): void
    {
        $rady = Bingo::getRady();

        self::assertCount(12, $rady);
        self::assertContains([10, 11, 12, 13, 14], $rady);
        self::assertContains([2, 7, 12, 17, 22], $rady);
        self::assertContains([0, 6, 12, 18, 24], $rady);
        self::assertContains([4, 8, 12, 16, 20], $rady);
    }

    public function testAktualniJednaniPlatiDoRanaPoNem(): void
    {
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $this->vytvorJednani(self::DALSI_DEN_JEDNANI);

        $poPulnoci = $this->bingo('2030-01-11 05:59:00');
        self::assertSame(self::DEN_JEDNANI, $poPulnoci->getAktualniJednani()?->datum->format('Y-m-d'));
        self::assertSame(StavSouteze::BEZI, $poPulnoci->getStavSouteze($jednani), 'Jednání po půlnoci ještě běží.');

        $rano = $this->bingo('2030-01-11 06:00:00');
        self::assertSame(self::DALSI_DEN_JEDNANI, $rano->getAktualniJednani()?->datum->format('Y-m-d'));
        self::assertTrue($rano->isProbehle($jednani));
        self::assertSame(StavSouteze::BEZ_VITEZE, $rano->getStavSouteze($jednani));
    }

    public function testSoutezZacneV19(): void
    {
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);

        self::assertSame(StavSouteze::PRIPRAVA, $this->bingo(self::DEN_JEDNANI . ' 18:59:59')->getStavSouteze($jednani));
        self::assertSame(StavSouteze::BEZI, $this->bingo(self::DEN_JEDNANI . ' 19:00:00')->getStavSouteze($jednani));
    }

    public function testSoutezJdeSpustitDrivJenVDenJednani(): void
    {
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $denPredem = $this->bingo('2030-01-09 20:00:00');
        self::assertFalse($denPredem->getStav($jednani, 0)['lzeSpustit']);
        try {
            $denPredem->spust($jednani);
            self::fail('Den před jednáním soutěž spustit nejde.');
        } catch (BingoException) {
        }

        $bingo = $this->bingo(self::DEN_JEDNANI . ' 18:30:00');
        self::assertTrue($bingo->getStav($jednani, 0)['lzeSpustit']);
        $bingo->spust($jednani);

        $spustene = $bingo->getJednani($jednani->id);
        self::assertSame(StavSouteze::BEZI, $bingo->getStavSouteze($spustene));
        self::assertSame('18:30', $bingo->getZacatek($spustene)->format('G:i'));
        self::assertFalse($bingo->getStav($spustene, 0)['lzeSpustit']);
    }

    public function testKartaMaRuzneFrazeZPooluAVolnyStred(): void
    {
        $this->vytvorFraze(30);
        $bingo = $this->bingo(self::PRED_STARTEM);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $idUzivatele = $this->vytvorUzivatele('karta');

        $bingo->vygenerujKartu($jednani, $idUzivatele);

        $karta = $bingo->getKarta($jednani, $idUzivatele);
        self::assertNotNull($karta);
        self::assertSame(array_values(array_diff(range(0, 24), [Bingo::VOLNE_POLE])), array_keys($karta));
        $idsFrazi = array_column($karta, 'id');
        self::assertCount(Bingo::FRAZI_NA_KARTE, array_unique($idsFrazi));
        self::assertSame([], array_diff($idsFrazi, array_column($bingo->getFraze(), 'id')));
    }

    public function testKartaPotrebujeDostFrazi(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE - 1);

        $this->expectException(BingoException::class);
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu(
            $this->vytvorJednani(self::DEN_JEDNANI),
            $this->vytvorUzivatele('malo'),
        );
    }

    public function testKartuJdeMenitJenDoStartu(): void
    {
        $this->vytvorFraze(30);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $idUzivatele = $this->vytvorUzivatele('meni');
        $predStartem = $this->bingo(self::PRED_STARTEM);
        $predStartem->vygenerujKartu($jednani, $idUzivatele);
        $prvniKarta = $predStartem->getStav($jednani, $idUzivatele)['idKarty'];
        self::assertTrue($predStartem->getStav($jednani, $idUzivatele)['lzeGenerovat']);
        $predStartem->vygenerujKartu($jednani, $idUzivatele);
        $stav = $predStartem->getStav($jednani, $idUzivatele);
        self::assertNotSame($prvniKarta, $stav['idKarty'], 'Podle id karty pozná stránka, že je karta jiná.');
        self::assertSame($predStartem->getKarta($jednani, $idUzivatele), $stav['karta']);

        $zaBehu = $this->bingo(self::ZA_BEHU);
        self::assertFalse($zaBehu->getStav($jednani, $idUzivatele)['lzeGenerovat']);
        $this->expectException(BingoException::class);
        $zaBehu->vygenerujKartu($jednani, $idUzivatele);
    }

    public function testOdskrtnutiPlatiVsem(): void
    {
        // s právě tolika frázemi mají obě karty tytéž fráze
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $prvni = $this->vytvorUzivatele('prvni');
        $druhy = $this->vytvorUzivatele('druhy');
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $prvni);
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $druhy);
        $bingo = $this->bingo(self::ZA_BEHU);
        $idFraze = $bingo->getKarta($jednani, $druhy)[0]['id'];

        $bingo->odskrtni($jednani, $idFraze, $prvni, true);

        $stav = $bingo->getStav($jednani, $druhy);
        self::assertSame('bezi', $stav['stav']);
        self::assertSame([$idFraze], array_keys($stav['zaznele']));
        self::assertSame('19:30', $stav['zaznele'][$idFraze]['kdy']);
        self::assertSame('Test „test_bingo_prvni“ Bingo', $stav['zaznele'][$idFraze]['kdo']);
    }

    public function testZruseniOdskrtnuti(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $idUzivatele = $this->vytvorUzivatele('zruseni');
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $idUzivatele);
        $bingo = $this->bingo(self::ZA_BEHU);
        $idFraze = $bingo->getKarta($jednani, $idUzivatele)[0]['id'];
        $bingo->odskrtni($jednani, $idFraze, $idUzivatele, true);

        $bingo->odskrtni($jednani, $idFraze, $idUzivatele, false);

        self::assertSame([], $bingo->getStav($jednani, $idUzivatele)['zaznele']);
    }

    public function testPredStartemSeNeodskrtava(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $bingo = $this->bingo(self::PRED_STARTEM);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $idUzivatele = $this->vytvorUzivatele('predem');
        $bingo->vygenerujKartu($jednani, $idUzivatele);
        self::assertFalse($bingo->getStav($jednani, $idUzivatele)['lzeOdskrtnout']);

        $this->expectException(BingoException::class);
        $bingo->odskrtni($jednani, $bingo->getKarta($jednani, $idUzivatele)[0]['id'], $idUzivatele, true);
    }

    public function testOdskrtavaJenKdoMaKartu(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $hrac = $this->vytvorUzivatele('hrac');
        $divak = $this->vytvorUzivatele('divak');
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $hrac);
        $bingo = $this->bingo(self::ZA_BEHU);

        $this->expectException(BingoException::class);
        $bingo->odskrtni($jednani, $bingo->getKarta($jednani, $hrac)[0]['id'], $divak, true);
    }

    public function testOdskrtnoutJdeJenFraziZeHry(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $idMimoHru = $this->vytvorFraze(1)[0];
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $idUzivatele = $this->vytvorUzivatele('mimo');
        $predStartem = $this->bingo(self::PRED_STARTEM);
        // smazaná fráze se na kartu nedostane, karta má proto zbylých 24
        $predStartem->smazFrazi($idMimoHru);
        $predStartem->vygenerujKartu($jednani, $idUzivatele);

        $this->expectException(BingoException::class);
        $this->bingo(self::ZA_BEHU)->odskrtni($jednani, $idMimoHru, $idUzivatele, true);
    }

    public function testKdoZaBehuNemaKartuNevidiCoZaznelo(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $hrac = $this->vytvorUzivatele('hrac');
        $pozdniPrichozi = $this->vytvorUzivatele('pozde');
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $hrac);
        $bingo = $this->bingo(self::ZA_BEHU);
        $idFraze = $bingo->getKarta($jednani, $hrac)[0]['id'];
        $bingo->odskrtni($jednani, $idFraze, $hrac, true);

        $stav = $bingo->getStav($jednani, $pozdniPrichozi);
        self::assertTrue($stav['skryto']);
        self::assertSame([], $stav['zaznele']);
        self::assertSame([], $stav['frazeVeHre']);
        self::assertTrue($stav['lzeGenerovat']);

        $bingo->vygenerujKartu($jednani, $pozdniPrichozi);

        $stav = $bingo->getStav($jednani, $pozdniPrichozi);
        self::assertFalse($stav['skryto']);
        self::assertSame([$idFraze], array_keys($stav['zaznele']), 'S kartou uvidí, co už zaznělo.');
        self::assertFalse($stav['lzeGenerovat'], 'Za běhu dostane kartu jen jednu.');
        $this->expectException(BingoException::class);
        $bingo->vygenerujKartu($jednani, $pozdniPrichozi);
    }

    public function testPrvniBingoUkonciSoutezAUloziViteze(): void
    {
        $this->vytvorFraze(30);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $hrac = $this->vytvorUzivatele('vitez');
        $divak = $this->vytvorUzivatele('divak');
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $hrac);
        $bingo = $this->bingo(self::ZA_BEHU);
        $karta = $bingo->getKarta($jednani, $hrac);
        foreach ([10, 11, 13] as $pozice) {
            $bingo->odskrtni($jednani, $karta[$pozice]['id'], $hrac, true);
        }
        self::assertSame(StavSouteze::BEZI, $bingo->getStavSouteze($jednani), 'Prostřední řádek ještě není celý.');

        $this->bingo(self::DEN_JEDNANI . ' 19:42:00')->odskrtni($jednani, $karta[14]['id'], $hrac, true);

        self::assertSame(StavSouteze::VYHRANO, $bingo->getStavSouteze($jednani));
        $stav = $bingo->getStav($jednani, $divak);
        self::assertFalse($stav['skryto'], 'Po konci soutěže vidí i ten, kdo nehrál.');
        self::assertCount(1, $stav['vitezove']);
        self::assertSame($hrac, $stav['vitezove'][0]['idUzivatele']);
        self::assertSame('19:42', $stav['vitezove'][0]['kdy'], 'Vítěz je v čase fráze, která řadu dokončila.');
        self::assertSame([10, 11, 12, 13, 14], $stav['vitezove'][0]['rady']);
        self::assertSame($karta[14], $stav['vitezove'][0]['pole'][14]);
        self::assertStringContainsString('Vítěz: Test „test_bingo_vitez“ Bingo', $stav['popis']);

        $prehled = $this->bingo('2030-02-01 12:00:00')->getPrehledJednani();
        self::assertSame(StavSouteze::VYHRANO, $prehled[0]['stav'], 'Vítěz zůstane i po jednání.');
        self::assertSame([[
            'jmeno' => 'Test „test_bingo_vitez“ Bingo',
            'kdy'   => '19:42',
        ]], $prehled[0]['vitezove']);

        $this->expectException(BingoException::class);
        $bingo->odskrtni($jednani, $karta[0]['id'], $hrac, true);
    }

    public function testZruseniOdskrtnutiPoBinguVratiSoutezDoHry(): void
    {
        $this->vytvorFraze(30);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $hrac = $this->vytvorUzivatele('omyl');
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $hrac);
        $bingo = $this->bingo(self::ZA_BEHU);
        $karta = $bingo->getKarta($jednani, $hrac);
        foreach ([10, 11, 13, 14] as $pozice) {
            $bingo->odskrtni($jednani, $karta[$pozice]['id'], $hrac, true);
        }
        self::assertSame(StavSouteze::VYHRANO, $bingo->getStavSouteze($jednani));

        $bingo->odskrtni($jednani, $karta[14]['id'], $hrac, false);

        self::assertSame(StavSouteze::BEZI, $bingo->getStavSouteze($jednani));
        self::assertSame([], $bingo->getStav($jednani, $hrac)['vitezove']);
    }

    public function testPozdePrichoziNevyhrajeZpetne(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $hrac = $this->vytvorUzivatele('hrac');
        $pozdniPrichozi = $this->vytvorUzivatele('pozde');
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $hrac);
        $bingo = $this->bingo(self::ZA_BEHU);
        $kartaHrace = $bingo->getKarta($jednani, $hrac);
        // hráči chybí do řady pozice 14, pozdě příchozí bude mít odškrtnuté fráze v celé řadě
        $odskrtnute = [];
        foreach ([0, 10, 11, 13] as $pozice) {
            $bingo->odskrtni($jednani, $kartaHrace[$pozice]['id'], $hrac, true);
            $odskrtnute[] = $kartaHrace[$pozice]['id'];
        }
        $this->bingo(self::DEN_JEDNANI . ' 19:50:00')->vygenerujKartu($jednani, $pozdniPrichozi);
        $this->presunFrazeDoRady($jednani, $pozdniPrichozi, $odskrtnute, [10, 11, 13, 14]);
        // náhodná karta pozdě příchozího mohla vyhrát už při vygenerování; bez vítěze soutěž běží a vítěze určí až
        // přepočet po dalším odškrtnutí, tady fráze, která hráči ani pozdě příchozímu bingo nedá
        dbQuery(<<<'SQL'
            UPDATE bingo_karta SET vyhra = NULL WHERE id_jednani = $0
            SQL, [
            0 => $jednani->id,
        ]);

        $this->bingo(self::DEN_JEDNANI . ' 19:55:00')->odskrtni($jednani, $kartaHrace[24]['id'], $hrac, true);

        $vitezove = $bingo->getStav($jednani, $hrac)['vitezove'];
        self::assertSame([$pozdniPrichozi], array_column($vitezove, 'idUzivatele'));
        self::assertSame('19:50', $vitezove[0]['kdy'], 'Bingo má nejdřív od chvíle, kdy kartu dostal.');
    }

    public function testFrazeSeNeopakujeASmazanaZustaneNaKarte(): void
    {
        $bingo = $this->bingo(self::PRED_STARTEM);
        $idAutora = $this->vytvorUzivatele('autor');
        $bingo->pridejFrazi("  Když jsem   byl\ndramaturg programu ", $idAutora);
        self::assertSame(['Když jsem byl dramaturg programu'], array_column($bingo->getFraze(), 'text'));
        try {
            $bingo->pridejFrazi('když jsem byl dramaturg programu', $idAutora);
            self::fail('Stejná fráze nemá jít přidat podruhé.');
        } catch (BingoException) {
        }

        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE - 1);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $bingo->vygenerujKartu($jednani, $idAutora);
        $idFraze = $bingo->getFraze()[0]['id'];
        $bingo->smazFrazi($idFraze);

        self::assertNotContains($idFraze, array_column($bingo->getFraze(), 'id'));
        self::assertContains($idFraze, array_column($bingo->getKarta($jednani, $idAutora), 'id'));
    }

    public function testJednaniSOdskrtnutimZustaneVHistorii(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $idUzivatele = $this->vytvorUzivatele('historie');
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $idUzivatele);
        $bingo = $this->bingo(self::ZA_BEHU);
        $bingo->odskrtni($jednani, $bingo->getKarta($jednani, $idUzivatele)[0]['id'], $idUzivatele, true);

        try {
            $bingo->smazJednani($jednani);
            self::fail('Jednání, na kterém se odškrtávalo, nemá jít smazat.');
        } catch (BingoException) {
        }

        $budouci = $this->vytvorJednani(self::DALSI_DEN_JEDNANI);
        $bingo->vygenerujKartu($budouci, $idUzivatele);
        $bingo->smazJednani($budouci);
        self::assertNull($bingo->getJednani($budouci->id));
        self::assertNotNull($bingo->getJednani($jednani->id));
    }

    public function testJednaniNejdePridatDoMinulostiAniDvakrat(): void
    {
        $bingo = $this->bingo(self::PRED_STARTEM);

        $bingo->pridejJednani(self::DEN_JEDNANI);
        self::assertSame(self::DEN_JEDNANI, $bingo->getAktualniJednani()?->datum->format('Y-m-d'));

        foreach (['2030-01-09', self::DEN_JEDNANI, '2030-02-30', 'zítra'] as $spatneDatum) {
            try {
                $bingo->pridejJednani($spatneDatum);
                self::fail("Jednání '{$spatneDatum}' nemá jít přidat.");
            } catch (BingoException) {
            }
        }
    }

    public function testPoBinguJdeZrusitJenVitezneOdskrtnutiAVratitHoSPuvodnimCasem(): void
    {
        $this->vytvorFraze(30);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $hrac = $this->vytvorUzivatele('spor');
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $hrac);
        $karta = $this->bingo(self::ZA_BEHU)->getKarta($jednani, $hrac);
        $id = static fn (int $pozice): int => $karta[$pozice]['id'];
        foreach ([10, 11, 13] as $pozice) {
            $this->bingo(self::ZA_BEHU)->odskrtni($jednani, $id($pozice), $hrac, true);
        }
        $this->bingo(self::DEN_JEDNANI . ' 19:35:00')->odskrtni($jednani, $id(0), $hrac, true);
        $this->bingo(self::DEN_JEDNANI . ' 19:42:00')->odskrtni($jednani, $id(14), $hrac, true);

        $poBingu = $this->bingo(self::DEN_JEDNANI . ' 19:50:00');
        self::assertEqualsCanonicalizing(
            [$id(10), $id(11), $id(13), $id(14)],
            $poBingu->getStav($jednani, $hrac)['lzeZrusit'],
            'Po bingu jde zrušit jen odškrtnutí z vítězné řady.',
        );
        try {
            $poBingu->odskrtni($jednani, $id(0), $hrac, false);
            self::fail('Odškrtnutí mimo vítěznou řadu po bingu zrušit nejde, znovu odškrtnout by ho už nešlo.');
        } catch (BingoException) {
        }

        // někdo omylem zruší odškrtnutí z vítězné řady: bingo zmizí, soutěž běží dál
        $poBingu->odskrtni($jednani, $id(11), $hrac, false);
        $stav = $poBingu->getStav($jednani, $hrac);
        self::assertSame('bezi', $stav['stav']);
        self::assertArrayNotHasKey($id(11), $stav['zaznele']);
        self::assertSame('19:30', $stav['zrusene'][$id(11)]['kdy']);
        self::assertSame('19:50', $stav['zrusene'][$id(11)]['zruseno']);
        self::assertTrue($stav['lzeVratit']);

        // mezitím vyhraje jinou řadou (sloupec 0, 5, 10, 15, 20) později
        foreach ([5, 15, 20] as $pozice) {
            $this->bingo(self::DEN_JEDNANI . ' 19:55:00')->odskrtni($jednani, $id($pozice), $hrac, true);
        }
        $pozdejsiBingo = $this->bingo(self::DEN_JEDNANI . ' 19:56:00');
        self::assertSame('19:55', $pozdejsiBingo->getStav($jednani, $hrac)['vitezove'][0]['kdy']);
        try {
            $pozdejsiBingo->odskrtni($jednani, $id(24), $hrac, true);
            self::fail('Po bingu nejde nic nově odškrtnout.');
        } catch (BingoException) {
        }

        // vrácení i po dalším bingu: platí původní čas a s ním původní bingo
        $pozdejsiBingo->odskrtni($jednani, $id(11), $hrac, true);

        $stav = $pozdejsiBingo->getStav($jednani, $hrac);
        self::assertSame('vyhrano', $stav['stav']);
        self::assertSame('19:42', $stav['vitezove'][0]['kdy'], 'Omylem zrušené bingo nepřijde o svůj čas.');
        self::assertSame('19:30', $stav['zaznele'][$id(11)]['kdy']);
        self::assertSame([], $stav['zrusene']);
    }

    public function testZruseniPredBingemPlatiAZnovuOdskrtnutaFrazeMaNovyCas(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $hrac = $this->vytvorUzivatele('preklik');
        $this->bingo(self::PRED_STARTEM)->vygenerujKartu($jednani, $hrac);
        $bingo = $this->bingo(self::ZA_BEHU);
        $idFraze = $bingo->getKarta($jednani, $hrac)[0]['id'];
        $bingo->odskrtni($jednani, $idFraze, $hrac, true);
        $bingo->odskrtni($jednani, $idFraze, $hrac, false);

        $this->bingo(self::DEN_JEDNANI . ' 19:45:00')->odskrtni($jednani, $idFraze, $hrac, true);

        $stav = $bingo->getStav($jednani, $hrac);
        self::assertSame('19:45', $stav['zaznele'][$idFraze]['kdy'], 'Fráze zazněla až teď.');
        self::assertSame([], $stav['zrusene']);
    }

    public function testStrankaSePtaCastoJenZaBehuSouteze(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $hrac = $this->vytvorUzivatele('sledovani');

        self::assertSame(60, $this->bingo('2030-01-09 12:00:00')->getStav($jednani, $hrac)['sledovatZa']);
        self::assertSame(10, $this->bingo(self::PRED_STARTEM)->getStav($jednani, $hrac)['sledovatZa']);
        self::assertSame(
            4,
            $this->bingo(self::DEN_JEDNANI . ' 18:59:56')->getStav($jednani, $hrac)['sledovatZa'],
            'Automatický start pozná stránka hned.',
        );
        self::assertSame(3, $this->bingo(self::ZA_BEHU)->getStav($jednani, $hrac)['sledovatZa']);
        self::assertNull($this->bingo('2030-01-11 06:00:00')->getStav($jednani, $hrac)['sledovatZa']);
    }

    public function testSmazaneJednaniNejdeMenit(): void
    {
        $this->vytvorFraze(Bingo::FRAZI_NA_KARTE);
        $jednani = $this->vytvorJednani(self::DEN_JEDNANI);
        $bingo = $this->bingo(self::PRED_STARTEM);
        $bingo->smazJednani($jednani);

        $this->expectException(BingoException::class);
        $bingo->vygenerujKartu($jednani, $this->vytvorUzivatele('smazane'));
    }

    private function bingo(string $ted): Bingo
    {
        return new Bingo(new MockClock(new \DateTimeImmutable($ted)));
    }

    private function vytvorJednani(string $datum): Jednani
    {
        dbQuery(<<<'SQL'
            INSERT INTO bingo_jednani SET datum = $0
            SQL, [
            0 => $datum,
        ]);

        return new Jednani((int) dbInsertId(), new \DateTimeImmutable($datum));
    }

    /**
     * Přeskládá kartu tak, aby zadané fráze ležely na zadaných pozicích (prohodí je s tím, co tam bylo).
     *
     * @param list<int> $idsFrazi
     * @param list<int> $pozice
     */
    private function presunFrazeDoRady(Jednani $jednani, int $idUzivatele, array $idsFrazi, array $pozice): void
    {
        $idKarty = (int) dbFetchSingle(<<<'SQL'
            SELECT id FROM bingo_karta WHERE id_jednani = $0 AND id_uzivatele = $1
            SQL, [
            0 => $jednani->id,
            1 => $idUzivatele,
        ]);
        foreach ($idsFrazi as $index => $idFraze) {
            $puvodniPozice = (int) dbFetchSingle(<<<'SQL'
                SELECT pozice FROM bingo_karta_pole WHERE id_karty = $0 AND id_fraze = $1
                SQL, [
                0 => $idKarty,
                1 => $idFraze,
            ]);
            // prohození přes dočasnou pozici mimo kartu, pozice je součást primárního klíče
            dbQuery(<<<'SQL'
                UPDATE bingo_karta_pole SET pozice = 255 WHERE id_karty = $0 AND pozice = $1
                SQL, [
                0 => $idKarty,
                1 => $pozice[$index],
            ]);
            dbQuery(<<<'SQL'
                UPDATE bingo_karta_pole SET pozice = $2 WHERE id_karty = $0 AND pozice = $1
                SQL, [
                0 => $idKarty,
                1 => $puvodniPozice,
                2 => $pozice[$index],
            ]);
            dbQuery(<<<'SQL'
                UPDATE bingo_karta_pole SET pozice = $1 WHERE id_karty = $0 AND pozice = 255
                SQL, [
                0 => $idKarty,
                1 => $puvodniPozice,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function vytvorFraze(int $pocet): array
    {
        static $poradi = 0;
        $ids = [];
        for ($i = 0; $i < $pocet; ++$i) {
            ++$poradi;
            dbQuery(<<<'SQL'
                INSERT INTO bingo_fraze SET text = $0
                SQL, [
                0 => 'Fráze ' . $poradi,
            ]);
            $ids[] = (int) dbInsertId();
        }

        return $ids;
    }

    private function vytvorUzivatele(string $suffix): int
    {
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_hodnoty SET
                login_uzivatele = $0,
                email1_uzivatele = $1,
                jmeno_uzivatele = 'Test',
                prijmeni_uzivatele = 'Bingo'
            SQL, [
            0 => 'test_bingo_' . $suffix,
            1 => 'test.bingo.' . $suffix . '@example.org',
        ]);

        return (int) dbInsertId();
    }
}
