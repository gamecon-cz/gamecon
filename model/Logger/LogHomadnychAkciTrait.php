<?php

namespace Gamecon\Logger;

use Gamecon\Cas\DateTimeImmutableStrict;

trait LogHomadnychAkciTrait
{

    private function zalogujHromadnouAkci(
        string                $skupina,
        string                $akce,
        string|int|float|bool $vysledek,
        \Uzivatel             $provedl
    ) {
        dbQuery(<<<SQL
INSERT INTO hromadne_akce_log(skupina, akce, vysledek, provedl)
VALUES ($0, $1, $2, $3)
SQL,
            [0 => $skupina, 1 => $akce, 2 => $vysledek, 3 => $provedl->id()]
        );
    }

    private function posledniHromadnaAkceKdy(string $skupina, string $akce): ?DateTimeImmutableStrict {
        $dokoncenoKdy = dbFetchSingle(<<<SQL
SELECT kdy
FROM hromadne_akce_log
WHERE skupina = $0
    AND akce = $1
LIMIT 1
SQL,
            [0 => $skupina, 1 => $akce]
        );
        return $dokoncenoKdy
            ? new DateTimeImmutableStrict($dokoncenoKdy)
            : null;
    }

}
