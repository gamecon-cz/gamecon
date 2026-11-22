<?php

declare(strict_types=1);

namespace Gamecon\Ai;

use Gamecon\Ai\Exceptions\PripojeniAiNelzeUlozit;
use Gamecon\Ai\SqlStruktura\AiPripojeniSqlStruktura as Sql;

/**
 * The connection to an AI's API, set by a developer in Dev → Připojení AI: a token and a model for each provider,
 * at most one provider used. With none used, the texts go to an AI by hand, through a file and a chat.
 *
 * The token is stored encrypted and belongs to the database it was entered in: a copy of the database
 * (ostrá → beta, a preview) does not use it, see the migration 2026-11-21-193852.
 */
class PripojeniAi
{
    // enough to tell which of the account's keys it is, as the providers' own pages show it
    private const int ZOBRAZENY_KONEC_TOKENU = 4;

    private readonly string $prostredi;

    public function __construct(?string $prostredi = null)
    {
        $this->prostredi = $prostredi ?? DB_NAME;
    }

    public function dejProstredi(): string
    {
        return $this->prostredi;
    }

    /**
     * The batches of the used provider, null when none is used here, the texts then go through a file only.
     */
    public function dejPouzivaneDavky(): ?DavkyAi
    {
        foreach ($this->dejStavy() as $stav) {
            if ($stav->pouzivat && $stav->jePouzitelny()) {
                return $this->dejDavky($stav->poskytovatel);
            }
        }

        return null;
    }

    /**
     * The batches of the provider whether it is used or not (a check of the token, batches sent before a switch),
     * null without a token usable here.
     *
     * @param string|null $model null for the stored one, empty for the provider's default
     */
    public function dejDavky(PoskytovatelAi $poskytovatel, ?string $model = null): ?DavkyAi
    {
        $radek = $this->dejRadek($poskytovatel);
        if ($radek === null || $radek[Sql::PROSTREDI] !== $this->prostredi) {
            return null;
        }
        $token = \Sifrovatko::desifrujNeboNull((string) $radek[Sql::ZASIFROVANY_TOKEN]);

        return $token !== null && $token !== ''
            ? $poskytovatel->davky($token, $model ?? $radek[Sql::MODEL])
            : null;
    }

    /**
     * @return array<string, StavPripojeniAi> every provider by its value, in the order of the enum
     */
    public function dejStavy(): array
    {
        $radky = [];
        foreach (dbFetchAll(<<<'SQL'
SELECT * FROM ai_pripojeni
SQL) as $radek) {
            $radky[(string) $radek[Sql::POSKYTOVATEL]] = $radek;
        }

        $stavy = [];
        foreach (PoskytovatelAi::cases() as $poskytovatel) {
            $radek = $radky[$poskytovatel->value] ?? null;
            $jeZTohotoProstredi = $radek !== null && $radek[Sql::PROSTREDI] === $this->prostredi;
            $token = $jeZTohotoProstredi
                ? \Sifrovatko::desifrujNeboNull((string) $radek[Sql::ZASIFROVANY_TOKEN])
                : null;
            $stavy[$poskytovatel->value] = new StavPripojeniAi(
                poskytovatel: $poskytovatel,
                maToken: $radek !== null,
                prostredi: $radek[Sql::PROSTREDI] ?? null,
                jeZTohotoProstredi: $jeZTohotoProstredi,
                konecTokenu: $token !== null && $token !== ''
                    ? mb_substr($token, -self::ZOBRAZENY_KONEC_TOKENU)
                    : null,
                model: $radek[Sql::MODEL] ?? null,
                pouzivat: (bool) ($radek[Sql::POUZIVAT] ?? false),
                zmenil: isset($radek[Sql::ZMENIL])
                    ? (int) $radek[Sql::ZMENIL]
                    : null,
                zmenenoKdy: isset($radek[Sql::ZMENENO_KDY])
                    ? new \DateTimeImmutable((string) $radek[Sql::ZMENENO_KDY])
                    : null,
            );
        }

        return $stavy;
    }

    /**
     * @param string|null $token empty keeps the stored one, which has to be usable here
     * @param string|null $model empty for the provider's default
     *
     * @throws PripojeniAiNelzeUlozit
     */
    public function uloz(PoskytovatelAi $poskytovatel, ?string $token, ?string $model, int $zmenil): void
    {
        $token = trim((string) $token);
        $model = trim((string) $model);
        $model = $model !== ''
            ? $model
            : null;

        if ($token === '') {
            if (! $this->dejStavy()[$poskytovatel->value]->jePouzitelny()) {
                throw new PripojeniAiNelzeUlozit("Zadej token pro {$poskytovatel->nazev()}.");
            }
            dbQuery(<<<'SQL'
UPDATE ai_pripojeni SET model = $1, zmenil = $2, zmeneno_kdy = NOW() WHERE poskytovatel = $0
SQL,
                [
                    0 => $poskytovatel->value,
                    1 => $model,
                    2 => $zmenil,
                ],
            );

            return;
        }

        // a row copied from another database keeps nothing of its use there, assignments go left to right,
        // so pouzivat compares the former prostredi
        dbQuery(<<<'SQL'
INSERT INTO ai_pripojeni (poskytovatel, zasifrovany_token, model, prostredi, pouzivat, zmenil, zmeneno_kdy)
VALUES ($0, $1, $2, $3, 0, $4, NOW())
ON DUPLICATE KEY UPDATE
    pouzivat = IF(prostredi = VALUES(prostredi), pouzivat, 0),
    zasifrovany_token = VALUES(zasifrovany_token),
    model = VALUES(model),
    prostredi = VALUES(prostredi),
    zmenil = VALUES(zmenil),
    zmeneno_kdy = VALUES(zmeneno_kdy)
SQL,
            [
                0 => $poskytovatel->value,
                1 => \Sifrovatko::zasifruj($token),
                2 => $model,
                3 => $this->prostredi,
                4 => $zmenil,
            ],
        );
    }

    /**
     * @param PoskytovatelAi|null $poskytovatel null for none, the texts then go through a file only
     *
     * @throws PripojeniAiNelzeUlozit
     */
    public function pouzivej(?PoskytovatelAi $poskytovatel, int $zmenil): void
    {
        if ($poskytovatel !== null && ! $this->dejStavy()[$poskytovatel->value]->jePouzitelny()) {
            throw new PripojeniAiNelzeUlozit("Pro {$poskytovatel->nazev()} tu není uložený token, nejdřív ho zadej.");
        }
        // rows copied from another database stop being used too; the change goes to the rows whose use changes,
        // pouzivat is assigned last, so that the conditions before it compare its former value
        dbQuery(<<<'SQL'
UPDATE ai_pripojeni
SET zmenil = IF(pouzivat <> ($0 IS NOT NULL AND poskytovatel = $0 AND prostredi = $1), $2, zmenil),
    zmeneno_kdy = IF(pouzivat <> ($0 IS NOT NULL AND poskytovatel = $0 AND prostredi = $1), NOW(), zmeneno_kdy),
    pouzivat = ($0 IS NOT NULL AND poskytovatel = $0 AND prostredi = $1)
SQL,
            [
                0 => $poskytovatel?->value,
                1 => $this->prostredi,
                2 => $zmenil,
            ],
        );
    }

    public function smaz(PoskytovatelAi $poskytovatel): void
    {
        dbQuery(<<<'SQL'
DELETE FROM ai_pripojeni WHERE poskytovatel = $0
SQL,
            [
                0 => $poskytovatel->value,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dejRadek(PoskytovatelAi $poskytovatel): ?array
    {
        $radek = dbFetchRow(<<<'SQL'
SELECT * FROM ai_pripojeni WHERE poskytovatel = $0
SQL,
            [
                0 => $poskytovatel->value,
            ],
        );

        return $radek !== []
            ? $radek
            : null;
    }
}
