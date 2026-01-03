<?php

declare(strict_types=1);

namespace Gamecon\BackgroundProcess;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Component\Clock\ClockInterface;

/**
 * Služba pro správu background procesů
 */
class BackgroundProcessService
{
    public const COMMAND_DB_COPY     = 'database-copy';
    public const COMMAND_BFGR_REPORT = 'bfgr-report';

    public static function vytvorZGlobals(): self
    {
        return new self(
            SystemoveNastaveni::zGlobals()->kernel()->getContainer()->get('app.clock'),
        );
    }

    private ?BackgroundProcessSqlite $sqlite = null;
    private ?string                 $linuxBootId = null;

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    /**
     * Získá Linux boot ID ze /proc/sys/kernel/random/boot_id
     */
    private function getLinuxBootId(): string
    {
        if ($this->linuxBootId === null) {
            $bootIdFile = '/proc/sys/kernel/random/boot_id';
            if (!file_exists($bootIdFile)) {
                throw new \RuntimeException("Soubor '$bootIdFile' neexistuje. Tato služba funguje pouze na Linuxu.");
            }
            $bootId = trim(file_get_contents($bootIdFile));
            if (!$bootId) {
                throw new \RuntimeException("Nepodařilo se načíst boot_id ze souboru '$bootIdFile'.");
            }
            $this->linuxBootId = $bootId;
        }

        return $this->linuxBootId;
    }

    /**
     * Spustí proces na pozadí a zaznamená ho
     */
    public function startBackgroundProcess(
        string $command,
        string $scriptPath,
        array  $args = [],
        ?array $params = null,
    ): int {
        // Zkontroluj, jestli už proces neběží
        if ($this->isProcessRunning($command)) {
            throw new \RuntimeException("Proces '$command' již běží");
        }

        // Vytvoř příkazovou řádku
        $cmdLine = 'php ' . escapeshellarg($scriptPath);
        foreach ($args as $key => $value) {
            // klíče jsou definované v kódu, hodnotu ale escapujeme
            $cmdLine .= ' --' . $key . '=' . escapeshellarg((string)$value);
        }
        $cmdLine .= ' > /dev/null 2>&1 & echo $!';

        // Spusť proces a získej PID
        $pid = (int)exec($cmdLine);

        if ($pid <= 0) {
            throw new \RuntimeException("Nepodařilo se spustit proces $command");
        }

        // Zaznamenej do databáze
        $paramsJson = $params
            ? json_encode($params, JSON_UNESCAPED_UNICODE)
            : null;
        $this->getSqlite()->insertRunningProcess($this->getLinuxBootId(), $pid, $command, $paramsJson);

        return $pid;
    }

    /**
     * Zkontroluje, jestli proces s daným příkazem běží
     */
    public function isProcessRunning(string $command): bool
    {
        $process = $this->getSqlite()->findRunningProcessByCommand($command);

        if (!$process) {
            return false;
        }

        // Zkontroluj, jestli je boot ID stejné
        if ($process['linux_boot_id'] !== $this->getLinuxBootId()) {
            // Systém byl restartován, proces už neběží
            $this->getSqlite()->deleteRunningProcess(
                $process['linux_boot_id'],
                (int)$process['pid'],
                $command,
            );

            return false;
        }

        // Zkontroluj, jestli proces s daným PID existuje
        $pid = (int)$process['pid'];
        if (!$this->isPidRunning($pid)) {
            // Proces už neběží
            $this->getSqlite()->deleteRunningProcess($this->getLinuxBootId(), $pid, $command);

            return false;
        }

        return true;
    }

    /**
     * Zkontroluje, jestli proces s daným PID běží
     */
    private function isPidRunning(int $pid): bool
    {
        // Na Linuxu: zkontroluj existenci /proc/$pid
        if (file_exists("/proc/$pid")) {
            return true;
        }

        // Fallback: zkus poslat signál 0 (neškodný test)
        return posix_kill($pid, 0);
    }

    /**
     * Získá informace o běžícím procesu
     * @return array{
     *     pid: int,
     *     command: string,
     *     started_at: string,
     *     elapsed_seconds: int,
     *     estimated_remaining_seconds: ?int,
     *     progress_percent: ?int,
     *     metadata: ?array<string, mixed>
     * }|null
     */
    public function getRunningProcessInfo(string $command): ?array
    {
        if (!$this->isProcessRunning($command)) {
            return null;
        }

        $process = $this->getSqlite()->findRunningProcessByCommand($command);
        if (!$process) {
            return null;
        }

        $startedAt = new \DateTime($process['started_at']);
        $now = new \DateTime('now');
        $elapsedSeconds = $now->getTimestamp() - $startedAt->getTimestamp();

        // Získej odhadovanou dobu trvání
        $avgDuration = $this->getSqlite()->getAverageDuration($command);
        $estimatedRemainingSeconds = null;
        $progressPercent = null;

        if ($avgDuration !== null) {
            $estimatedRemainingSeconds = max(0, $avgDuration - $elapsedSeconds);
            $progressPercent = min(100, (int)round(($elapsedSeconds / max($avgDuration, 1)) * 100));
        }

        return [
            'pid'                         => (int)$process['pid'],
            'command'                     => $process['command'],
            'started_at'                  => $process['started_at'],
            'elapsed_seconds'             => $elapsedSeconds,
            'estimated_remaining_seconds' => $estimatedRemainingSeconds,
            'progress_percent'            => $progressPercent,
            'metadata'                    => $process['metadata_json']
                ? json_decode($process['metadata_json'], true)
                : null,
        ];
    }

    /**
     * Zaregistruje shutdown funkci pro automatické označení dokončení procesu
     * Volá se automaticky z worker skriptů
     */
    public function registerShutdownHandler(string $command): void
    {
        register_shutdown_function(function () use ($command) {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                // Fatal error
                $errorMessage = sprintf(
                    "%s v %s:%d",
                    $error['message'],
                    $error['file'],
                    $error['line'],
                );
                $this->markProcessCompleted($command, false, $errorMessage);
            } else {
                // Normální dokončení
                $this->markProcessCompleted($command, true);
            }
        });
    }

    /**
     * Označí proces jako dokončený (volá se při ukončení procesu)
     */
    public function markProcessCompleted(
        string  $command,
        bool    $success = true,
        ?string $errorMessage = null,
    ): void {
        $pid = getmypid();
        $status = $success
            ? 'completed'
            : 'failed';

        $this->getSqlite()->moveToLog(
            $this->getLinuxBootId(),
            $pid,
            $command,
            $status,
            $errorMessage,
        );
    }

    /**
     * Formátuje čas v sekundách na lidsky čitelný formát
     */
    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "$seconds s";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $remainingSeconds > 0
                ? sprintf("%d min %d s", $minutes, $remainingSeconds)
                : sprintf("%d min", $minutes);
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return sprintf("%d h %d min", $hours, $remainingMinutes);
    }


    private function getSqlite(): BackgroundProcessSqlite
    {
        if ($this->sqlite === null) {
            $this->sqlite = new BackgroundProcessSqlite($this->clock);
        }

        return $this->sqlite;
    }

}
