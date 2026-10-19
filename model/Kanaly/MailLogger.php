<?php

namespace Gamecon\Kanaly;

use EPDO;
use PDO;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Loguje odeslané e-maily do SQLite databáze v LOGY/maily.sqlite.
 */
class MailLogger
{
    private const SOUBOR_SQLITE = 'maily.sqlite';

    private const DODAVANE_SLOUPCE = ['telo_html' => 'TEXT', 'sablona' => 'TEXT', 'rocnik' => 'INTEGER'];

    private ?EPDO $sqlite = null;

    public static function zGlobals(): static
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new static(LOGY . '/' . self::SOUBOR_SQLITE);
        }
        return $instance;
    }

    public function __construct(
        private readonly string $cestaSqlite,
    ) {
    }

    public function zalogujOdeslani(
        string $predmet,
        string $format,
        array  $adresati,
        int    $pocetPriloh,
        string $telo,
        ?string $teloHtml = null,
        ?string $chyba = null,
        ?string $sablona = null,
        ?int    $rocnik = null,
    ): void {
        $dotaz = $this->databaze()->prepare(
            'INSERT INTO maily (kdy, predmet, predmet_lower, format, adresati, prilohy_count, telo, telo_html, chyba, sablona, rocnik)
             VALUES (:kdy, :predmet, :predmet_lower, :format, :adresati, :prilohy_count, :telo, :telo_html, :chyba, :sablona, :rocnik)'
        );
        $dotaz->execute([
            ':kdy'           => date('c'),
            ':predmet'       => $predmet,
            ':predmet_lower' => mb_strtolower($predmet, 'UTF-8'),
            ':format'        => $format,
            ':adresati'      => json_encode(array_values($adresati), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':prilohy_count' => $pocetPriloh,
            ':telo'          => $telo,
            ':telo_html'     => $teloHtml,
            ':chyba'         => $chyba,
            ':sablona'       => $sablona,
            ':rocnik'        => $rocnik,
        ]);
    }

    /**
     * Counts per e-mail template for one year (ročník) of the festival. A message counts as sent when it has no error,
     * recipients are summed over the sent ones only, a failure is a message that did not go out even after the retry.
     * E-mails logged without a template (system notices, tests from the admin) are left out.
     * Recipients are counted here, not by the SQLite JSON functions, which a build of SQLite may lack.
     *
     * @return array<string, array{odeslano: int, prijemcu: int, selhalo: int}> by template code
     */
    public function souhrnPodleSablon(int $rocnik): array
    {
        $dotaz = $this->databaze()->prepare(<<<'SQL'
            SELECT sablona, chyba IS NULL AS odeslano, adresati
            FROM maily
            WHERE rocnik = :rocnik
              AND sablona IS NOT NULL
            SQL);
        $dotaz->bindValue(':rocnik', $rocnik, PDO::PARAM_INT);
        $dotaz->execute();

        $souhrn = [];
        while ($radek = $dotaz->fetch(PDO::FETCH_ASSOC)) {
            $kod = $radek['sablona'];
            $souhrn[$kod] ??= ['odeslano' => 0, 'prijemcu' => 0, 'selhalo' => 0];
            if ((int) $radek['odeslano'] === 1) {
                ++$souhrn[$kod]['odeslano'];
                $souhrn[$kod]['prijemcu'] += count(json_decode((string) $radek['adresati'], true) ?: []);
            } else {
                ++$souhrn[$kod]['selhalo'];
            }
        }
        ksort($souhrn);

        return $souhrn;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function najdi(
        ?string $filtr = null,
        string  $razeniSloupec = 'kdy',
        string  $razeniSmer = 'DESC',
        int     $limit = 50,
        int     $offset = 0,
    ): array {
        [$kde, $parametry] = $this->kdeProHledani($filtr);
        $orderBy           = $this->bezpecnyOrderBy($razeniSloupec, $razeniSmer);

        $dotaz = $this->databaze()->prepare(
            "SELECT id, kdy, predmet, format, adresati, prilohy_count, chyba, sablona, rocnik
             FROM maily
             $kde
             ORDER BY $orderBy
             LIMIT :limit OFFSET :offset"
        );
        foreach ($parametry as $klic => $hodnota) {
            $dotaz->bindValue($klic, $hodnota);
        }
        $dotaz->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dotaz->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dotaz->execute();

        return $dotaz->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        $dotaz = $this->databaze()->prepare(
            'SELECT id, kdy, predmet, format, adresati, prilohy_count, telo, telo_html, chyba, sablona, rocnik
             FROM maily
             WHERE id = :id'
        );
        $dotaz->bindValue(':id', $id, PDO::PARAM_INT);
        $dotaz->execute();
        $zaznam = $dotaz->fetch(PDO::FETCH_ASSOC);

        return $zaznam ?: null;
    }

    public function spocitej(?string $filtr = null): int
    {
        [$kde, $parametry] = $this->kdeProHledani($filtr);
        $dotaz             = $this->databaze()->prepare("SELECT COUNT(*) FROM maily $kde");
        foreach ($parametry as $klic => $hodnota) {
            $dotaz->bindValue($klic, $hodnota);
        }
        $dotaz->execute();

        return (int) $dotaz->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function kdeProHledani(?string $filtr): array
    {
        if ($filtr === null || trim($filtr) === '') {
            return ['', []];
        }
        $jehla = '%' . mb_strtolower(trim($filtr), 'UTF-8') . '%';
        return [
            'WHERE predmet_lower LIKE :filtr OR LOWER(adresati) LIKE :filtr',
            [':filtr' => $jehla],
        ];
    }

    private function bezpecnyOrderBy(string $sloupec, string $smer): string
    {
        $povoleneSloupce = ['kdy', 'prilohy_count'];
        if (!in_array($sloupec, $povoleneSloupce, true)) {
            $sloupec = 'kdy';
        }
        $smer = strtoupper($smer) === 'ASC' ? 'ASC' : 'DESC';
        return "$sloupec $smer";
    }

    private function databaze(): EPDO
    {
        if ($this->sqlite !== null) {
            return $this->sqlite;
        }
        (new Filesystem())->mkdir(dirname($this->cestaSqlite), 0770);
        $sqlite = new EPDO('sqlite:' . $this->cestaSqlite);
        $sqlite->query(<<<'SQLITE3'
            CREATE TABLE IF NOT EXISTS maily(
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                kdy             DATETIME NOT NULL,
                predmet         TEXT NOT NULL,
                predmet_lower   TEXT NOT NULL,
                format          TEXT NOT NULL,
                adresati        JSON NOT NULL,
                prilohy_count   INTEGER NOT NULL DEFAULT 0,
                telo            TEXT NOT NULL,
                telo_html       TEXT,
                chyba           TEXT,
                sablona         TEXT,
                rocnik          INTEGER
            )
            SQLITE3);

        $this->dodejChybejiciSloupce($sqlite);

        $sqlite->query('CREATE INDEX IF NOT EXISTS idx_maily_kdy ON maily (kdy)');
        $sqlite->query('CREATE INDEX IF NOT EXISTS idx_maily_prilohy_count ON maily (prilohy_count)');
        $sqlite->query('CREATE INDEX IF NOT EXISTS idx_maily_predmet_lower ON maily (predmet_lower)');
        $sqlite->query('CREATE INDEX IF NOT EXISTS idx_maily_rocnik_sablona ON maily (rocnik, sablona)');

        // Only a database that is whole is kept: a failed migration must not leave a half-migrated connection behind.
        $this->sqlite = $sqlite;

        return $sqlite;
    }

    /**
     * Adds columns to a database made before they existed (SQLite has no ADD COLUMN IF NOT EXISTS). The lock and the
     * second check under it keep a concurrent process from failing on "duplicate column" in the middle of sending a
     * mail, which would send it again. Older rows keep the new columns empty, so rewriting the whole log is avoided.
     */
    private function dodejChybejiciSloupce(EPDO $sqlite): void
    {
        if ($this->chybejiciSloupce($sqlite) === []) {
            return;
        }

        $sqlite->query('BEGIN IMMEDIATE');
        try {
            foreach ($this->chybejiciSloupce($sqlite) as $nazev => $typ) {
                $sqlite->query("ALTER TABLE maily ADD COLUMN $nazev $typ");
            }
            $sqlite->query('COMMIT');
        } catch (\Throwable $chyba) {
            try {
                $sqlite->query('ROLLBACK');
            } catch (\Throwable) {
                // SQLite may have ended the transaction itself, and that must not hide the original error
            }
            throw $chyba;
        }
    }

    /**
     * @return array<string, string> missing column name => its type
     */
    private function chybejiciSloupce(EPDO $sqlite): array
    {
        $sloupce = $sqlite->query('PRAGMA table_info(maily)')->fetchAll(PDO::FETCH_COLUMN, 1);

        return array_diff_key(self::DODAVANE_SLOUPCE, array_flip($sloupce));
    }
}
