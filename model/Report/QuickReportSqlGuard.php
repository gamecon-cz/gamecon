<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Report\Exceptions\QuickReportSqlNotAllowed;

/**
 * A read-only session alone is not enough: one statement can override it
 * (SET STATEMENT tx_read_only = 0 FOR ..., START TRANSACTION READ WRITE),
 * so a quick report must be a single statement that starts as a read.
 */
class QuickReportSqlGuard
{
    private const ALLOWED_FIRST_KEYWORDS = ['SELECT', 'WITH', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'DESC'];

    /**
     * @throws QuickReportSqlNotAllowed
     */
    public function assertSingleReadStatement(string $sql): void
    {
        // MariaDB ends a -- comment on any control character, so one could hide code from this lexer
        if (preg_match('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~', $sql)) {
            throw new QuickReportSqlNotAllowed('Quick report nesmí obsahovat řídicí znaky');
        }
        // whether \ escapes a quote depends on the server's sql_mode NO_BACKSLASH_ESCAPES, so both readings must pass
        $this->assertSingleReadStatementCode($this->codeWithoutLiteralsAndComments($sql, backslashEscapes: true));
        $this->assertSingleReadStatementCode($this->codeWithoutLiteralsAndComments($sql, backslashEscapes: false));
    }

    /**
     * @throws QuickReportSqlNotAllowed
     */
    private function assertSingleReadStatementCode(string $code): void
    {
        $code = preg_replace('~[;\s]+$~', '', $code);

        if (str_contains($code, ';')) {
            throw new QuickReportSqlNotAllowed('Quick report smí obsahovat jen jeden SQL dotaz');
        }
        if (!preg_match('~^[\s(]*(?<keyword>[a-z]+)~i', $code, $matches)
            || !in_array(strtoupper($matches['keyword']), self::ALLOWED_FIRST_KEYWORDS, true)
        ) {
            throw new QuickReportSqlNotAllowed(
                'Quick report musí začínat na ' . implode(', ', self::ALLOWED_FIRST_KEYWORDS),
            );
        }
        if (preg_match('~\bINTO\s+(OUTFILE|DUMPFILE)\b~i', $code)) {
            throw new QuickReportSqlNotAllowed('Quick report nesmí zapisovat do souboru');
        }
    }

    /**
     * Literals collapse to empty quotes and comments to a space, so a ; or a keyword inside them is not seen as code.
     * @throws QuickReportSqlNotAllowed
     */
    private function codeWithoutLiteralsAndComments(string $sql, bool $backslashEscapes): string
    {
        $code   = '';
        $length = strlen($sql);
        $index  = 0;
        while ($index < $length) {
            $character = $sql[$index];
            $next      = $sql[$index + 1] ?? '';

            if ($character === "'" || $character === '"' || $character === '`') {
                $index = $this->indexAfterQuoted($sql, $index, $character, $backslashEscapes);
                $code  .= $character . $character;
                continue;
            }
            if ($character === '#' || ($character === '-' && $next === '-' && ctype_space($sql[$index + 2] ?? ' '))) {
                $lineEnd = strpos($sql, "\n", $index);
                $index   = $lineEnd === false
                    ? $length
                    : $lineEnd + 1;
                $code    .= ' ';
                continue;
            }
            if ($character === '/' && $next === '*') {
                // MariaDB executes the content of /*! ... */ and /*M! ... */
                if (preg_match('~\G/\*M?!~', $sql, $matches, 0, $index)) {
                    throw new QuickReportSqlNotAllowed('Quick report nesmí obsahovat spustitelné komentáře /*! */');
                }
                $commentEnd = strpos($sql, '*/', $index + 2);
                $index      = $commentEnd === false
                    ? $length
                    : $commentEnd + 2;
                $code       .= ' ';
                continue;
            }
            $code .= $character;
            $index++;
        }

        return $code;
    }

    private function indexAfterQuoted(string $sql, int $openingIndex, string $quote, bool $backslashEscapes): int
    {
        $length = strlen($sql);
        $index  = $openingIndex + 1;
        while ($index < $length) {
            $character = $sql[$index];
            if ($backslashEscapes && $character === '\\' && $quote !== '`') {
                $index += 2;
                continue;
            }
            if ($character === $quote) {
                if (($sql[$index + 1] ?? '') === $quote) {
                    $index += 2;
                    continue;
                }

                return $index + 1;
            }
            $index++;
        }

        return $length;
    }
}
