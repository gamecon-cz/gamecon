<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AkceSql;
use Gamecon\Uzivatel\SqlStruktura\MedailonekSqlStruktura as MedailonekSql;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;

/**
 * Hledá, které texty webu odkazují na soubor: stránky, novinky a blog, popisy aktivit (všech ročníků)
 * a medailonky. Hledá cestu vůči kořeni webu (`soubory/obsah/...`), takže najde relativní
 * i absolutní odkazy, i s procentovým kódováním. Šablony a kód neprohledává.
 */
class PouzitiObrazku
{
    private const PREFIX_PRIZNAKU = 'pouziti_';

    /**
     * @param list<array{popis: string, url: string}> $pouziti
     */
    public static function popis(array $pouziti, int $max = 3): string
    {
        $popisy = array_column($pouziti, 'popis');
        $text = implode(', ', array_slice($popisy, 0, $max));
        $zbyva = count($popisy) - $max;
        if ($zbyva > 0) {
            $text .= sprintf(' a %d %s', $zbyva, $zbyva < 5 ? 'další' : 'dalších');
        }

        return $text;
    }

    private static function vzorProLike(string $hodnota): string
    {
        return '%' . addcslashes($hodnota, '\\%_') . '%';
    }

    /**
     * @return list<array{popis: string, url: string}> popis je pro člověka, url vede do administrace
     */
    public function kdeJePouzity(string $absolutniCesta): array
    {
        return $this->kdeJsouPouzity([$absolutniCesta])[$absolutniCesta];
    }

    /**
     * Pro víc souborů najednou: každá tabulka s texty se projde jednou, ne jednou za soubor.
     *
     * @param list<string> $absolutniCesty
     * @return array<string, list<array{popis: string, url: string}>> podle zadané cesty
     */
    public function kdeJsouPouzity(array $absolutniCesty): array
    {
        $pouziti = array_fill_keys($absolutniCesty, []);
        $hledaneCesty = [];
        $vzory = [];
        foreach (array_keys($pouziti) as $absolutniCesta) {
            $cestaVuciWebu = ZpracovaneObrazky::cestaVuciWebu((string) $absolutniCesta);
            if ($cestaVuciWebu === null) {
                continue;
            }
            $zakodovanaCesta = implode('/', array_map('rawurlencode', explode('/', $cestaVuciWebu)));
            $hledaneCesty[] = (string) $absolutniCesta;
            array_push($vzory, self::vzorProLike($cestaVuciWebu), self::vzorProLike($zakodovanaCesta));
        }
        if ($hledaneCesty === []) {
            return $pouziti;
        }

        $radkySVyskyty = [
            [
                $this->najdiRadky('id_stranky, url_stranky', 'stranky', ['obsah'], 'url_stranky', $vzory),
                static fn (array $stranka): array => [
                    'popis' => 'stránka ' . $stranka['url_stranky'],
                    'url'   => 'web/editace-stranek?id=' . $stranka['id_stranky'],
                ],
            ],
            [
                $this->najdiRadky('id, nazev, typ', 'novinky', ['text'], 'vydat DESC', $vzory),
                static fn (array $novinka): array => [
                    'popis' => ((int) $novinka['typ'] === \Novinka::BLOG ? 'blog ' : 'novinka ') . $novinka['nazev'],
                    'url'   => 'web/novinka?id=' . $novinka['id'],
                ],
            ],
            [
                $this->najdiRadky(
                    sprintf('%1$s, %2$s, %3$s', AkceSql::ID_AKCE, AkceSql::NAZEV_AKCE, AkceSql::ROK),
                    AkceSql::AKCE_SEZNAM_TABULKA,
                    [AkceSql::POPIS],
                    sprintf('%1$s DESC, %2$s', AkceSql::ROK, AkceSql::NAZEV_AKCE),
                    $vzory,
                ),
                static fn (array $aktivita): array => [
                    'popis' => sprintf('aktivita %s (%d)', $aktivita[AkceSql::NAZEV_AKCE], $aktivita[AkceSql::ROK]),
                    'url'   => 'aktivity/upravy?aktivitaId=' . $aktivita[AkceSql::ID_AKCE],
                ],
            ],
            [
                $this->najdiRadky(
                    sprintf(
                        '%1$s.%2$s, %3$s.%4$s, %3$s.%5$s, %3$s.%6$s',
                        MedailonekSql::MEDAILONKY_TABULKA,
                        MedailonekSql::ID_UZIVATELE,
                        UzivatelSql::UZIVATELE_HODNOTY_TABULKA,
                        UzivatelSql::LOGIN_UZIVATELE,
                        UzivatelSql::JMENO_UZIVATELE,
                        UzivatelSql::PRIJMENI_UZIVATELE,
                    ),
                    sprintf(
                        '%1$s INNER JOIN %2$s ON %2$s.%3$s = %1$s.%3$s',
                        MedailonekSql::MEDAILONKY_TABULKA,
                        UzivatelSql::UZIVATELE_HODNOTY_TABULKA,
                        MedailonekSql::ID_UZIVATELE,
                    ),
                    [
                        MedailonekSql::MEDAILONKY_TABULKA . '.' . MedailonekSql::O_SOBE,
                        MedailonekSql::MEDAILONKY_TABULKA . '.' . MedailonekSql::DRD,
                    ],
                    MedailonekSql::MEDAILONKY_TABULKA . '.' . MedailonekSql::ID_UZIVATELE,
                    $vzory,
                ),
                static fn (array $medailonek): array => [
                    'popis' => 'medailonek ' . \Uzivatel::jmenoNickZjisti($medailonek),
                    'url'   => 'aktivity/medailonky?id=' . $medailonek[MedailonekSql::ID_UZIVATELE],
                ],
            ],
        ];
        foreach ($radkySVyskyty as [$radky, $popisMista]) {
            foreach ($radky as $radek) {
                foreach ($hledaneCesty as $index => $hledanaCesta) {
                    if ((int) $radek[self::PREFIX_PRIZNAKU . $index] === 1) {
                        $pouziti[$hledanaCesta][] = $popisMista($radek);
                    }
                }
            }
        }

        return $pouziti;
    }

    /**
     * Řádky, jejichž text odkazuje aspoň na jednu z hledaných cest. U každého řádku je příznak
     * `pouziti_<pořadí cesty>`, který vyhodnotila databáze, aby se shoda počítala stejně jako v dotazu;
     * řádky se podle příznaků filtrují přes HAVING, takže se každé LIKE spočítá jednou.
     *
     * @param string $sloupce sloupce řádku k výpisu
     * @param string $zdroj tabulka nebo spojení tabulek
     * @param list<string> $sloupceTextu sloupce, ve kterých se odkazy hledají
     * @param list<string> $vzory dva vzory pro LIKE na každou cestu: obyčejný a zakódovaný pro URL
     * @return list<array<string, string|null>>
     */
    private function najdiRadky(
        string $sloupce,
        string $zdroj,
        array $sloupceTextu,
        string $razeni,
        array $vzory,
    ): array {
        $priznaky = [];
        $podminky = [];
        for ($index = 0, $pocetCest = intdiv(count($vzory), 2); $index < $pocetCest; $index++) {
            $shoda = implode(' OR ', array_map(
                static fn (string $sloupec): string => sprintf(
                    '%1$s LIKE $%2$d OR %1$s LIKE $%3$d',
                    $sloupec,
                    2 * $index,
                    2 * $index + 1,
                ),
                $sloupceTextu,
            ));
            $priznaky[] = sprintf('(%s) AS %s%d', $shoda, self::PREFIX_PRIZNAKU, $index);
            $podminky[] = self::PREFIX_PRIZNAKU . $index;
        }

        return dbFetchAll(
            sprintf(
                <<<'SQL'
SELECT %1$s, %2$s
FROM %3$s
HAVING %4$s
ORDER BY %5$s
SQL,
                $sloupce,
                implode(', ', $priznaky),
                $zdroj,
                implode(' OR ', $podminky),
                $razeni,
            ),
            $vzory,
        );
    }
}
