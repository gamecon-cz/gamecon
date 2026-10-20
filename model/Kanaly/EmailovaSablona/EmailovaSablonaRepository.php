<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\SqlStruktura\EmailovaSablonaSqlStruktura as Sql;

class EmailovaSablonaRepository
{
    /** @var array<string, UlozenaEmailovaSablona>|null by the value of the code */
    private ?array $podleKodu = null;

    public function dej(KodEmailoveSablonyEnum $kod): ?UlozenaEmailovaSablona
    {
        return $this->vsechny()[$kod->value] ?? null;
    }

    /**
     * Cached because of bulk sending: the subject and text and all e-mails of one run
     * must come from the same wording, even if somebody changes it meanwhile.
     * A row with a code this version does not know (left by a newer one) is skipped.
     *
     * @return array<string, UlozenaEmailovaSablona> by the value of the code
     */
    public function vsechny(): array
    {
        if ($this->podleKodu !== null) {
            return $this->podleKodu;
        }

        $radky = dbFetchAll(<<<'SQL'
SELECT kod, predmet, text, zmenil, zmeneno_kdy
FROM emailova_sablona
ORDER BY kod
SQL,
        );

        $this->podleKodu = [];
        foreach ($radky as $radek) {
            $kod = KodEmailoveSablonyEnum::tryFrom((string)$radek[Sql::KOD]);
            if ($kod === null) {
                continue;
            }
            $this->podleKodu[$kod->value] = new UlozenaEmailovaSablona(
                kod: $kod,
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
     * The subject and text as they are stored: trimmed, with Unix line ends.
     *
     * @see ZkusebniEmail::odesli() which sends the text as it would be saved
     * @return array{0: string, 1: string} subject, text
     */
    public function normalizuj(string $predmet, string $text): array
    {
        return [trim($predmet), trim(str_replace("\r\n", "\n", $text))];
    }

    /**
     * Rejects a blank template here as well, so no caller other than the form
     * (which catches it earlier) can save one.
     */
    public function uloz(
        KodEmailoveSablonyEnum $kod,
        string                 $predmet,
        string                 $text,
        int                    $zmenil,
    ): void {
        [$predmet, $text] = $this->normalizuj($predmet, $text);
        if (RozkladSymbolu::jePrazdny($predmet) || RozkladSymbolu::jePrazdny($text)) {
            throw new \InvalidArgumentException("Šablona e-mailu '{$kod->value}' nesmí mít prázdný předmět ani text.");
        }

        dbQuery(<<<'SQL'
INSERT INTO emailova_sablona(kod, predmet, text, zmenil)
VALUES ($0, $1, $2, $3)
ON DUPLICATE KEY UPDATE predmet = VALUES(predmet), text = VALUES(text), zmenil = VALUES(zmenil)
SQL,
            [
                0 => $kod->value,
                1 => $predmet,
                2 => $text,
                3 => $zmenil,
            ],
        );

        $this->podleKodu = null;
    }
}
