<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

/**
 * Veřejný katalog kostýmů přes skutečný Apache: zveřejnění, vyřazené kostýmy, kanonické adresy a stránka
 * pro přihlášené.
 */
class KatalogKostymuNaWebuTest extends AbstractTestWeb
{
    // Apache musí vidět zveřejnění katalogu, které test mění, takže bez transakce testovací metody.
    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    /**
     * @return array<int, string|array{0: string, 1: array<int, mixed>}>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return array_merge(
            parent::getSetUpBeforeClassInitQueries(),
            [
                <<<'SQL'
INSERT INTO kostym (id, nazev, popis, velikost, miry, strih, kategorie, stav, interni_poznamka)
VALUES (901, 'Elfí plášť', 'Zelený', 'm', '', 'unisex', 'Fantasy', 'k_dispozici', 'skříň 3'),
       (902, 'Rytířská zbroj', '', 'l', '', 'pansky', 'Historie', 'v_oprave', ''),
       (903, 'Roztrhaný kabát', '', 'xl', '', 'pansky', 'Historie', 'vyrazeny', '')
SQL,
            ],
        );
    }

    private static function zverejniKatalog(bool $zverejnit): void
    {
        dbQuery(
            "UPDATE systemove_nastaveni SET hodnota = $0 WHERE klic = 'KOSTYMY_KATALOG_ZVEREJNEN'",
            [$zverejnit ? '1' : '0'],
        );
    }

    protected function tearDown(): void
    {
        self::zverejniKatalog(false);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function nezverejnenyKatalogNeexistuje(): void
    {
        self::zverejniKatalog(false);

        self::assertSame(404, $this->stahniVerejnouStranku('web/kostymy')['status']);
        self::assertSame(404, $this->stahniVerejnouStranku('web/kostymy/901')['status']);
    }

    /**
     * @test
     */
    public function zverejnenyKatalogUkazeKostymyKromeVyrazenych(): void
    {
        self::zverejniKatalog(true);

        $odpoved = $this->stahniVerejnouStranku('web/kostymy?velikost=&strih=&kategorie=');

        self::assertSame(200, $odpoved['status']);
        self::assertStringContainsString('Elfí plášť', $odpoved['telo']);
        self::assertStringContainsString('Rytířská zbroj', $odpoved['telo']);
        self::assertStringContainsString('teď v opravě', $odpoved['telo']);
        self::assertStringNotContainsString('Roztrhaný kabát', $odpoved['telo']);
        self::assertStringNotContainsString('skříň 3', $odpoved['telo'], 'Interní poznámka na web nepatří');
        self::assertStringEndsWith('/web/kostymy', $this->kanonickaAdresa($odpoved['telo']), 'Filtr neurčuje jinou stránku');
    }

    /**
     * @test
     */
    public function filtrPodleStrihu(): void
    {
        self::zverejniKatalog(true);

        $telo = $this->stahniVerejnouStranku('web/kostymy?strih=pansky')['telo'];

        self::assertStringContainsString('Rytířská zbroj', $telo);
        self::assertStringNotContainsString('Elfí plášť', $telo);
    }

    /**
     * @test
     */
    public function detailKostymuMaVlastniKanonickouAdresu(): void
    {
        self::zverejniKatalog(true);

        $odpoved = $this->stahniVerejnouStranku('web/kostymy/901?aktivita=5');

        self::assertSame(200, $odpoved['status']);
        self::assertStringContainsString('Zelený', $odpoved['telo']);
        self::assertStringContainsString('po <a href="prihlaseni">přihlášení</a>', $odpoved['telo']);
        self::assertStringEndsWith('/web/kostymy/901', $this->kanonickaAdresa($odpoved['telo']));
        self::assertSame(404, $this->stahniVerejnouStranku('web/kostymy/903')['status'], 'Vyřazený kostým na webu není');
        self::assertSame(404, $this->stahniVerejnouStranku('web/kostymy/nic')['status']);
    }

    /**
     * @test
     */
    public function vlastniRezervaceJsouJenProPrihlasene(): void
    {
        self::zverejniKatalog(true);

        self::assertSame(401, $this->stahniVerejnouStranku('web/kostymy/moje')['status']);
    }

    private function kanonickaAdresa(string $telo): string
    {
        self::assertSame(1, preg_match('~<link rel="canonical" href="([^"]+)">~', $telo, $shoda), 'Stránka nemá kanonickou adresu');

        return html_entity_decode($shoda[1]);
    }
}
