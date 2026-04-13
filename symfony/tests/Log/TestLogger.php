<?php

declare(strict_types=1);

namespace App\Tests\Log;

use Psr\Log\AbstractLogger;

/**
 * In-memory logger for tests. Collects all log records so they can be
 * inspected on demand instead of leaking to stdout/stderr.
 */
class TestLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function hasRecord(string $message, string $level): bool
    {
        foreach ($this->records as $record) {
            if ($record['message'] === $message && $record['level'] === $level) {
                return true;
            }
        }

        return false;
    }

    public function hasRecordThatContains(string $substring, string $level): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && str_contains($record['message'], $substring)) {
                return true;
            }
        }

        return false;
    }

    public function reset(): void
    {
        $this->records = [];
    }
}
