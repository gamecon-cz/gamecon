<?php declare(strict_types=1);

namespace Gamecon\Logger;

class LogUdalosti
{
    public function zalogovatUdalost(\Uzivatel $logujici, string $zprava, array $metadata, int $rok = ROCNIK) {
        dbQuery(<<<SQL
INSERT INTO log_udalosti
SET id_logujiciho = {$logujici->id()},
zprava = $0,
metadata = $1,
rok = $rok
SQL,
            [$zprava, $this->zakodujMetadata($metadata)]);
    }

    private function zakodujMetadata(array $metadata): string {
        return json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function existujeLog(string $zprava, array $metadata, int $rok): bool {
        return (bool)dbOneCol(<<<SQL
SELECT EXISTS(
    SELECT * FROM log_udalosti
    WHERE zprava = $0 AND metadata = $1 AND rok = $rok
)
SQL,
            [$zprava, $this->zakodujMetadata($metadata)]);
    }
}
