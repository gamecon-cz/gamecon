<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\Korektura;

use Gamecon\Ai\Exceptions\ChybaApiAi;
use Gamecon\Ai\OdpovedAiNaPozadavek;
use Gamecon\Ai\PoskytovatelAi;
use Gamecon\Aktivita\Korektura\Exceptions\KorekturuNelzeVyridit;
use Gamecon\Aktivita\Korektura\KorekturyTextu;
use Gamecon\Aktivita\Korektura\KorekturyTextuRepository;
use Gamecon\Aktivita\Korektura\StavKorekturyEnum;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Model\Ai\FalesneDavkyAi;

class KorekturyTextuTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    private SystemoveNastaveni $systemoveNastaveni;
    private KorekturyTextuRepository $repository;
    private KorekturyTextu $korektury;
    private FalesneDavkyAi $davkyAi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $this->repository = new KorekturyTextuRepository();
        $this->davkyAi = new FalesneDavkyAi();
        $this->korektury = new KorekturyTextu($this->systemoveNastaveni, $this->repository, $this->davkyAi);
    }

    /**
     * @test
     */
    public function exportVezmeNeopraveneVerejneAktivityRocnikuJednouZaRodinuInstanci(): void
    {
        $rocnik = $this->systemoveNastaveni->rocnik();
        $neopravena = $this->vytvorAktivitu('Neopravená');
        $opravena = $this->vytvorAktivitu('Opravená', probehlaKorekce: true);
        $technicka = $this->vytvorAktivitu('Technická', typ: TypAktivity::TECHNICKA);
        $loni = $this->vytvorAktivitu('Loňská', rok: $rocnik - 1);
        [$hlavni, $dalsiInstance] = $this->vytvorRodinuInstanci('Rodina');

        $kExportu = array_keys($this->repository->dejTextyKExportu($rocnik));

        self::assertContains($neopravena, $kExportu);
        self::assertContains($hlavni, $kExportu);
        self::assertNotContains($dalsiInstance, $kExportu, 'Instance sdílejí texty, stačí hlavní aktivita');
        self::assertNotContains($opravena, $kExportu);
        self::assertNotContains($technicka, $kExportu);
        self::assertNotContains($loni, $kExportu);

        $baliky = $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);

        self::assertCount(1, $baliky);
        self::assertNotContains(
            $neopravena,
            array_keys($this->repository->dejTextyKExportu($rocnik)),
            'Exportovaná aktivita se znovu nevyexportuje',
        );
        self::assertStringContainsString(
            '<gc-aktivita id="' . $neopravena . '">',
            $this->korektury->soubor($baliky[0]),
        );
    }

    /**
     * @test
     */
    public function prijatyNavrhZapiseTextyCeleRodineAOznaciJiJakoOpravenou(): void
    {
        [$hlavni, $dalsiInstance] = $this->vytvorRodinuInstanci('Rodina s chybou');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);

        $vysledek = $this->korektury->importuj(<<<TEXT
<gc-aktivita id="{$hlavni}">
<gc-popis>Popis, který je opravený.</gc-popis>
<gc-zmeny>čárka</gc-zmeny>
</gc-aktivita>
TEXT,
        );

        self::assertSame(1, $vysledek->pocetNavrhu);
        self::assertSame(0, $vysledek->pocetBezZmen);
        self::assertSame([], $vysledek->varovani);

        $korektura = $this->repository->dejOtevrene([$hlavni])[$hlavni];
        self::assertSame(StavKorekturyEnum::NAVRZENO, $korektura->stav);
        self::assertNull($korektura->navrhNazev);
        self::assertSame('Popis, který je opravený.', $korektura->navrhPopis);
        self::assertSame('čárka', $korektura->zmeny);

        $this->korektury->prijmi($korektura->id, $korektura->navrh(), \Uzivatel::SYSTEM);

        foreach ([$hlavni, $dalsiInstance] as $idAktivity) {
            $radek = dbOneLine('SELECT nazev_akce, popis, probehla_korekce FROM akce_seznam WHERE id_akce = $0', [$idAktivity]);
            self::assertSame('Rodina s chybou', $radek['nazev_akce']);
            self::assertSame('Popis, který je opravený.', $radek['popis']);
            self::assertSame(1, (int) $radek['probehla_korekce']);
        }
        self::assertSame(StavKorekturyEnum::PRIJATO, $this->repository->dej($korektura->id)->stav);
    }

    /**
     * @test
     */
    public function navrhTextuZmenenehoOdExportuNejdePrijmout(): void
    {
        $idAktivity = $this->vytvorAktivitu('Měněná');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->importuj("<gc-aktivita id=\"{$idAktivity}\"><gc-popis>Opravený popis.</gc-popis></gc-aktivita>");
        dbQuery('UPDATE akce_seznam SET popis = $0 WHERE id_akce = $1', ['Pořadatel to mezitím přepsal.', $idAktivity]);

        $korektura = $this->repository->dejOtevrene([$idAktivity])[$idAktivity];

        $this->expectException(KorekturuNelzeVyridit::class);
        $this->korektury->prijmi($korektura->id, $korektura->navrh(), \Uzivatel::SYSTEM);
    }

    /**
     * @test
     */
    public function importPreskociNeexportovanouAktivituAStejnyTextNeniZmena(): void
    {
        $exportovana = $this->vytvorAktivitu('Exportovaná');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $neexportovana = $this->vytvorAktivitu('Až po exportu');

        $vysledek = $this->korektury->importuj(<<<TEXT
<gc-aktivita id="{$exportovana}">
<gc-nazev>Exportovaná</gc-nazev>
<gc-popis>
Popis aktivity Exportovaná.
</gc-popis>
<gc-zmeny>nic</gc-zmeny>
</gc-aktivita>
<gc-aktivita id="{$neexportovana}"><gc-nazev>Jiný</gc-nazev></gc-aktivita>
TEXT,
        );

        self::assertSame(1, $vysledek->pocetNavrhu);
        self::assertSame(1, $vysledek->pocetBezZmen);
        self::assertCount(1, $vysledek->varovani);
        self::assertStringContainsString((string) $neexportovana, $vysledek->varovani[0]);

        $korektura = $this->repository->dejOtevrene([$exportovana])[$exportovana];
        self::assertFalse($korektura->maNavrzeneZmeny());
        self::assertNull($korektura->zmeny, 'Bez změn není co popisovat');

        self::assertSame(1, $this->korektury->prijmiVsechnyBezZmen(\Uzivatel::SYSTEM));
        self::assertSame(1, (int) dbOneCol('SELECT probehla_korekce FROM akce_seznam WHERE id_akce = $0', [$exportovana]));
    }

    /**
     * @test
     */
    public function prijetiNechaNezmenenyTextPresneJakBylIKoncumRadku(): void
    {
        $popisSWindowsKonciRadku = "První odstavec.\r\n\r\nDruhý odstavec.\r\n";
        $idAktivity = $this->vytvorAktivitu('Z Windows', popis: $popisSWindowsKonciRadku);
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->importuj("<gc-aktivita id=\"{$idAktivity}\"><gc-nazev>Z Windowsu</gc-nazev></gc-aktivita>");
        $korektura = $this->repository->dejOtevrene([$idAktivity])[$idAktivity];

        $this->korektury->prijmi($korektura->id, $korektura->navrh(), \Uzivatel::SYSTEM);

        $radek = dbOneLine('SELECT nazev_akce, popis FROM akce_seznam WHERE id_akce = $0', [$idAktivity]);
        self::assertSame('Z Windowsu', $radek['nazev_akce']);
        self::assertSame($popisSWindowsKonciRadku, $radek['popis']);
    }

    /**
     * @test
     */
    public function zamitnutaAktivitaZustaneNeopravenaAJdeZnovuNaKorekturu(): void
    {
        $idAktivity = $this->vytvorAktivitu('Zamítnutá');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->importuj("<gc-aktivita id=\"{$idAktivity}\"><gc-nazev>Úplně jiný název</gc-nazev></gc-aktivita>");
        $korektura = $this->repository->dejOtevrene([$idAktivity])[$idAktivity];

        $this->korektury->zamitni($korektura->id, \Uzivatel::SYSTEM);

        self::assertSame('Zamítnutá', dbOneCol('SELECT nazev_akce FROM akce_seznam WHERE id_akce = $0', [$idAktivity]));
        self::assertContains(
            $idAktivity,
            array_keys($this->repository->dejTextyKExportu($this->systemoveNastaveni->rocnik())),
        );
    }

    /**
     * @test
     */
    public function souborMaUAktivityDrivejsiOpravenouVerziSeStejnymNazvem(): void
    {
        $rocnik = $this->systemoveNastaveni->rocnik();
        $this->vytvorAktivitu('Opakovaná hra', rok: $rocnik - 2, probehlaKorekce: true, popis: 'Předloňský opravený popis.');
        $this->vytvorAktivitu('Opakovaná hra', rok: $rocnik - 1, probehlaKorekce: true, popis: 'Loňský opravený popis.');
        $this->vytvorAktivitu('Opakovaná hra', rok: $rocnik - 1, probehlaKorekce: false, popis: 'Loňský neopravený popis.');
        $letosni = $this->vytvorAktivitu('Opakovaná hra');

        $baliky = $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $soubor = $this->korektury->soubor($baliky[0]);

        $blokAktivity = substr($soubor, strpos($soubor, '<gc-aktivita id="' . $letosni . '">'));
        $blokAktivity = substr($blokAktivity, 0, strpos($blokAktivity, '</gc-aktivita>'));
        self::assertStringContainsString('<gc-drivejsi rok="' . ($rocnik - 1) . '">', $blokAktivity);
        self::assertStringContainsString('Loňský opravený popis.', $blokAktivity);
        self::assertStringNotContainsString('neopravený', $blokAktivity);
    }

    /**
     * @test
     */
    public function aiDostaneKazdouCekajiciAktivituJednouVeVlastnimPozadavku(): void
    {
        $prvni = $this->vytvorAktivitu('První přes API');
        $druha = $this->vytvorAktivitu('Druhá přes API');
        $balik = $this->korektury->exportuj(1000, \Uzivatel::SYSTEM)[0];

        $poslano = $this->korektury->posliAi($balik);

        self::assertCount(1, $this->davkyAi->odeslane);
        $idDavky = $this->davkyAi->posledniDavka();
        $zpravy = $this->davkyAi->zpravyPodleAktivit($idDavky);
        self::assertSame($poslano, count($zpravy));
        self::assertStringContainsString('<gc-popis-kratky>Krátký popis aktivity První přes API</gc-popis-kratky>', $zpravy[$prvni]);
        self::assertStringNotContainsString('Druhá přes API', $zpravy[$prvni], 'Každá aktivita jde ve vlastním požadavku');
        self::assertArrayHasKey($druha, $zpravy);
        self::assertStringContainsString(
            'Korektura textů aktivit GameConu ' . $this->systemoveNastaveni->rocnik(),
            $this->davkyAi->odeslane[$idDavky]['pokyny'],
            'Pokyny jsou tytéž jako v souboru, jdou jako systémový prompt',
        );
        self::assertSame($poslano, $this->korektury->dejCekajiciBaliky()[$balik]->pocetUAi);

        self::assertSame(0, $this->korektury->posliAi(null), 'Co už u AI je, znovu nejde');
        self::assertCount(1, $this->davkyAi->odeslane);
        self::assertStringContainsString(
            '<gc-aktivita id="' . $prvni . '">',
            $this->korektury->soubor($balik),
            'Odeslané texty jdou dál stáhnout do chatu',
        );
    }

    /**
     * @test
     */
    public function odpovediSkonceneDavkySeNactouJakoNavrhyANezodpovezeneJdouPoslatZnovu(): void
    {
        $sChybou = $this->vytvorAktivitu('S chybou');
        $bezChyb = $this->vytvorAktivitu('Bez chyb');
        $odmitnuta = $this->vytvorAktivitu('Odmítnutá');
        $sCizimBlokem = $this->vytvorAktivitu('S cizím blokem');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->posliAi(null);
        $idDavky = $this->davkyAi->posledniDavka();

        self::assertNull($this->korektury->nactiOdpovediAi()->vysledekImportu, 'Dávka ještě běží');

        $this->davkyAi->dokonci($idDavky, static fn (int $idAktivity) => match ($idAktivity) {
            $sChybou => OdpovedAiNaPozadavek::text(<<<TEXT
<gc-aktivita id="{$sChybou}">
<gc-popis>Popis aktivity, která měla chybu.</gc-popis>
<gc-zmeny>čárka</gc-zmeny>
</gc-aktivita>
TEXT),
            $odmitnuta    => OdpovedAiNaPozadavek::chyba('AI korekturu odmítla'),
            $sCizimBlokem => OdpovedAiNaPozadavek::text("<gc-aktivita id=\"{$sChybou}\"><gc-nazev>Cizí</gc-nazev></gc-aktivita>"),
            default       => OdpovedAiNaPozadavek::text("<gc-aktivita id=\"{$idAktivity}\"></gc-aktivita>"),
        });

        $nacteni = $this->korektury->nactiOdpovediAi();

        self::assertSame([], $nacteni->chyby);
        $vysledek = $nacteni->vysledekImportu;
        self::assertNotNull($vysledek);
        $navrzene = $this->repository->dejOtevrene([$sChybou, $bezChyb, $odmitnuta, $sCizimBlokem]);
        self::assertSame(StavKorekturyEnum::NAVRZENO, $navrzene[$sChybou]->stav);
        self::assertSame('Popis aktivity, která měla chybu.', $navrzene[$sChybou]->navrhPopis);
        self::assertSame(StavKorekturyEnum::NAVRZENO, $navrzene[$bezChyb]->stav);
        self::assertFalse($navrzene[$bezChyb]->maNavrzeneZmeny());

        foreach ([
            $odmitnuta    => 'Odmítnutá',
            $sCizimBlokem => 'S cizím blokem',
        ] as $idAktivity => $nazev) {
            self::assertSame(StavKorekturyEnum::EXPORTOVANO, $navrzene[$idAktivity]->stav, $nazev);
            self::assertNull($navrzene[$idAktivity]->idDavkyAi, "{$nazev} se vrátila mezi texty k odeslání");
            self::assertNotEmpty(
                array_filter($vysledek->varovani, static fn (string $varovani) => str_contains($varovani, "„{$nazev}“")),
                "Varování u aktivity {$nazev}: " . implode(' | ', $vysledek->varovani),
            );
        }
        self::assertSame([], $this->repository->dejDavkyAi(), 'Na načtenou dávku už nic nečeká');
        self::assertNull($this->korektury->nactiOdpovediAi()->vysledekImportu, 'Načtená dávka se znovu nenačítá');

        self::assertSame(2, $this->korektury->posliAi(null));
        self::assertSame(
            [$odmitnuta, $sCizimBlokem],
            array_keys($this->davkyAi->zpravyPodleAktivit($this->davkyAi->posledniDavka())),
        );
    }

    /**
     * @test
     */
    public function zrusenyBalikZrusiDavkuAiKteraBezNejNemaCoDelat(): void
    {
        $this->vytvorAktivitu('Ve zrušeném balíku');
        $balik = $this->korektury->exportuj(1000, \Uzivatel::SYSTEM)[0];
        $this->korektury->posliAi($balik);

        $this->korektury->zrusBalik($balik);

        self::assertSame([$this->davkyAi->posledniDavka()], $this->davkyAi->zrusene);
        self::assertSame([], $this->repository->dejDavkyAi());
    }

    /**
     * @test
     */
    public function chybaJedneDavkyNezdrziOdpovediOstatnichANeznamaDavkaPustiTextyZnovu(): void
    {
        [$vRozbite, $rozbita, $hotova] = $this->dveDavky();
        $this->davkyAi->selze($rozbita, new ChybaApiAi('API AI neposlalo platný JSON', 200));
        $this->davkyAi->dokonci($hotova, static fn (int $idAktivity) => OdpovedAiNaPozadavek::text(
            "<gc-aktivita id=\"{$idAktivity}\"></gc-aktivita>",
        ));

        $nacteni = $this->korektury->nactiOdpovediAi();

        self::assertSame(1, $nacteni->vysledekImportu?->pocetNavrhu, 'Hotová dávka se načte i přes chybu té druhé');
        self::assertCount(1, $nacteni->chyby);
        self::assertStringContainsString($rozbita, $nacteni->chyby[0]);
        self::assertSame($rozbita, $this->repository->dejOtevrene([$vRozbite])[$vRozbite]->idDavkyAi, 'Na rozbitou dávku se čeká dál');

        $this->davkyAi->selze($rozbita, new ChybaApiAi('API AI vrátilo chybu 404: batch not found', 404));

        $nacteni = $this->korektury->nactiOdpovediAi();

        self::assertNull($nacteni->vysledekImportu);
        self::assertCount(1, $nacteni->chyby);
        self::assertNull(
            $this->repository->dejOtevrene([$vRozbite])[$vRozbite]->idDavkyAi,
            'Na dávku, kterou API nezná, se už nečeká',
        );
        self::assertSame(1, $this->korektury->posliAi(null), 'Její text jde poslat znovu');
    }

    /**
     * @test
     */
    public function priVypadkuApiSeNaDalsiDavkyNepta(): void
    {
        [, $prvni] = $this->dveDavky();
        $this->davkyAi->selze($prvni, new ChybaApiAi('API AI vrátilo chybu 529: Overloaded', 529));

        $nacteni = $this->korektury->nactiOdpovediAi();

        self::assertSame([$prvni], $this->davkyAi->dotazy, 'Každý další dotaz by korektora nechal čekat na stejnou chybu');
        self::assertCount(1, $nacteni->chyby);
        self::assertCount(2, $this->repository->dejDavkyAi(), 'Obě dávky se čekají dál');
    }

    /**
     * A batch ID means something to its provider only: after the developer switches the provider, the batch sent
     * before is read with the former provider's token, not asked at the new provider's API.
     *
     * @test
     */
    public function poPrepnutiPoskytovateleSeDavkaPredchozihoNacteJehoTokenem(): void
    {
        $idAktivity = $this->vytvorAktivitu('Odeslaná před přepnutím');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->posliAi(null);
        $davkaClaude = $this->davkyAi->posledniDavka();
        $davkyOpenAi = new FalesneDavkyAi(PoskytovatelAi::OPENAI);
        $korektury = new KorekturyTextu(
            $this->systemoveNastaveni,
            $this->repository,
            $davkyOpenAi,
            fn (PoskytovatelAi $poskytovatel) => $poskytovatel === PoskytovatelAi::ANTHROPIC
                ? $this->davkyAi
                : null,
        );

        self::assertSame([], $korektury->nactiOdpovediAi()->chyby, 'Běžící dávka Claude se čeká dál');
        self::assertSame([$davkaClaude], $this->davkyAi->dotazy, 'Ptá se tokenem Claude');
        self::assertSame([], $davkyOpenAi->dotazy, 'API OpenAI se na dávku Claude neptá');

        $this->davkyAi->dokonci($davkaClaude, static fn (int $idAktivity) => OdpovedAiNaPozadavek::text(
            "<gc-aktivita id=\"{$idAktivity}\"><gc-popis>Návrh od Claude.</gc-popis></gc-aktivita>",
        ));

        self::assertSame(1, $korektury->nactiOdpovediAi()->vysledekImportu?->pocetNavrhu, 'Zaplacené odpovědi nepropadnou');
        self::assertSame('Návrh od Claude.', $this->repository->dejOtevrene([$idAktivity])[$idAktivity]->navrhPopis);
        self::assertSame([], $this->repository->dejDavkyAi());
    }

    /**
     * @test
     */
    public function davkaPoskytovateleBezTokenuSeUvolniATextyJdouPoslatZnovu(): void
    {
        $idAktivity = $this->vytvorAktivitu('Odeslaná před smazáním tokenu');
        $balik = $this->korektury->exportuj(1000, \Uzivatel::SYSTEM)[0];
        $this->korektury->posliAi(null);
        $davkyOpenAi = new FalesneDavkyAi(PoskytovatelAi::OPENAI);
        $korektury = new KorekturyTextu($this->systemoveNastaveni, $this->repository, $davkyOpenAi, static fn () => null);

        $nacteni = $korektury->nactiOdpovediAi();

        self::assertSame([], $davkyOpenAi->dotazy, 'Na dávku Claude se API OpenAI neptá');
        self::assertSame([], $davkyOpenAi->zrusene, 'Ani ji nezkouší zrušit');
        self::assertCount(1, $nacteni->chyby);
        self::assertStringContainsString(PoskytovatelAi::ANTHROPIC->nazev(), $nacteni->chyby[0]);
        self::assertNull($this->repository->dejOtevrene([$idAktivity])[$idAktivity]->idDavkyAi);

        self::assertSame(1, $korektury->posliAi(null), 'Text jde poslat novému poskytovateli');
        $davky = $this->repository->dejDavkyAi($balik);
        self::assertCount(1, $davky);
        self::assertSame($davkyOpenAi->posledniDavka(), $davky[0]->id);
        self::assertSame(PoskytovatelAi::OPENAI->value, $davky[0]->poskytovatel);
        self::assertTrue($davky[0]->zTetoDatabaze);
    }

    /**
     * A copy of the database (ostrá → beta) brings the rows waiting for ostrá's batch along. Beta with a token
     * of the same account must not cancel that batch, ostrá waits for its answers.
     *
     * @test
     */
    public function davkuOdeslanouZJineDatabazeKopieNerusiAniSeNaNiNepta(): void
    {
        $this->vytvorAktivitu('Z kopie, ve zrušeném balíku');
        $zruseny = $this->korektury->exportuj(1000, \Uzivatel::SYSTEM)[0];
        $this->korektury->posliAi($zruseny);
        $cekajici = $this->vytvorAktivitu('Z kopie, čekající');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->posliAi(null);
        self::assertCount(2, $this->davkyAi->odeslane);
        // what a copy of the database looks like: the batches were sent from the original
        dbQuery("UPDATE akce_korektura SET prostredi_ai = 'ostra_original' WHERE id_davky_ai IS NOT NULL");

        $this->korektury->zrusBalik($zruseny);

        self::assertSame([], $this->davkyAi->zrusene, 'Zrušení balíku v kopii dávku originálu nezruší');

        $nacteni = $this->korektury->nactiOdpovediAi();

        self::assertSame([], $this->davkyAi->dotazy, 'Na dávku originálu se kopie neptá');
        self::assertSame([], $this->davkyAi->zrusene);
        self::assertCount(1, $nacteni->chyby);
        self::assertSame([], $this->repository->dejDavkyAi(), 'Kopie na dávky originálu nečeká');
        self::assertNull($this->repository->dejOtevrene([$cekajici])[$cekajici]->idDavkyAi, 'Text jde z kopie poslat znovu');
    }

    /**
     * A later proposal replaces an earlier one, whichever way each came.
     *
     * @test
     */
    public function odpovedApiPrepiseDrivejsiNavrhZChatu(): void
    {
        $idAktivity = $this->vytvorAktivitu('Z chatu i přes API');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->posliAi(null);
        $idDavky = $this->davkyAi->posledniDavka();
        $this->korektury->importuj("<gc-aktivita id=\"{$idAktivity}\"><gc-popis>Návrh z chatu.</gc-popis></gc-aktivita>");
        $this->davkyAi->dokonci($idDavky, static fn (int $idAktivity) => OdpovedAiNaPozadavek::text(
            "<gc-aktivita id=\"{$idAktivity}\"><gc-popis>Návrh z API.</gc-popis></gc-aktivita>",
        ));

        self::assertSame(1, $this->korektury->nactiOdpovediAi()->vysledekImportu?->pocetNavrhu);

        $korektura = $this->repository->dejOtevrene([$idAktivity])[$idAktivity];
        self::assertSame('Návrh z API.', $korektura->navrhPopis);
        self::assertNull($korektura->idDavkyAi);
        self::assertSame([], $this->davkyAi->zrusene);
    }

    /**
     * @test
     */
    public function davkaAiJejichzTextyKorektorMezitimVyridilSeZrusi(): void
    {
        $idAktivity = $this->vytvorAktivitu('Vyřízená z chatu');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->posliAi(null);
        $idDavky = $this->davkyAi->posledniDavka();
        $this->korektury->importuj("<gc-aktivita id=\"{$idAktivity}\"><gc-popis>Návrh z chatu.</gc-popis></gc-aktivita>");
        $this->korektury->zamitni($this->repository->dejOtevrene([$idAktivity])[$idAktivity]->id, \Uzivatel::SYSTEM);

        $nacteni = $this->korektury->nactiOdpovediAi();

        self::assertNull($nacteni->vysledekImportu);
        self::assertSame([$idDavky], $this->davkyAi->zrusene);
        self::assertSame([], $this->davkyAi->dotazy, 'Na odpověď, kterou nic nečeká, se neptá');
        self::assertSame([], $this->repository->dejDavkyAi());
    }

    /**
     * The lock lives as long as the connection to the database, which is persistent: one left behind would hold up
     * every sending of the same connection.
     *
     * @test
     */
    public function odesilaniAiBeziVZamkuKteryPakUvolniIPoChybe(): void
    {
        $zamek = "CONCAT(DATABASE(), '.korektury-odesilani-ai')";

        self::assertNotNull($this->repository->vZamkuOdesilaniAi(static fn () => dbOneCol("SELECT IS_USED_LOCK({$zamek})")));
        try {
            $this->repository->vZamkuOdesilaniAi(static fn () => throw new ChybaApiAi('API AI neodpovídá'));
        } catch (ChybaApiAi) {
        }
        self::assertSame(1, (int) dbOneCol("SELECT IS_FREE_LOCK({$zamek})"));
    }

    /**
     * A double click on Připrav balíky must not give an activity two open rows.
     *
     * @test
     */
    public function exportBeziVZamku(): void
    {
        $zamek = "CONCAT(DATABASE(), '.korektury-export')";
        $this->vytvorAktivitu('Exportovaná v zámku');

        $drzitelZamku = null;
        $repository = new class($drzitelZamku, $zamek) extends KorekturyTextuRepository {
            public function __construct(
                private mixed &$drzitelZamku,
                private readonly string $zamek,
            ) {
            }

            public function vytvorBaliky(array $textyPodleAktivit, int $velikostBaliku, int $exportoval): array
            {
                $this->drzitelZamku = dbOneCol("SELECT IS_USED_LOCK({$this->zamek})");

                return parent::vytvorBaliky($textyPodleAktivit, $velikostBaliku, $exportoval);
            }
        };

        self::assertCount(1, (new KorekturyTextu($this->systemoveNastaveni, $repository))->exportuj(1000, \Uzivatel::SYSTEM));
        self::assertNotNull($drzitelZamku, 'Balíky vznikají v zámku');
        self::assertSame(1, (int) dbOneCol("SELECT IS_FREE_LOCK({$zamek})"), 'Po exportu se zámek uvolní');
    }

    /**
     * Two packages, each sent to the AI in a batch of its own.
     *
     * @return array{0: int, 1: string, 2: string} the activity of the first batch, the first batch, the second batch
     */
    private function dveDavky(): array
    {
        $vPrvni = $this->vytvorAktivitu('V první dávce');
        $this->korektury->posliAi($this->korektury->exportuj(1000, \Uzivatel::SYSTEM)[0]);
        $prvni = $this->davkyAi->posledniDavka();
        $this->vytvorAktivitu('V druhé dávce');
        $this->korektury->posliAi($this->korektury->exportuj(1000, \Uzivatel::SYSTEM)[0]);

        return [$vPrvni, $prvni, $this->davkyAi->posledniDavka()];
    }

    private function vytvorAktivitu(
        string $nazev,
        ?int $rok = null,
        bool $probehlaKorekce = false,
        int $typ = TypAktivity::LARP,
        ?string $popis = null,
        ?int $patriPod = null,
    ): int {
        dbInsert('akce_seznam', [
            'nazev_akce'       => $nazev,
            'popis_kratky'     => "Krátký popis aktivity {$nazev}",
            'popis'            => $popis ?? "Popis aktivity {$nazev}.",
            'probehla_korekce' => (int) $probehlaKorekce,
            'typ'              => $typ,
            'stav'             => StavAktivity::NOVA,
            'rok'              => $rok ?? $this->systemoveNastaveni->rocnik(),
            'patri_pod'        => $patriPod,
        ]);

        return (int) dbInsertId();
    }

    /**
     * @return array{0: int, 1: int} the main activity and another instance
     */
    private function vytvorRodinuInstanci(string $nazev): array
    {
        $hlavni = $this->vytvorAktivitu($nazev);
        dbInsert('akce_instance', [
            'id_hlavni_akce' => $hlavni,
        ]);
        $idInstance = (int) dbInsertId();
        dbQuery('UPDATE akce_seznam SET patri_pod = $0 WHERE id_akce = $1', [$idInstance, $hlavni]);

        return [$hlavni, $this->vytvorAktivitu($nazev, patriPod: $idInstance)];
    }
}
