<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\SystemoveNastaveni;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniHtml;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Model\Kanaly\EmailovaSablona\CerstveSystemoveNastaveniTrait;

class SystemoveNastaveniVerejneTest extends AbstractTestDb
{
    use CerstveSystemoveNastaveniTrait;

    private const string KLIC = 'PODEZRELE_VYSOKA_PLATBA_UCASTNIKA';

    /**
     * @test
     */
    public function nastaveniJeVychoziNeverejne(): void
    {
        self::assertSame('0', $this->zaznam()['verejne']);
    }

    /**
     * @test
     */
    public function zverejneniSeUlozi(): void
    {
        $editujici = \Uzivatel::zId(\Uzivatel::SYSTEM);

        $zmeneno = $this->nastaveni()->ulozZmenuPriznakuVerejne(true, self::KLIC, $editujici);

        self::assertSame(1, $zmeneno);
        self::assertSame('1', $this->zaznam()['verejne']);
    }

    /**
     * @test
     */
    public function zverejneniMenoPouzeTentoPriznakAHodnotuNechaBeze(): void
    {
        $pred = $this->zaznam();

        $this->nastaveni()->ulozZmenuPriznakuVerejne(true, self::KLIC, \Uzivatel::zId(\Uzivatel::SYSTEM));

        $po = $this->zaznam();
        self::assertSame($pred['hodnota'], $po['hodnota']);
        self::assertSame($pred['vlastni'], $po['vlastni']);
    }

    /**
     * @test
     */
    public function zmenaPriznakuSeZalogujeSeZmenenymPriznakemAUzivatelem(): void
    {
        $this->nastaveni()->ulozZmenuPriznakuVerejne(true, self::KLIC, \Uzivatel::zId(\Uzivatel::SYSTEM));

        $log = dbOneLine(<<<SQL
SELECT systemove_nastaveni_log.id_uzivatele, systemove_nastaveni_log.verejne, systemove_nastaveni_log.hodnota, systemove_nastaveni_log.vlastni
FROM systemove_nastaveni_log
JOIN systemove_nastaveni ON systemove_nastaveni.id_nastaveni = systemove_nastaveni_log.id_nastaveni
WHERE systemove_nastaveni.klic = $0
ORDER BY systemove_nastaveni_log.id_nastaveni_log DESC
LIMIT 1
SQL,
            [
                0 => self::KLIC,
            ],
        );

        self::assertSame((string) \Uzivatel::SYSTEM, $log['id_uzivatele']);
        self::assertSame('1', $log['verejne']);
        self::assertNull($log['hodnota'], 'Log nese jen to, co se změnilo');
        self::assertNull($log['vlastni']);
    }

    /**
     * @test
     */
    public function zverejneniLzeVratitZpet(): void
    {
        $editujici = \Uzivatel::zId(\Uzivatel::SYSTEM);
        $this->nastaveni()->ulozZmenuPriznakuVerejne(true, self::KLIC, $editujici);

        $this->nastaveni()->ulozZmenuPriznakuVerejne(false, self::KLIC, $editujici);

        self::assertSame('0', $this->zaznam()['verejne']);
    }

    /**
     * @test
     */
    public function zaznamyProHtmlNesouPriznakVerejneKZaskrtnuti(): void
    {
        $this->nastaveni()->ulozZmenuPriznakuVerejne(true, self::KLIC, \Uzivatel::zId(\Uzivatel::SYSTEM));

        $zaznamy = (new SystemoveNastaveniHtml($this->nastaveni()))->dejZaznamyNastaveniProHtml([self::KLIC, 'KURZ_EURO']);

        self::assertSame('checked', $zaznamy[self::KLIC]['verejneChecked']);
        self::assertSame('', $zaznamy['KURZ_EURO']['verejneChecked']);
    }

    private function nastaveni(): SystemoveNastaveni
    {
        return $this->cerstveSystemoveNastaveni();
    }

    /**
     * @return array<string, mixed>
     */
    private function zaznam(): array
    {
        return $this->nastaveni()->dejVsechnyZaznamyNastaveni()[self::KLIC];
    }
}
