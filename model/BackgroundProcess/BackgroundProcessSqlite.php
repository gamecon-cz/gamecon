<?php

declare(strict_types=1);

namespace Gamecon\BackgroundProcess;

use Gamecon\SystemoveNastaveni\ZdrojTed;
use Symfony\Component\Clock\ClockInterface;

/**
 * SQLite wrapper pro sledování background procesů
 */
class BackgroundProcessSqlite
{
    private const DB_PATH = LOGY . '/tasks.db';
    private \PDO $pdo;

    public function __construct(private readonly ClockInterface $clock)
    {
        $dbPath = self::DB_PATH;
        $dbDir = dirname($dbPath);

        // Vytvoř adresář pokud neexistuje
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $needsInit = !file_exists($dbPath);

        $this->pdo = new \PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if ($needsInit) {
            $this->initDatabase();
        }
    }

    private function initDatabase(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS background_process (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                linux_boot_id TEXT NOT NULL,
                pid INTEGER NOT NULL,
                command TEXT NOT NULL,
                metadata_json JSON,
                started_at DATETIME NOT NULL,
                UNIQUE(linux_boot_id, pid, command)
            )
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS background_process_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                linux_boot_id TEXT NOT NULL,
                pid INTEGER NOT NULL,
                command TEXT NOT NULL,
                metadata_json JSON,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NOT NULL,
                duration_seconds INTEGER NOT NULL,
                status TEXT NOT NULL,
                error_message TEXT,
                UNIQUE(linux_boot_id, pid, command)
            )
        ');

        $this->pdo->exec('
            CREATE INDEX IF NOT EXISTS idx_background_process_command
            ON background_process(command)
        ');

        $this->pdo->exec('
            CREATE INDEX IF NOT EXISTS idx_background_process_log_command
            ON background_process_log(command, finished_at DESC)
        ');
    }

    /**
     * Vloží nový běžící proces
     */
    public function insertRunningProcess(
        string $linuxBootId,
        int $pid,
        string $command,
        ?string $metadataJson = null
    ): void {
        $stmt = $this->pdo->prepare('
            INSERT OR REPLACE INTO background_process
            (linux_boot_id, pid, command, metadata_json, started_at)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$linuxBootId, $pid, $command, $metadataJson, $this->clock->now()->format('Y-m-d H:i:s')]);
    }

    /**
     * Najde běžící proces podle příkazu
     */
    public function findRunningProcessByCommand(string $command): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM background_process
            WHERE command = ?
            ORDER BY started_at DESC
            LIMIT 1
        ');
        $stmt->execute([$command]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Smaže běžící proces
     */
    public function deleteRunningProcess(string $linuxBootId, int $pid, string $command): void
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM background_process
            WHERE linux_boot_id = ? AND pid = ? AND command = ?
        ');
        $stmt->execute([$linuxBootId, $pid, $command]);
    }

    /**
     * Přesune proces do logu jako dokončený
     */
    public function moveToLog(
        string $linuxBootId,
        int $pid,
        string $command,
        string $status,
        ?string $errorMessage = null
    ): void {
        // Najdi běžící proces
        $stmt = $this->pdo->prepare('
            SELECT * FROM background_process
            WHERE linux_boot_id = ? AND pid = ? AND command = ?
        ');
        $stmt->execute([$linuxBootId, $pid, $command]);
        $process = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$process) {
            // Proces už není v tabulce, možná byl odstraněn
            return;
        }

        // Spočítej dobu trvání
        $startedAt = new \DateTimeImmutable($process['started_at']);
        $finishedAt = $this->clock->now();
        $durationSeconds = $finishedAt->getTimestamp() - $startedAt->getTimestamp();

        // Vlož do logu
        $stmt = $this->pdo->prepare('
            INSERT OR REPLACE INTO background_process_log
            (linux_boot_id, pid, command, metadata_json, started_at, finished_at, duration_seconds, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $linuxBootId,
            $pid,
            $command,
            $process['metadata_json'],
            $process['started_at'],
            $finishedAt->format('Y-m-d H:i:s'),
            $durationSeconds,
            $status,
            $errorMessage
        ]);

        // Smaž z běžících
        $this->deleteRunningProcess($linuxBootId, $pid, $command);
    }

    /**
     * Získá průměrnou dobu trvání pro daný příkaz
     */
    public function getAverageDuration(string $command): ?int
    {
        $stmt = $this->pdo->prepare('
            SELECT AVG(duration_seconds) as avg_duration
            FROM (
                SELECT duration_seconds
                FROM background_process_log
                WHERE command = ?
                    AND status = "completed"
                ORDER BY id DESC
                LIMIT 5
            )
        ');
        $stmt->execute([$command]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result && $result['avg_duration'] !== null) {
            return (int)round($result['avg_duration']);
        }

        return null;
    }
}
