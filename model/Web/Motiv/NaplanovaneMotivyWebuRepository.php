<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

use Gamecon\Web\Motiv\SqlStruktura\NaplanovanyMotivWebuSqlStruktura as Sql;

class NaplanovaneMotivyWebuRepository
{
    /**
     * The theme on at the given moment. When plans overlap, the one that started last wins,
     * so a short theme planned inside a long one interrupts it.
     */
    public function zobrazovany(\DateTimeInterface $kdy): ?NaplanovanyMotivWebu
    {
        return $this->zRadku(dbFetchAll(<<<'SQL'
SELECT id_naplanovaneho_motivu, motiv, zobrazit_od, zobrazit_do, zmenil, zmeneno_kdy
FROM naplanovany_motiv_webu
WHERE zobrazit_od <= $0
    AND zobrazit_do > $0
ORDER BY zobrazit_od DESC, id_naplanovaneho_motivu DESC
SQL,
            [
                0 => $kdy,
            ],
        ))[0] ?? null;
    }

    /**
     * @return list<NaplanovanyMotivWebu> the latest start first
     */
    public function vsechny(): array
    {
        return $this->zRadku(dbFetchAll(<<<'SQL'
SELECT id_naplanovaneho_motivu, motiv, zobrazit_od, zobrazit_do, zmenil, zmeneno_kdy
FROM naplanovany_motiv_webu
ORDER BY zobrazit_od DESC, id_naplanovaneho_motivu DESC
SQL,
        ));
    }

    /**
     * @return int the ID of the plan
     */
    public function naplanuj(
        MotivWebuEnum $motiv,
        \DateTimeInterface $zobrazitOd,
        \DateTimeInterface $zobrazitDo,
        int $zmenil,
    ): int {
        if ($zobrazitOd >= $zobrazitDo) {
            throw new \InvalidArgumentException('Motiv webu musí skončit až po svém začátku.');
        }

        dbQuery(<<<'SQL'
INSERT INTO naplanovany_motiv_webu (motiv, zobrazit_od, zobrazit_do, zmenil)
VALUES ($0, $1, $2, $3)
SQL,
            [
                0 => $motiv,
                1 => $zobrazitOd,
                2 => $zobrazitDo,
                3 => $zmenil,
            ],
        );

        return (int) dbInsertId();
    }

    /**
     * Ends a theme that is on now, one that has not started yet is left alone (delete it instead).
     */
    public function ukonci(int $id, \DateTimeInterface $kdy, int $zmenil): void
    {
        dbQuery(<<<'SQL'
UPDATE naplanovany_motiv_webu
SET zobrazit_do = $1, zmenil = $2
WHERE id_naplanovaneho_motivu = $0
    AND zobrazit_od < $1
    AND zobrazit_do > $1
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
DELETE FROM naplanovany_motiv_webu
WHERE id_naplanovaneho_motivu = $0
SQL,
            [
                0 => $id,
            ],
        );
    }

    /**
     * A row with a theme this version does not know (left by a newer one, or removed from code) is skipped.
     *
     * @param array<int, array<string, string|null>> $radky
     *
     * @return list<NaplanovanyMotivWebu>
     */
    private function zRadku(array $radky): array
    {
        $naplanovane = [];
        foreach ($radky as $radek) {
            $motiv = MotivWebuEnum::tryFrom((string) $radek[Sql::MOTIV]);
            if ($motiv === null) {
                continue;
            }
            $naplanovane[] = new NaplanovanyMotivWebu(
                id: (int) $radek[Sql::ID_NAPLANOVANEHO_MOTIVU],
                motiv: $motiv,
                zobrazitOd: new \DateTimeImmutable((string) $radek[Sql::ZOBRAZIT_OD]),
                zobrazitDo: new \DateTimeImmutable((string) $radek[Sql::ZOBRAZIT_DO]),
                zmenil: $radek[Sql::ZMENIL] !== null
                    ? (int) $radek[Sql::ZMENIL]
                    : null,
                zmenenoKdy: $radek[Sql::ZMENENO_KDY] !== null
                    ? new \DateTimeImmutable($radek[Sql::ZMENENO_KDY])
                    : null,
            );
        }

        return $naplanovane;
    }
}
