<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AkceSql;
use Gamecon\Uzivatel\SqlStruktura\MedailonekSqlStruktura as MedailonekSql;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;

/**
 * Hledá, které texty webu odkazují na soubor: stránky, novinky a blog, popisy aktivit (všech ročníků)
 * a medailonky. Hledá cestu vůči kořeni webu (`soubory/obsah/...`), takže najde relativní
 * i absolutní odkazy, i s procentovým kódováním. Šablony a kód neprohledává, jen zná
 * @see self::ODKAZY_Z_KODU
 */
class PouzitiObrazku
{
    private const PREFIX_PRIZNAKU = 'pouziti_';
    private const PREFIX_TEXTU = 'text_';

    /**
     * Soubory ve spravovaných složkách, na které odkazuje kód nebo šablona. Seznam je ruční, v kódu se odkazy
     * nehledají; PouzitiObrazkuTest hlídá, že žádný nechybí ani nepřebývá. `{rocnik}` je aktuální ročník.
     */
    public const ODKAZY_Z_KODU = [
        'obsah/download/tiskova_kronika.pdf'                   => 'QR kódy (web/moduly/qrka.php)',
        'obsah/materialy/{rocnik}/souhlas_rodicu_{rocnik}.pdf' => 'přihláška (web/sablony/blackarrow/prihlaska.xtpl)',
        'obsah/materialy/2026/velikosti_mikin.pdf'             => 'přihláška (web/sablony/blackarrow/prihlaska.xtpl)',
    ];

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

    /**
     * @param string $cestaVuciWebu například `soubory/obsah/stranky/priklad.pdf`
     * @return list<array{popis: string, url: string}> adresa je prázdná, odkaz je v kódu a z administrace se nezmění
     */
    public static function odkazyZKodu(string $cestaVuciWebu): array
    {
        $mista = [];
        foreach (self::ODKAZY_Z_KODU as $vzor => $popis) {
            $regularniVyraz = '~^soubory/' . str_replace('\{rocnik\}', (string) ROCNIK, preg_quote($vzor, '~')) . '$~i';
            if (preg_match($regularniVyraz, $cestaVuciWebu) === 1) {
                $mista[$popis] = [
                    'popis' => $popis,
                    'url'   => '',
                ];
            }
        }

        return array_values($mista);
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
        $zKodu = [];
        foreach (array_keys($pouziti) as $absolutniCesta) {
            $cestaVuciWebu = ZpracovaneObrazky::cestaVuciWebu((string) $absolutniCesta);
            if ($cestaVuciWebu === null) {
                continue;
            }
            $zakodovanaCesta = implode('/', array_map('rawurlencode', explode('/', $cestaVuciWebu)));
            $hledaneCesty[] = (string) $absolutniCesta;
            $zKodu[(string) $absolutniCesta] = self::odkazyZKodu($cestaVuciWebu);
            array_push($vzory, self::vzorProLike($cestaVuciWebu), self::vzorProLike($zakodovanaCesta));
        }
        if ($hledaneCesty === []) {
            return $pouziti;
        }

        foreach ($this->zdrojeTextu() as $zdroj) {
            $radky = $this->najdiRadky($zdroj['sloupce'], $zdroj['zdroj'], $zdroj['sloupceTextu'], $zdroj['razeni'], $vzory);
            foreach ($radky as $radek) {
                foreach ($hledaneCesty as $index => $hledanaCesta) {
                    if ((int) $radek[self::PREFIX_PRIZNAKU . $index] === 1) {
                        $pouziti[$hledanaCesta][] = $zdroj['popisMista']($radek);
                    }
                }
            }
        }
        foreach ($zKodu as $absolutniCesta => $mistaVKodu) {
            array_push($pouziti[$absolutniCesta], ...$mistaVKodu);
        }

        return $pouziti;
    }

    /**
     * Všechny odkazy do složek v textech webu podle cesty souboru. Hledá se jedním LIKE na složku, takže cena
     * nezávisí na počtu souborů, na rozdíl od @see kdeJsouPouzity(). Je to podklad pro výpis, o smazání
     * rozhoduje kdeJsouPouzity(): odkaz s mezerou nebo tečkou za větou tu uniknout může.
     *
     * @param list<string> $slozkyVuciWebu například `soubory/obsah/drd`
     * @return array<string, list<array{popis: string, url: string}>> klíč je cesta vůči kořeni webu malými písmeny
     */
    public function odkazyVeSlozkach(array $slozkyVuciWebu): array
    {
        if ($slozkyVuciWebu === []) {
            return [];
        }
        $slozky = array_map(static fn (string $slozka): string => trim($slozka, '/'), $slozkyVuciWebu);
        $vzory = array_map(static fn (string $slozka): string => self::vzorProLike($slozka . '/'), $slozky);
        // bez `u`, aby text s nevalidním UTF-8 nevyřadil všechny odkazy řádku; hranice odkazu jsou samé znaky ASCII
        $hledaniOdkazu = sprintf(
            '~(?:%s)/[^\s"\'()<>\[\]?#]+~i',
            implode('|', array_map(static fn (string $slozka): string => preg_quote($slozka, '~'), $slozky)),
        );

        $odkazy = [];
        foreach ($this->zdrojeTextu() as $zdroj) {
            $radky = $this->najdiRadkySTexty($zdroj['sloupce'], $zdroj['zdroj'], $zdroj['sloupceTextu'], $zdroj['razeni'], $vzory);
            foreach ($radky as $radek) {
                $misto = $zdroj['popisMista']($radek);
                foreach (array_keys($zdroj['sloupceTextu']) as $index) {
                    preg_match_all($hledaniOdkazu, (string) $radek[self::PREFIX_TEXTU . $index], $shody);
                    foreach ($shody[0] as $odkaz) {
                        $odkazy[self::klicOdkazu($odkaz)][$misto['url']] = $misto;
                    }
                }
            }
        }

        return array_map(array_values(...), $odkazy);
    }

    private static function klicOdkazu(string $odkaz): string
    {
        return mb_strtolower((string) preg_replace('~/{2,}~', '/', str_replace('\\', '/', rawurldecode($odkaz))));
    }

    /**
     * @return list<array{
     *     sloupce: string,
     *     zdroj: string,
     *     sloupceTextu: list<string>,
     *     razeni: string,
     *     popisMista: \Closure(array<string, string|null>): array{popis: string, url: string}
     * }> sloupce jsou pro výpis řádku, zdroj je tabulka nebo spojení tabulek, sloupceTextu jsou ty, ve kterých se hledá
     */
    private function zdrojeTextu(): array
    {
        return [
            [
                'sloupce'      => 'id_stranky, url_stranky',
                'zdroj'        => 'stranky',
                'sloupceTextu' => ['obsah'],
                'razeni'       => 'url_stranky',
                'popisMista'   => static fn (array $stranka): array => [
                    'popis' => 'stránka ' . $stranka['url_stranky'],
                    'url'   => 'web/editace-stranek?id=' . $stranka['id_stranky'],
                ],
            ],
            [
                'sloupce'      => 'id, nazev, typ',
                'zdroj'        => 'novinky',
                'sloupceTextu' => ['text'],
                'razeni'       => 'vydat DESC',
                'popisMista'   => static fn (array $novinka): array => [
                    'popis' => ((int) $novinka['typ'] === \Novinka::BLOG ? 'blog ' : 'novinka ') . $novinka['nazev'],
                    'url'   => 'web/novinka?id=' . $novinka['id'],
                ],
            ],
            [
                'sloupce'      => sprintf('%1$s, %2$s, %3$s', AkceSql::ID_AKCE, AkceSql::NAZEV_AKCE, AkceSql::ROK),
                'zdroj'        => AkceSql::AKCE_SEZNAM_TABULKA,
                'sloupceTextu' => [AkceSql::POPIS],
                'razeni'       => sprintf('%1$s DESC, %2$s', AkceSql::ROK, AkceSql::NAZEV_AKCE),
                'popisMista'   => static fn (array $aktivita): array => [
                    'popis' => sprintf('aktivita %s (%d)', $aktivita[AkceSql::NAZEV_AKCE], $aktivita[AkceSql::ROK]),
                    'url'   => 'aktivity/upravy?aktivitaId=' . $aktivita[AkceSql::ID_AKCE],
                ],
            ],
            [
                'sloupce'      => sprintf(
                    '%1$s.%2$s, %3$s.%4$s, %3$s.%5$s, %3$s.%6$s',
                    MedailonekSql::MEDAILONKY_TABULKA,
                    MedailonekSql::ID_UZIVATELE,
                    UzivatelSql::UZIVATELE_HODNOTY_TABULKA,
                    UzivatelSql::LOGIN_UZIVATELE,
                    UzivatelSql::JMENO_UZIVATELE,
                    UzivatelSql::PRIJMENI_UZIVATELE,
                ),
                'zdroj'        => sprintf(
                    '%1$s INNER JOIN %2$s ON %2$s.%3$s = %1$s.%3$s',
                    MedailonekSql::MEDAILONKY_TABULKA,
                    UzivatelSql::UZIVATELE_HODNOTY_TABULKA,
                    MedailonekSql::ID_UZIVATELE,
                ),
                'sloupceTextu' => [
                    MedailonekSql::MEDAILONKY_TABULKA . '.' . MedailonekSql::O_SOBE,
                    MedailonekSql::MEDAILONKY_TABULKA . '.' . MedailonekSql::DRD,
                ],
                'razeni'       => MedailonekSql::MEDAILONKY_TABULKA . '.' . MedailonekSql::ID_UZIVATELE,
                'popisMista'   => static fn (array $medailonek): array => [
                    'popis' => 'medailonek ' . \Uzivatel::jmenoNickZjisti($medailonek),
                    'url'   => 'aktivity/medailonky?id=' . $medailonek[MedailonekSql::ID_UZIVATELE],
                ],
            ],
        ];
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

    /**
     * Řádky, jejichž text odpovídá aspoň jednomu vzoru, i s texty samotnými ve sloupcích `text_<pořadí sloupce>`.
     *
     * @param list<string> $sloupceTextu
     * @param list<string> $vzory vzory pro LIKE
     * @return list<array<string, string|null>>
     */
    private function najdiRadkySTexty(
        string $sloupce,
        string $zdroj,
        array $sloupceTextu,
        string $razeni,
        array $vzory,
    ): array {
        $texty = [];
        $podminky = [];
        foreach ($sloupceTextu as $index => $sloupecTextu) {
            $texty[] = sprintf('%s AS %s%d', $sloupecTextu, self::PREFIX_TEXTU, $index);
            foreach (array_keys($vzory) as $poradiVzoru) {
                $podminky[] = sprintf('%s LIKE $%d', $sloupecTextu, $poradiVzoru);
            }
        }

        return dbFetchAll(
            sprintf(
                <<<'SQL'
SELECT %1$s, %2$s
FROM %3$s
WHERE %4$s
ORDER BY %5$s
SQL,
                $sloupce,
                implode(', ', $texty),
                $zdroj,
                implode(' OR ', $podminky),
                $razeni,
            ),
            $vzory,
        );
    }
}
