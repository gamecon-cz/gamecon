<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaKontrola;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaRepository;
use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\Kanaly\EmailovaSablona\ProblemySablonHtml;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\UpominkaVlastniZneni;

class EmailovaSablonaKontrolaTest extends AbstractTestDb
{
    use CerstveSystemoveNastaveniTrait;

    private const string NEVEREJNY_KLIC = SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA;

    /**
     * @test
     */
    public function vychoziSablonyNemajiZadnyProblem(): void
    {
        self::assertSame([], $this->kontrola()->najdiProblemy());
    }

    /**
     * @test
     */
    public function najdeSablonuObsahujiciNeverejneNastaveniVPredmetuINaTextu(): void
    {
        $repository = new EmailovaSablonaRepository();
        $repository->uloz(KodEmailoveSablonyEnum::OBNOVA_HESLA, 'Hranice %' . self::NEVEREJNY_KLIC . '%', 'Text %odkaz%', \Uzivatel::SYSTEM);
        $repository->uloz(KodEmailoveSablonyEnum::RYCHLA_REGISTRACE, 'Předmět', 'Hranice %' . self::NEVEREJNY_KLIC . '%: %odkaz_k_potvrzeni%', \Uzivatel::SYSTEM);

        $problemy = $this->kontrola()->najdiProblemy();

        self::assertSame(
            [KodEmailoveSablonyEnum::OBNOVA_HESLA, KodEmailoveSablonyEnum::RYCHLA_REGISTRACE],
            array_map(static fn ($problem) => $problem->kod, $problemy),
        );
        self::assertSame('web/emailove-sablony?kod=obnova_hesla', $problemy[0]->odkaz);
        self::assertSame('Obnova hesla', $problemy[0]->nazev);
        self::assertStringContainsString(self::NEVEREJNY_KLIC, $problemy[0]->chyby[0]);
    }

    /**
     * @test
     */
    public function problemSePrestaHlasitPoZverejneniNastaveni(): void
    {
        (new EmailovaSablonaRepository())->uloz(KodEmailoveSablonyEnum::OBNOVA_HESLA, 'Předmět', 'Hranice %' . self::NEVEREJNY_KLIC . '% %odkaz%', \Uzivatel::SYSTEM);
        self::assertCount(1, $this->kontrola()->najdiProblemy());

        $this->cerstveSystemoveNastaveni()->ulozZmenuPriznakuVerejne(true, self::NEVEREJNY_KLIC, \Uzivatel::zId(\Uzivatel::SYSTEM));

        self::assertSame([], $this->kontrola()->najdiProblemy());
    }

    /**
     * @test
     */
    public function najdeIVlastniZneniUpominkyProLetosniRocnik(): void
    {
        (new UpominkaVlastniZneni())->uloz(
            ROCNIK,
            'Nedoplatek',
            'Dluh %dluh%, tajemství %' . self::NEVEREJNY_KLIC . '%',
            \Uzivatel::SYSTEM,
        );

        $problemy = $this->kontrola()->najdiProblemy();

        self::assertCount(1, $problemy);
        self::assertSame(KodEmailoveSablonyEnum::UPOMINKA_VLASTNI, $problemy[0]->kod);
        self::assertSame('finance/upominky-dluzniku?zneni', $problemy[0]->odkaz);
    }

    /**
     * @test
     */
    public function neznamaVelkaHodnotaSeHlasiTaky(): void
    {
        (new EmailovaSablonaRepository())->uloz(KodEmailoveSablonyEnum::OBNOVA_HESLA, 'Předmět', 'Text %NENI_TAKOVE_NASTAVENI% %odkaz%', \Uzivatel::SYSTEM);

        $problemy = $this->kontrola()->najdiProblemy();

        self::assertCount(1, $problemy);
        self::assertStringContainsString('není známá systémová hodnota', $problemy[0]->chyby[0]);
    }

    /**
     * @test
     */
    public function chybejiciSablonaSeHlasiJakoTaKterouNelzeOdeslat(): void
    {
        dbQuery("DELETE FROM emailova_sablona WHERE kod = 'obnova_hesla'");

        $problemy = $this->kontrola()->najdiProblemy();

        self::assertSame([KodEmailoveSablonyEnum::OBNOVA_HESLA], array_map(static fn ($problem) => $problem->kod, $problemy));
        self::assertTrue($problemy[0]->zabranujeOdeslani);
        self::assertStringContainsString('E-mail se neodešle', ProblemySablonHtml::vykresli($problemy));
    }

    /**
     * @test
     */
    public function bezProblemuSeNicNevykresli(): void
    {
        self::assertSame('', ProblemySablonHtml::vykresli([]));
    }

    /**
     * @test
     */
    public function vykreslenyBlokMaOdkazNaEditaciAEscapujeNazev(): void
    {
        (new EmailovaSablonaRepository())->uloz(KodEmailoveSablonyEnum::OBNOVA_HESLA, 'Předmět', 'Text %' . self::NEVEREJNY_KLIC . '% %odkaz%', \Uzivatel::SYSTEM);

        $html = ProblemySablonHtml::vykresli($this->kontrola()->najdiProblemy());

        self::assertStringContainsString('<a href="web/emailove-sablony?kod=obnova_hesla">Obnova hesla</a>', $html);
        self::assertStringContainsString('id="problemySablon"', $html);
        self::assertStringContainsString('používá neveřejné nastavení', $html);
        self::assertStringContainsString('zaškrtnutím „Veřejné“ na stránce Nastavení', $html);
    }

    private function kontrola(): EmailovaSablonaKontrola
    {
        return EmailovaSablonaKontrola::zNastaveni($this->cerstveSystemoveNastaveni());
    }
}
