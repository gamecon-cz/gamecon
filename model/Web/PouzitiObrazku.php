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
        $cestaVuciWebu = ZpracovaneObrazky::cestaVuciWebu($absolutniCesta);
        if ($cestaVuciWebu === null) {
            return [];
        }
        $zakodovanaCesta = implode('/', array_map('rawurlencode', explode('/', $cestaVuciWebu)));
        $vzory = [self::vzorProLike($cestaVuciWebu), self::vzorProLike($zakodovanaCesta)];

        $pouziti = [];
        foreach (dbFetchAll(<<<'SQL'
SELECT id_stranky, url_stranky
FROM stranky
WHERE obsah LIKE $0 OR obsah LIKE $1
ORDER BY url_stranky
SQL,
            $vzory,
        ) as $stranka) {
            $pouziti[] = [
                'popis' => 'stránka ' . $stranka['url_stranky'],
                'url'   => 'web/editace-stranek?id=' . $stranka['id_stranky'],
            ];
        }

        foreach (dbFetchAll(<<<'SQL'
SELECT id, nazev, typ
FROM novinky
WHERE text LIKE $0 OR text LIKE $1
ORDER BY vydat DESC
SQL,
            $vzory,
        ) as $novinka) {
            $pouziti[] = [
                'popis' => ((int) $novinka['typ'] === \Novinka::BLOG ? 'blog ' : 'novinka ') . $novinka['nazev'],
                'url'   => 'web/novinka?id=' . $novinka['id'],
            ];
        }

        foreach (dbFetchAll(
            sprintf(
                'SELECT %1$s, %2$s, %3$s FROM %4$s WHERE %5$s LIKE $0 OR %5$s LIKE $1 ORDER BY %3$s DESC, %2$s',
                AkceSql::ID_AKCE,
                AkceSql::NAZEV_AKCE,
                AkceSql::ROK,
                AkceSql::AKCE_SEZNAM_TABULKA,
                AkceSql::POPIS,
            ),
            $vzory,
        ) as $aktivita) {
            $pouziti[] = [
                'popis' => sprintf('aktivita %s (%d)', $aktivita[AkceSql::NAZEV_AKCE], $aktivita[AkceSql::ROK]),
                'url'   => 'aktivity/upravy?aktivitaId=' . $aktivita[AkceSql::ID_AKCE],
            ];
        }

        foreach (dbFetchAll(
            sprintf(
                <<<'SQL'
SELECT %1$s.%3$s, %2$s.%6$s, %2$s.%7$s, %2$s.%8$s
FROM %1$s
JOIN %2$s ON %2$s.%3$s = %1$s.%3$s
WHERE %1$s.%4$s LIKE $0 OR %1$s.%4$s LIKE $1 OR %1$s.%5$s LIKE $0 OR %1$s.%5$s LIKE $1
SQL,
                MedailonekSql::MEDAILONKY_TABULKA,
                UzivatelSql::UZIVATELE_HODNOTY_TABULKA,
                MedailonekSql::ID_UZIVATELE,
                MedailonekSql::O_SOBE,
                MedailonekSql::DRD,
                UzivatelSql::LOGIN_UZIVATELE,
                UzivatelSql::JMENO_UZIVATELE,
                UzivatelSql::PRIJMENI_UZIVATELE,
            ),
            $vzory,
        ) as $medailonek) {
            $pouziti[] = [
                'popis' => 'medailonek ' . \Uzivatel::jmenoNickZjisti($medailonek),
                'url'   => 'aktivity/medailonky?id=' . $medailonek[MedailonekSql::ID_UZIVATELE],
            ];
        }

        return $pouziti;
    }
}
