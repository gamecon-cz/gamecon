<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

/**
 * `dbQuery()` posílá dotaz do `PDO::query()` (vrací výsledek), nebo do `PDO::exec()`
 * (vrací jen počet řádků), a pozná to z prvního slova. Splete-li se směrem k zápisu,
 * výsledek se zahodí a volající spadne na `fetch() on bool` — tedy na místě, které
 * s příčinou nesouvisí. Proto se vyjmenovávají měnící tvary a zbytek je čtení.
 */
class DbQuerySmerDotazuTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function dotazSSpolecnymVyrazemVraciVysledek(): void
    {
        $vysledek = dbQuery('WITH jedna AS (SELECT 1 AS cislo) SELECT cislo FROM jedna');

        self::assertInstanceOf(\PDOStatement::class, $vysledek);
        // Výsledek se musí dočíst celý: spojení je nebufferované, takže otevřený
        // kurzor by shodil další dotaz v témže procesu, včetně úklidu po testu.
        self::assertSame([[
            'cislo' => '1',
        ]], $vysledek->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Helpery volají `fetch*()` rovnou na výsledku `dbQuery()`, takže je špatné
     * zařazení shodí stejně jako přímého volajícího.
     *
     * @test
     */
    public function helperyNadSpolecnymVyrazemProjdou(): void
    {
        $dotaz = 'WITH dve AS (SELECT 1 AS cislo UNION SELECT 2) SELECT cislo FROM dve ORDER BY cislo';

        // Spojení má ATTR_STRINGIFY_FETCHES, takže i čísla přicházejí jako řetězce.
        self::assertSame([[
            'cislo' => '1',
        ], [
            'cislo' => '2',
        ]], dbFetchAll($dotaz));
        self::assertSame('1', dbFetchSingle($dotaz));
    }

    /**
     * Zápis se nesmí přeřadit mezi čtení — `exec()` je pro něj správně a jen odtud
     * se bere počet dotčených řádků.
     *
     * @test
     */
    public function zapisSeStaleChovaJakoZapis(): void
    {
        dbQuery('CREATE TEMPORARY TABLE tmp_dbquery_cte (cislo INT)');

        $vysledek = dbQuery('INSERT INTO tmp_dbquery_cte VALUES (1), (2)');

        self::assertTrue($vysledek);
        self::assertSame(2, dbAffectedOrNumRows($vysledek));
    }

    /**
     * Tvary, které se nedají předem vyjmenovat, musí skončit na čtecí větvi. Ta si
     * poradí i se zápisem, kdežto opačná chyba výsledek zahodí a projeví se až
     * u volajícího.
     *
     * @test
     *
     * @dataProvider ctecíTvary
     */
    public function nerozpoznanyTvarSeChovaJakoCteni(string $dotaz): void
    {
        self::assertInstanceOf(\PDOStatement::class, dbQuery($dotaz));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ctecíTvary(): iterable
    {
        yield 'SELECT' => ['SELECT 1'];
        yield 'WITH' => ['WITH x AS (SELECT 1 AS a) SELECT a FROM x'];
        yield 'závorka' => ['(SELECT 1) UNION (SELECT 2)'];
        yield 'komentář před dotazem' => ["-- poznámka\nSELECT 1"];
        // `TABLE …` sem nepatří: MariaDB 10.11 ho nezná (je až v MySQL 8.0.19).
        yield 'VALUES' => ['VALUES (1), (2)'];
    }
}
