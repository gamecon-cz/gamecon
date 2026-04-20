<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly;

use Gamecon\Kanaly\MailLogger;
use PHPUnit\Framework\TestCase;

class MailLoggerTest extends TestCase
{
    private string $cestaSqlite;

    protected function setUp(): void
    {
        $this->cestaSqlite = LOGY . '/maily-test-' . uniqid('', true) . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->cestaSqlite)) {
            unlink($this->cestaSqlite);
        }
    }

    /**
     * @test
     */
    public function zalogujeZaznamABezchyby(): void
    {
        $logger = new MailLogger($this->cestaSqlite);

        $logger->zalogujOdeslani(
            predmet: 'Test',
            format: 'html',
            adresati: ['kdo@example.com'],
            pocetPriloh: 2,
            telo: 'telo emailu',
        );

        self::assertSame(1, $logger->spocitej());
        $zaznamy = $logger->najdi();
        self::assertCount(1, $zaznamy);
        self::assertSame('Test', $zaznamy[0]['predmet']);
        self::assertSame(2, (int) $zaznamy[0]['prilohy_count']);
        self::assertSame('["kdo@example.com"]', $zaznamy[0]['adresati']);
        self::assertNull($zaznamy[0]['chyba']);
    }

    /**
     * @test
     */
    public function filtrujePodleCastiPredmetuBezOhleduNaVelikostPismen(): void
    {
        $logger = new MailLogger($this->cestaSqlite);
        $logger->zalogujOdeslani('Pozvánka na GameCon', 'html', ['a@b.cz'], 0, '');
        $logger->zalogujOdeslani('Faktura #42', 'html', ['c@d.cz'], 0, '');
        $logger->zalogujOdeslani('POZVÁNKA extra', 'html', ['e@f.cz'], 0, '');

        self::assertSame(2, $logger->spocitej('pozvánka'));
        $nalezene = array_column($logger->najdi(filtr: 'POZVÁNKA'), 'predmet');
        sort($nalezene);
        self::assertSame(['POZVÁNKA extra', 'Pozvánka na GameCon'], $nalezene);
    }

    /**
     * @test
     */
    public function filtrujePodleEmailuAdresata(): void
    {
        $logger = new MailLogger($this->cestaSqlite);
        $logger->zalogujOdeslani('Prvni', 'html', ['ucastnik@gamecon.cz'], 0, '');
        $logger->zalogujOdeslani('Druhy', 'html', ['nekdo@example.com'], 0, '');
        $logger->zalogujOdeslani('Treti', 'html', ['ORG@GAMECON.CZ', 'a@b.cz'], 0, '');

        self::assertSame(2, $logger->spocitej('gamecon.cz'));
        $predmety = array_column($logger->najdi(filtr: 'gamecon.cz'), 'predmet');
        sort($predmety);
        self::assertSame(['Prvni', 'Treti'], $predmety);
    }

    /**
     * @test
     */
    public function razeniPodlePoctuPrilohAKdy(): void
    {
        $logger = new MailLogger($this->cestaSqlite);
        $logger->zalogujOdeslani('A', 'html', [], 3, '');
        $logger->zalogujOdeslani('B', 'html', [], 1, '');
        $logger->zalogujOdeslani('C', 'html', [], 5, '');

        $razeniPriloh = array_column(
            $logger->najdi(razeniSloupec: 'prilohy_count', razeniSmer: 'DESC'),
            'predmet',
        );
        self::assertSame(['C', 'A', 'B'], $razeniPriloh);

        $razeniPrilohAsc = array_column(
            $logger->najdi(razeniSloupec: 'prilohy_count', razeniSmer: 'ASC'),
            'predmet',
        );
        self::assertSame(['B', 'A', 'C'], $razeniPrilohAsc);
    }

    /**
     * @test
     */
    public function paginaceOmezujeVysledky(): void
    {
        $logger = new MailLogger($this->cestaSqlite);
        for ($i = 1; $i <= 5; ++$i) {
            $logger->zalogujOdeslani("Mail {$i}", 'html', [], $i, '');
        }

        $prvniStrana = $logger->najdi(razeniSloupec: 'prilohy_count', razeniSmer: 'ASC', limit: 2, offset: 0);
        $druhaStrana = $logger->najdi(razeniSloupec: 'prilohy_count', razeniSmer: 'ASC', limit: 2, offset: 2);

        self::assertSame(['Mail 1', 'Mail 2'], array_column($prvniStrana, 'predmet'));
        self::assertSame(['Mail 3', 'Mail 4'], array_column($druhaStrana, 'predmet'));
        self::assertSame(5, $logger->spocitej());
    }

    /**
     * @test
     */
    public function detailVraciCeleTeloAChybuNeboNullProNeznameId(): void
    {
        $logger = new MailLogger($this->cestaSqlite);
        $logger->zalogujOdeslani('Detail test', 'html', ['x@y.cz'], 1, 'plné tělo e-mailu', null);

        $zaznamy = $logger->najdi();
        $id = (int) $zaznamy[0]['id'];

        $detail = $logger->detail($id);
        self::assertNotNull($detail);
        self::assertSame('Detail test', $detail['predmet']);
        self::assertSame('plné tělo e-mailu', $detail['telo']);
        self::assertNull($detail['chyba']);

        self::assertNull($logger->detail(999999));
    }

    /**
     * @test
     */
    public function zaznamuSChybouMaVyplnenouChybu(): void
    {
        $logger = new MailLogger($this->cestaSqlite);
        $logger->zalogujOdeslani('Nepodařilo se', 'html', ['x@y.cz'], 0, 'telo', 'SMTP server nereaguje');

        $zaznam = $logger->najdi()[0];
        self::assertSame('SMTP server nereaguje', $zaznam['chyba']);
    }
}
