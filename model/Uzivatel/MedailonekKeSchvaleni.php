<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Uzivatel\SqlStruktura\MedailonekSqlStruktura as MedailonekSql;

/**
 * Text medailonku, který vypravěč napsal v Nastavení na webu a který na web pustí až organizátor (záměr: texty mají
 * držet jednotnou formu). Fotka schválení nepotřebuje. Organizátor, který medailonek upraví v adminu (Aktivity →
 * Medailonky), ho zveřejní rovnou.
 *
 * Řádek je jeden na uživatele a po schválení zůstane, ať vypravěč v Nastavení vidí, že je text schválený. Nový text
 * ho přepíše a čeká znovu. Jen text „o sobě“; profil Pána jeskyně (`medailonky.drd`) je z Mistrovství v DrD, které
 * skončilo v roce 2025, a upravuje ho jen organizátor v adminu.
 */
final class MedailonekKeSchvaleni
{
    public const string TABULKA = 'medailonky_ke_schvaleni';

    private function __construct(
        public readonly int $idUzivatele,
        public readonly string $oSobe,
        public readonly \DateTimeImmutable $odeslano,
        public readonly ?\DateTimeImmutable $schvaleno,
    ) {
    }

    public static function zUzivatele(int $idUzivatele): ?self
    {
        $radek = dbOneLine(<<<'SQL'
SELECT id_uzivatele, o_sobe, odeslano, schvaleno FROM medailonky_ke_schvaleni WHERE id_uzivatele = $0
SQL,
            [
                0 => $idUzivatele,
            ],
        );

        return $radek
            ? self::zRadku($radek)
            : null;
    }

    /**
     * @return list<self> texty, které čekají na schválení, od nejdéle čekajícího
     */
    public static function cekajici(): array
    {
        $radky = dbFetchAll(<<<'SQL'
SELECT id_uzivatele, o_sobe, odeslano, schvaleno
FROM medailonky_ke_schvaleni
WHERE schvaleno IS NULL
ORDER BY odeslano, id_uzivatele
SQL,
        );

        return array_map(self::zRadku(...), $radky);
    }

    /**
     * Linie (typy aktivit), ve kterých vypravěči letos vedou netechnickou aktivitu, v jakémkoli stavu, i v přípravě:
     * podle nich se ve schvalování filtruje. Kdo vede ve dvou liniích, je v obou.
     *
     * @param list<int> $idUzivatelu
     *
     * @return array<int, list<int>> id uživatele => id typů aktivit, jen ti, kdo nějakou vedou
     */
    public static function linieVypravecu(array $idUzivatelu, int $rocnik): array
    {
        $linie = [];
        foreach (dbFetchAll(<<<'SQL'
SELECT DISTINCT akce_organizatori.id_uzivatele, akce_seznam.typ
FROM akce_organizatori
JOIN akce_seznam ON akce_seznam.id_akce = akce_organizatori.id_akce
WHERE akce_organizatori.id_uzivatele IN ($0)
  AND akce_seznam.rok = $1
  AND akce_seznam.typ NOT IN ($2)
ORDER BY akce_organizatori.id_uzivatele, akce_seznam.typ
SQL,
            [
                0 => $idUzivatelu,
                1 => $rocnik,
                2 => TypAktivity::interniTypy(),
            ],
        ) as $radek) {
            $linie[(int) $radek['id_uzivatele']][] = (int) $radek['typ'];
        }

        return $linie;
    }

    /**
     * Pošle text z Nastavení ke schválení. Text stejný jako zveřejněný se neschvaluje a čekání se zruší (vypravěč se
     * vrátil ke zveřejněnému textu). Stejný text, jaký už čeká, čeká dál od původního času.
     *
     * @return bool jestli teď něco čeká na schválení
     */
    public static function odesli(int $idUzivatele, string $oSobe): bool
    {
        // kdo medailonek nemá, má zveřejněný prázdný text
        $zverejneny = (string) dbOneCol(<<<'SQL'
SELECT o_sobe FROM medailonky WHERE id_uzivatele = $0
SQL,
            [
                0 => $idUzivatele,
            ],
        );
        // zveřejněný text z adminu má konce řádků \r\n, z Nastavení přijde sjednocený
        if (self::normalizujText($zverejneny) === self::normalizujText($oSobe)) {
            dbQuery(<<<'SQL'
DELETE FROM medailonky_ke_schvaleni WHERE id_uzivatele = $0 AND schvaleno IS NULL
SQL,
                [
                    0 => $idUzivatele,
                ],
            );

            return false;
        }
        $cekajici = self::zUzivatele($idUzivatele);
        // poslaný znovu (dvojklik, odeslání beze změny): nový čas odeslání by organizátorovi, který text má otevřený,
        // schválení odmítl, jako by vypravěč poslal jiný text, a vypravěč by spadl na konec fronty
        if ($cekajici?->isCekajici() && self::normalizujText($cekajici->oSobe) === self::normalizujText($oSobe)) {
            return true;
        }
        // řádek medailonku (a s ním stránku /<id>) zakládá až text, který opravdu jde ke schválení, ne odeslání
        // prázdného pole
        dbInsertIgnore(MedailonekSql::MEDAILONKY_TABULKA, [
            MedailonekSql::ID_UZIVATELE => $idUzivatele,
            MedailonekSql::O_SOBE       => '',
            MedailonekSql::DRD          => '',
        ]);
        dbQuery(<<<'SQL'
INSERT INTO medailonky_ke_schvaleni (id_uzivatele, o_sobe, odeslano, schvaleno, id_schvalil)
VALUES ($0, $1, NOW(6), NULL, NULL)
ON DUPLICATE KEY UPDATE
    o_sobe = VALUES(o_sobe),
    odeslano = VALUES(odeslano),
    schvaleno = NULL,
    id_schvalil = NULL
SQL,
            [
                0 => $idUzivatele,
                1 => $oSobe,
            ],
        );

        return true;
    }

    /**
     * Zveřejní text, který čeká na schválení, případně v podobě upravené organizátorem.
     *
     * @param string $odeslano kdy vypravěč poslal text, který organizátor viděl (@see getOdeslanoProFormular()):
     *                         kdyby mezitím poslal nový, schválil by se text, který nikdo nečetl
     *
     * @throws MedailonekKeSchvaleniException když text už není ke schválení nebo vypravěč mezitím poslal nový
     */
    public static function schval(int $idUzivatele, string $oSobe, string $odeslano, int $idSchvalil): void
    {
        dbBegin();
        try {
            $schvaleno = dbAffectedOrNumRows(dbQuery(<<<'SQL'
UPDATE medailonky_ke_schvaleni
SET schvaleno = NOW(), id_schvalil = $1
WHERE id_uzivatele = $0
  AND schvaleno IS NULL
  AND odeslano = $2
SQL,
                [
                    0 => $idUzivatele,
                    1 => $idSchvalil,
                    2 => $odeslano,
                ],
            ));
            if ($schvaleno !== 1) {
                throw new MedailonekKeSchvaleniException(
                    self::zUzivatele($idUzivatele)?->isCekajici()
                        ? 'Vypravěč mezitím poslal nový text, zkontroluj ho prosím znovu.'
                        : 'Text už je schválený, nebo ho vypravěč stáhl.',
                );
            }
            dbQuery(<<<'SQL'
UPDATE medailonky SET o_sobe = $1 WHERE id_uzivatele = $0
SQL,
                [
                    0 => $idUzivatele,
                    1 => $oSobe,
                ],
            );
            dbCommit();
        } catch (\Throwable $chyba) {
            dbRollback();
            throw $chyba;
        }
    }

    /**
     * Text tak, jak se porovnává a ukládá: konce řádků \n (textarea posílá \r\n, admin ho tak i ukládá), bez mezer
     * na okrajích a bez neplatného UTF-8
     */
    public static function normalizujText(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", mb_scrub($text, 'UTF-8')));
    }

    public function isCekajici(): bool
    {
        return $this->schvaleno === null;
    }

    /**
     * Čas odeslání tak, jak ho formulář schválení pošle zpět, @see schval()
     */
    public function getOdeslanoProFormular(): string
    {
        return $this->odeslano->format('Y-m-d H:i:s.u');
    }

    /**
     * @param array<string, string|null> $radek
     */
    private static function zRadku(array $radek): self
    {
        return new self(
            (int) $radek['id_uzivatele'],
            (string) $radek['o_sobe'],
            new \DateTimeImmutable((string) $radek['odeslano']),
            $radek['schvaleno'] !== null
                ? new \DateTimeImmutable($radek['schvaleno'])
                : null,
        );
    }
}
