<?php

declare(strict_types=1);

namespace Gamecon\Web\OznameniNaWebu;

use Gamecon\Web\OznameniNaWebu\SqlStruktura\OznameniNaWebuSqlStruktura as Sql;

class OznameniNaWebuRepository
{
    /**
     * The announcements a visitor sees at the given moment, the most pressing first, the newest first within a type.
     *
     * @return list<OznameniNaWebu>
     */
    public function zobrazovana(\DateTimeInterface $kdy): array
    {
        $oznameni = $this->zRadku(dbFetchAll(<<<'SQL'
SELECT id_oznameni, text, typ, zobrazit_od, zobrazit_do, zmenil, zmeneno_kdy
FROM oznameni_na_webu
WHERE (zobrazit_od IS NULL OR zobrazit_od <= $0)
    AND (zobrazit_do IS NULL OR zobrazit_do > $0)
ORDER BY id_oznameni DESC
SQL,
            [
                0 => $kdy,
            ],
        ));
        usort(
            $oznameni,
            static fn (OznameniNaWebu $a, OznameniNaWebu $b): int => $a->typ->getPoradi() <=> $b->typ->getPoradi(),
        );

        return $oznameni;
    }

    /**
     * @return list<OznameniNaWebu> the newest first
     */
    public function vsechna(): array
    {
        return $this->zRadku(dbFetchAll(<<<'SQL'
SELECT id_oznameni, text, typ, zobrazit_od, zobrazit_do, zmenil, zmeneno_kdy
FROM oznameni_na_webu
ORDER BY id_oznameni DESC
SQL,
        ));
    }

    public function dej(int $id): ?OznameniNaWebu
    {
        return $this->zRadku(dbFetchAll(<<<'SQL'
SELECT id_oznameni, text, typ, zobrazit_od, zobrazit_do, zmenil, zmeneno_kdy
FROM oznameni_na_webu
WHERE id_oznameni = $0
SQL,
            [
                0 => $id,
            ],
        ))[0] ?? null;
    }

    /**
     * Rejects a blank text and an end before the start here as well, so no caller other than the form can save one.
     *
     * @param int|null $id null for a new announcement
     *
     * @return int the ID of the saved announcement
     */
    public function uloz(
        ?int $id,
        string $text,
        TypOznameniNaWebuEnum $typ,
        ?\DateTimeInterface $zobrazitOd,
        ?\DateTimeInterface $zobrazitDo,
        int $zmenil,
    ): int {
        $text = self::normalizujText($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Oznámení na webu nesmí mít prázdný text.');
        }
        if ($zobrazitOd !== null && $zobrazitDo !== null && $zobrazitOd >= $zobrazitDo) {
            throw new \InvalidArgumentException('Oznámení na webu musí skončit až po svém začátku.');
        }

        $hodnoty = [
            0 => $text,
            1 => $typ,
            2 => $zobrazitOd,
            3 => $zobrazitDo,
            4 => $zmenil,
        ];

        if ($id === null) {
            dbQuery(<<<'SQL'
INSERT INTO oznameni_na_webu (text, typ, zobrazit_od, zobrazit_do, zmenil)
VALUES ($0, $1, $2, $3, $4)
SQL,
                $hodnoty,
            );

            return (int) dbInsertId();
        }

        dbQuery(<<<'SQL'
UPDATE oznameni_na_webu
SET text = $0, typ = $1, zobrazit_od = $2, zobrazit_do = $3, zmenil = $4
WHERE id_oznameni = $5
SQL,
            $hodnoty + [
                5 => $id,
            ],
        );

        return $id;
    }

    /**
     * Ends an announcement that is showing now, one that has not started yet is left alone (delete it instead).
     */
    public function ukonci(int $id, \DateTimeInterface $kdy, int $zmenil): void
    {
        dbQuery(<<<'SQL'
UPDATE oznameni_na_webu
SET zobrazit_do = $1, zmenil = $2
WHERE id_oznameni = $0
    AND (zobrazit_od IS NULL OR zobrazit_od < $1)
    AND (zobrazit_do IS NULL OR zobrazit_do > $1)
SQL,
            [
                0 => $id,
                1 => $kdy,
                2 => $zmenil,
            ],
        );
    }

    public function smaz(int $id): void
    {
        dbQuery(<<<'SQL'
DELETE FROM oznameni_na_webu
WHERE id_oznameni = $0
SQL,
            [
                0 => $id,
            ],
        );
    }

    /**
     * The text as it is stored: trimmed, with Unix line ends.
     */
    public static function normalizujText(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }

    /**
     * A row with a type this version does not know (left by a newer one) is skipped.
     *
     * @param array<int, array<string, string|null>> $radky
     *
     * @return list<OznameniNaWebu>
     */
    private function zRadku(array $radky): array
    {
        $oznameni = [];
        foreach ($radky as $radek) {
            $typ = TypOznameniNaWebuEnum::tryFrom((string) $radek[Sql::TYP]);
            if ($typ === null) {
                continue;
            }
            $oznameni[] = new OznameniNaWebu(
                id: (int) $radek[Sql::ID_OZNAMENI],
                text: (string) $radek[Sql::TEXT],
                typ: $typ,
                zobrazitOd: self::datum($radek[Sql::ZOBRAZIT_OD]),
                zobrazitDo: self::datum($radek[Sql::ZOBRAZIT_DO]),
                zmenil: $radek[Sql::ZMENIL] !== null
                    ? (int) $radek[Sql::ZMENIL]
                    : null,
                zmenenoKdy: self::datum($radek[Sql::ZMENENO_KDY]),
            );
        }

        return $oznameni;
    }

    private static function datum(?string $hodnota): ?\DateTimeImmutable
    {
        return $hodnota !== null
            ? new \DateTimeImmutable($hodnota)
            : null;
    }
}
