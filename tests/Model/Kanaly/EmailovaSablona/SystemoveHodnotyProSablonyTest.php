<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Kanaly\EmailovaSablona\StavSystemoveHodnotyEnum;
use Gamecon\Kanaly\EmailovaSablona\SystemoveHodnotyProSablony;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;
use Gamecon\Tests\Db\AbstractTestDb;

class SystemoveHodnotyProSablonyTest extends AbstractTestDb
{
    use CerstveSystemoveNastaveniTrait;

    /**
     * @test
     *
     * @dataProvider stavyHodnot
     */
    public function stavHodnotyPodleToho(string $nazev, StavSystemoveHodnotyEnum $ocekavanyStav): void
    {
        self::assertSame($ocekavanyStav, $this->hodnoty()->stav($nazev));
    }

    public static function stavyHodnot(): array
    {
        return [
            'veřejné nastavení'              => ['ROCNIK', StavSystemoveHodnotyEnum::POVOLENA],
            'neveřejné nastavení'            => [SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA, StavSystemoveHodnotyEnum::NEVEREJNA],
            'neexistující nastavení'         => ['NENI_TAKOVE_NASTAVENI', StavSystemoveHodnotyEnum::NEZNAMA],
            'povolená konstanta'             => ['UCET_CZ', StavSystemoveHodnotyEnum::POVOLENA],
            'tajná konstanta mimo nastavení' => ['DB_PASS', StavSystemoveHodnotyEnum::NEZNAMA],
        ];
    }

    /**
     * @test
     */
    public function neverejnouHodnotuNikdyNevrati(): void
    {
        self::assertNull($this->hodnoty()->hodnota(SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA));
        self::assertNull($this->hodnoty()->hodnota('DB_PASS'));
        self::assertNull($this->hodnoty()->hodnota('NENI_TAKOVE_NASTAVENI'));
    }

    /**
     * @test
     */
    public function datumSeVraciVeStandardnimCeskemTvaru(): void
    {
        self::assertSame(
            (new DateTimeCz(GC_BEZI_OD))->formatCasNaMinutyStandard(),
            $this->hodnoty()->hodnota('GC_BEZI_OD'),
        );
    }

    /**
     * @test
     */
    public function cisloSeVraciBezUpravy(): void
    {
        self::assertSame((string) ROCNIK, $this->hodnoty()->hodnota('ROCNIK'));
    }

    /**
     * @test
     */
    public function konstantaZKonfiguraceSeVraciHodnotou(): void
    {
        self::assertSame(UCET_CZ, $this->hodnoty()->hodnota('UCET_CZ'));
    }

    /**
     * @test
     */
    public function editorNabidneJenVerejneHodnotyAbecedne(): void
    {
        $nabizene = $this->hodnoty()->povoleneProEditor();

        self::assertArrayHasKey('ROCNIK', $nabizene);
        self::assertArrayHasKey('GC_BEZI_OD', $nabizene);
        self::assertArrayHasKey('UCET_CZ', $nabizene);
        self::assertArrayNotHasKey(SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA, $nabizene);
        self::assertArrayNotHasKey('DB_PASS', $nabizene);
        $klice = array_keys($nabizene);
        $serazene = $klice;
        sort($serazene);
        self::assertSame($serazene, $klice);
        self::assertSame('Ročník', $nabizene['ROCNIK']['popis']);
        self::assertSame((string) ROCNIK, $nabizene['ROCNIK']['ukazka']);
    }

    /**
     * @test
     */
    public function neverejneKlicePatriVsechnoCoNeniVerejne(): void
    {
        $neverejne = $this->hodnoty()->neverejneKlice();

        self::assertContains(SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA, $neverejne);
        self::assertNotContains('ROCNIK', $neverejne);
    }

    /**
     * @test
     */
    public function poZverejneniSeHodnotaZacneVracet(): void
    {
        $klic = SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA;
        $this->cerstveSystemoveNastaveni()->ulozZmenuPriznakuVerejne(true, $klic, \Uzivatel::zId(\Uzivatel::SYSTEM));

        $hodnoty = new SystemoveHodnotyProSablony($this->cerstveSystemoveNastaveni());

        self::assertSame(StavSystemoveHodnotyEnum::POVOLENA, $hodnoty->stav($klic));
        self::assertNotNull($hodnoty->hodnota($klic));
        self::assertArrayHasKey($klic, $hodnoty->povoleneProEditor());
    }

    private function hodnoty(): SystemoveHodnotyProSablony
    {
        return new SystemoveHodnotyProSablony(SystemoveNastaveni::zGlobals());
    }
}
