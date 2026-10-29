<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

use Gamecon\Web\Motiv\SqlStruktura\BarvyWebuSqlStruktura as Sql;

class BarvyWebuRepository
{
    public function dej(StavBarevWebuEnum $stav): ?UlozeneBarvyWebu
    {
        $radek = dbFetchAll(<<<'SQL'
SELECT barvy, zmenil, zmeneno_kdy
FROM barvy_webu
WHERE stav = $0
SQL,
            [
                0 => $stav,
            ],
        )[0] ?? null;
        if ($radek === null) {
            return null;
        }

        return new UlozeneBarvyWebu(
            barvy: BarvyWebu::zJson((string) $radek[Sql::BARVY]),
            zmenil: $radek[Sql::ZMENIL] !== null
                ? (int) $radek[Sql::ZMENIL]
                : null,
            zmenenoKdy: $radek[Sql::ZMENENO_KDY] !== null
                ? new \DateTimeImmutable($radek[Sql::ZMENENO_KDY])
                : null,
        );
    }

    /**
     * What visitors see, the defaults of moderni.css when nobody has published anything yet.
     */
    public function zverejnene(): BarvyWebu
    {
        return $this->dej(StavBarevWebuEnum::ZVEREJNENO)?->barvy ?? BarvyWebu::vychozi();
    }

    public function ulozNavrh(BarvyWebu $barvy, int $zmenil): void
    {
        $this->uloz(StavBarevWebuEnum::NAVRH, $barvy, $zmenil);
    }

    /**
     * @return bool false when there is no draft to publish
     */
    public function zverejniNavrh(int $zmenil): bool
    {
        $navrh = $this->dej(StavBarevWebuEnum::NAVRH);
        if ($navrh === null) {
            return false;
        }
        $this->uloz(StavBarevWebuEnum::ZVEREJNENO, $navrh->barvy, $zmenil);
        $this->zahodNavrh();

        return true;
    }

    public function zahodNavrh(): void
    {
        dbQuery(<<<'SQL'
DELETE FROM barvy_webu
WHERE stav = $0
SQL,
            [
                0 => StavBarevWebuEnum::NAVRH,
            ],
        );
    }

    private function uloz(StavBarevWebuEnum $stav, BarvyWebu $barvy, int $zmenil): void
    {
        dbQuery(<<<'SQL'
INSERT INTO barvy_webu (stav, barvy, zmenil)
VALUES ($0, $1, $2)
ON DUPLICATE KEY UPDATE barvy = VALUES(barvy), zmenil = VALUES(zmenil), zmeneno_kdy = current_timestamp()
SQL,
            [
                0 => $stav,
                1 => $barvy->toJson(),
                2 => $zmenil,
            ],
        );
    }
}
