<?php

namespace Gamecon\Kanaly;

use EPDO;
use PDO;

/**
 * Loguje odeslané e-maily do SQLite databáze v LOGY/maily.sqlite.
 */
class MailLogger
{
    private const SOUBOR_SQLITE = 'maily.sqlite';

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
        ?string $chyba = null,
    ): void {
        $dotaz = $this->databaze()->prepare(
            'INSERT INTO maily (kdy, predmet, predmet_lower, format, adresati, prilohy_count, telo, chyba)
             VALUES (:kdy, :predmet, :predmet_lower, :format, :adresati, :prilohy_count, :telo, :chyba)'
        );
        $dotaz->execute([
            ':kdy'           => date('c'),
            ':predmet'       => $predmet,
            ':predmet_lower' => mb_strtolower($predmet, 'UTF-8'),
            ':format'        => $format,
            ':adresati'      => json_encode(array_values($adresati), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':prilohy_count' => $pocetPriloh,
            ':telo'          => $telo,
            ':chyba'         => $chyba,
        ]);
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
            "SELECT id, kdy, predmet, format, adresati, prilohy_count, chyba
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
            'SELECT id, kdy, predmet, format, adresati, prilohy_count, telo, chyba
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
        $adresar = dirname($this->cestaSqlite);
        if (!is_dir($adresar)) {
            mkdir($adresar, 0770, true);
        }
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
                chyba           TEXT
            )
            SQLITE3);
        $sqlite->query('CREATE INDEX IF NOT EXISTS idx_maily_kdy ON maily (kdy)');
        $sqlite->query('CREATE INDEX IF NOT EXISTS idx_maily_prilohy_count ON maily (prilohy_count)');
        $sqlite->query('CREATE INDEX IF NOT EXISTS idx_maily_predmet_lower ON maily (predmet_lower)');

        return $this->sqlite = $sqlite;
    }
}
