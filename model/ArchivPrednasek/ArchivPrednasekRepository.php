<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

/**
 * Writes of the lecture archive. The connection is optional so that a migration can write through its own one,
 * inside its transaction.
 */
final class ArchivPrednasekRepository
{
    public function __construct(
        private readonly ?\mysqli $spojeni = null,
    ) {
    }

    public function vlozPrednasku(
        string $nazev,
        int $rok,
        ?string $youtubeId = null,
        ?int $idAkce = null,
        bool $skryta = false,
    ): int {
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek (nazev, rok, youtube_id, id_akce, skryta)
            VALUES ($0, $1, $2, $3, $4)
            SQL, [
    0         => $nazev,
            1 => $rok,
            2 => $youtubeId,
            3 => $idAkce,
            4 => (int) $skryta,
], $this->spojeni);

        return $this->idVlozeneho();
    }

    /**
     * The same name is the same speaker (záměr), whether linked to a user or not.
     */
    public function getIdPrednasejicihoPodleJmena(string $jmeno): ?int
    {
        return self::idNeboNull(dbOneCol(<<<'SQL'
            SELECT id_prednasejiciho
            FROM archiv_prednasek_prednasejici
            WHERE jmeno = $0
            ORDER BY id_uzivatele IS NULL, id_prednasejiciho
            LIMIT 1
            SQL, [
            0 => $jmeno,
        ], $this->spojeni));
    }

    public function getIdPrednasejicihoPodleUzivatele(int $idUzivatele): ?int
    {
        return self::idNeboNull(dbOneCol(<<<'SQL'
            SELECT id_prednasejiciho
            FROM archiv_prednasek_prednasejici
            WHERE id_uzivatele = $0
            ORDER BY id_prednasejiciho
            LIMIT 1
            SQL, [
            0 => $idUzivatele,
        ], $this->spojeni));
    }

    /**
     * A lecture is identified by its title and year (unique key rok_nazev, case-insensitive by the collation).
     */
    public function getIdPrednaskyPodleNazvu(string $nazev, int $rok, ?int $kromePrednasky = null): ?int
    {
        return self::idNeboNull(dbOneCol(<<<'SQL'
            SELECT id_prednasky
            FROM archiv_prednasek
            WHERE rok = $0 AND nazev = $1 AND ($2 IS NULL OR id_prednasky != $2)
            SQL, [
            0 => $rok,
            1 => $nazev,
            2 => $kromePrednasky,
        ], $this->spojeni));
    }

    /**
     * @param list<int> $idsAkci
     *
     * @return list<int> those of the activities a lecture is paired with
     */
    public function getSparovaneAktivity(array $idsAkci): array
    {
        return array_map('intval', dbFetchColumn(<<<'SQL'
            SELECT id_akce FROM archiv_prednasek WHERE id_akce IN ($0)
            SQL, [
            0 => $idsAkci,
        ], $this->spojeni));
    }

    public function vlozPrednasejiciho(string $jmeno, ?int $idUzivatele): int
    {
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek_prednasejici (jmeno, id_uzivatele)
            VALUES ($0, $1)
            SQL, [
            0 => $jmeno,
            1 => $idUzivatele,
        ], $this->spojeni);

        return $this->idVlozeneho();
    }

    /**
     * The name is unique without regard to case (collation), so „rpg“ finds „RPG“.
     */
    public function najdiNeboVlozKlicoveSlovo(string $nazev): int
    {
        $id = self::idNeboNull(dbOneCol(<<<'SQL'
            SELECT id_klicoveho_slova
            FROM archiv_prednasek_klicove_slovo
            WHERE nazev = $0
            SQL, [
            0 => $nazev,
        ], $this->spojeni));
        if ($id !== null) {
            return $id;
        }
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek_klicove_slovo (nazev)
            VALUES ($0)
            SQL, [
            0 => $nazev,
        ], $this->spojeni);

        return $this->idVlozeneho();
    }

    /**
     * @param list<int> $idsPrednasejicich in the order they are shown
     */
    public function nastavPrednasejici(int $idPrednasky, array $idsPrednasejicich): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek_prednaska_prednasejici
            WHERE id_prednasky = $0
            SQL, [
            0 => $idPrednasky,
        ], $this->spojeni);
        foreach (array_values(array_unique($idsPrednasejicich)) as $poradi => $idPrednasejiciho) {
            dbQuery(<<<'SQL'
                INSERT INTO archiv_prednasek_prednaska_prednasejici (id_prednasky, id_prednasejiciho, poradi)
                VALUES ($0, $1, $2)
                SQL, [
                0 => $idPrednasky,
                1 => $idPrednasejiciho,
                2 => $poradi,
            ], $this->spojeni);
        }
    }

    /**
     * @param list<int> $idsKlicovychSlov
     */
    public function nastavKlicovaSlova(int $idPrednasky, array $idsKlicovychSlov): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek_prednaska_klicove_slovo
            WHERE id_prednasky = $0
            SQL, [
            0 => $idPrednasky,
        ], $this->spojeni);
        foreach (array_values(array_unique($idsKlicovychSlov)) as $idKlicovehoSlova) {
            dbQuery(<<<'SQL'
                INSERT INTO archiv_prednasek_prednaska_klicove_slovo (id_prednasky, id_klicoveho_slova)
                VALUES ($0, $1)
                SQL, [
                0 => $idPrednasky,
                1 => $idKlicovehoSlova,
            ], $this->spojeni);
        }
    }

    private function idVlozeneho(): int
    {
        return (int) ($this->spojeni ?? dbConnect())->insert_id;
    }

    private static function idNeboNull(mixed $id): ?int
    {
        return $id === null || $id === false
            ? null
            : (int) $id;
    }
}
