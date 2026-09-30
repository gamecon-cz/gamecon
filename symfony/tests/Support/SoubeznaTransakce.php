<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\Connection;

/**
 * A second transaction running at the same time as the test, in its own process. It runs its
 * steps up to the first `hlasim` before `spust()` returns, so the test knows what it holds.
 */
final class SoubeznaTransakce
{
    /**
     * @param resource $proces
     * @param resource $vystup
     */
    private function __construct(
        private $proces,
        private $vystup,
    ) {
    }

    /**
     * @param list<array{0: 'sql'|'cekej'|'hlasim'|'potvrd', 1: string|int}> $kroky
     */
    public static function spust(Connection $spojeni, array $kroky): self
    {
        $parametry = $spojeni->getParams();
        $zadani = json_encode([
            'dsn' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $parametry['host'] ?? 'localhost',
                $parametry['port'] ?? 3306,
                $parametry['dbname'] ?? '',
            ),
            'user'     => $parametry['user'] ?? '',
            'password' => $parametry['password'] ?? '',
            'kroky'    => $kroky,
        ], JSON_THROW_ON_ERROR);

        $proces = proc_open(
            [PHP_BINARY, __DIR__ . '/soubezna-transakce.php', $zadani],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $roury,
        );
        if (! is_resource($proces)) {
            throw new \RuntimeException('Souběžnou transakci nejde spustit.');
        }

        $transakce = new self($proces, $roury[1]);
        $prvniRadek = fgets($roury[1]);
        if ($prvniRadek === false || str_starts_with($prvniRadek, 'chyba')) {
            throw new \RuntimeException('Souběžná transakce selhala dřív, než se ohlásila: ' . $prvniRadek . stream_get_contents($roury[2]));
        }

        return $transakce;
    }

    /**
     * Waits for the rest of the steps.
     *
     * @return string `hotovo`, or `chyba <MySQL code> <message>`
     */
    public function dokonci(): string
    {
        $zbytek = trim((string) stream_get_contents($this->vystup));
        proc_close($this->proces);

        $radky = explode("\n", $zbytek);

        return end($radky) ?: '';
    }
}
