<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\SqlStruktura\EmailovaSablonaSqlStruktura as Sql;

class EmailovaSablonaRepository
{
    /** @var array<string, UlozenaEmailovaSablona>|null */
    private ?array $podleKodu = null;

    public function dej(string $kod): ?UlozenaEmailovaSablona
    {
        return $this->vsechny()[$kod] ?? null;
    }

    /**
     * Cached because of bulk sending: the subject and text and all e-mails of one run
     * must come from the same wording, even if somebody changes it meanwhile.
     *
     * @return array<string, UlozenaEmailovaSablona>
     */
    public function vsechny(): array
    {
        if ($this->podleKodu !== null) {
            return $this->podleKodu;
        }

        $radky = dbFetchAll(<<<SQL
SELECT kod, predmet, text, zmenil, zmeneno_kdy
FROM emailova_sablona
ORDER BY kod
SQL,
        );

        $this->podleKodu = [];
        foreach ($radky as $radek) {
            $this->podleKodu[$radek[Sql::KOD]] = new UlozenaEmailovaSablona(
                kod: (string)$radek[Sql::KOD],
                predmet: (string)$radek[Sql::PREDMET],
                text: (string)$radek[Sql::TEXT],
                zmenil: $radek[Sql::ZMENIL] !== null
                    ? (int)$radek[Sql::ZMENIL]
                    : null,
                zmenenoKdy: $radek[Sql::ZMENENO_KDY] !== null
                    ? new \DateTimeImmutable($radek[Sql::ZMENENO_KDY])
                    : null,
            );
        }

        return $this->podleKodu;
    }

    /**
     * Rejects a blank template here as well, so no caller other than the form
     * (which catches it earlier) can save one.
     */
    public function uloz(
        string $kod,
        string $predmet,
        string $text,
        int    $zmenil,
    ): void {
        $predmet = trim($predmet);
        $text    = trim(str_replace("\r\n", "\n", $text));
        if (RozkladSymbolu::jePrazdny($predmet) || RozkladSymbolu::jePrazdny($text)) {
            throw new \InvalidArgumentException("Šablona e-mailu '$kod' nesmí mít prázdný předmět ani text.");
        }

        dbQuery(<<<SQL
INSERT INTO emailova_sablona(kod, predmet, text, zmenil)
VALUES ($0, $1, $2, $3)
ON DUPLICATE KEY UPDATE predmet = VALUES(predmet), text = VALUES(text), zmenil = VALUES(zmenil)
SQL,
            [
                0 => $kod,
                1 => $predmet,
                2 => $text,
                3 => $zmenil,
            ],
        );

        $this->podleKodu = null;
    }
}
