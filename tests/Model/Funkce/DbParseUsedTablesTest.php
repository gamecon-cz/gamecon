<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Funkce;

use PHPUnit\Framework\TestCase;

/**
 * Testy parseru tabulek z SQL/definic view. Parser plní mapování závislostí
 * view → tabulky (`_tables_used_in_view_data_versions`), na kterém stojí
 * invalidace SQL cache. Když parser tabulku vynechá, cache dotazu nad daným
 * view se po změně té tabulky neinvaliduje (to způsobovalo, že odebrání role
 * nesmazalo slevu „aktivita zdarma“ – právo se četlo ze zastaralé cache).
 */
class DbParseUsedTablesTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider dotazyAOcekavaneTabulky
     *
     * @param array<string> $ocekavane
     */
    public function vratiPouziteTabulky(string $dotaz, array $ocekavane): void
    {
        $vysledek = array_values(dbParseUsedTables($dotaz));
        sort($vysledek);
        sort($ocekavane);
        self::assertSame($ocekavane, $vysledek);
    }

    /**
     * @return array<string, array{0: string, 1: array<string>}>
     */
    public static function dotazyAOcekavaneTabulky(): array
    {
        return [
            'prostý FROM' => [
                'SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele = 1',
                ['uzivatele_hodnoty'],
            ],
            'FROM + JOIN' => [
                'SELECT prava_role.id_prava FROM platne_role_uzivatelu LEFT JOIN prava_role USING(id_role)',
                ['platne_role_uzivatelu', 'prava_role'],
            ],
            'INSERT INTO se sloupci' => [
                'INSERT INTO uzivatele_role(id_uzivatele, id_role) VALUES (1, 2)',
                ['uzivatele_role'],
            ],
            // MariaDB ukládá join ve view jako `from (... join ...)` – první
            // tabulka je hned za otevírací závorkou a nesmí se vynechat.
            'definice view: FROM (závorkovaný JOIN)' => [
                'select 1 from (`uzivatele_role` join `platne_role` on(`uzivatele_role`.`id_role` = `platne_role`.`id_role`))',
                ['uzivatele_role', 'platne_role'],
            ],
            // information_schema vrací názvy kvalifikované jménem databáze –
            // chceme název tabulky, ne `gamecon`.
            'definice view: db-kvalifikované názvy' => [
                'select 1 from (`gamecon`.`uzivatele_role` join `gamecon`.`platne_role` on(1))',
                ['uzivatele_role', 'platne_role'],
            ],
            // Odvozená tabulka: za `FROM (` je klíčové slovo SELECT, ne tabulka.
            'odvozená tabulka FROM (SELECT …)' => [
                'SELECT * FROM (SELECT id FROM uzivatele_hodnoty) AS t JOIN role_seznam ON 1',
                ['uzivatele_hodnoty', 'role_seznam'],
            ],
        ];
    }
}
