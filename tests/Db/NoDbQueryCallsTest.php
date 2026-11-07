<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use PHPUnit\Framework\TestCase;

/**
 * `dbQuery()` guessed from the first word of a statement whether it reads or writes, and was
 * replaced by `dbFetch()` and `dbExecute()`. A branch cut before that still calls it (and
 * `dbAffectedOrNumRows()`, which went with it), so a merge or a rebase can bring a call back;
 * it would fail only when that line runs, which no test may ever reach. This fails right away.
 */
class NoDbQueryCallsTest extends TestCase
{
    private const REMOVED = ['dbQuery', 'dbQueryS', '_dbPdoQuery', '_dbMysqliQuery', 'dbAffectedOrNumRows', 'dbNumRows'];

    public function testNothingCallsOrDeclaresTheRemovedFunctions(): void
    {
        $found = [];
        foreach ($this->phpFiles() as $relativePath) {
            foreach ($this->mentionsOfRemovedFunctions((string) file_get_contents(self::root() . '/' . $relativePath)) as $line => $name) {
                $found[] = "{$relativePath}:{$line} {$name}";
            }
        }

        self::assertSame([], $found, 'Read with dbFetch() and write with dbExecute() instead.');
    }

    /**
     * @return array<int, string> line => the removed function that is called, declared or named as a callable string
     */
    private function mentionsOfRemovedFunctions(string $code): array
    {
        $tokens = array_values(array_filter(
            token_get_all($code),
            static fn (array|string $token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));
        $found = [];
        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                // call_user_func('dbQuery', …), array_map('dbQuery', …)
                if (in_array(trim($token[1], '\'"'), self::REMOVED, true)) {
                    $found[$token[2]] = trim($token[1], '\'"');
                }
                continue;
            }
            if (! in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                continue;
            }
            $name = (string) preg_replace('~^(namespace)?\\\\~', '', $token[1]);
            if (! in_array($name, self::REMOVED, true) || ($tokens[$index + 1] ?? null) !== '(') {
                continue;
            }
            $before = $tokens[$index - 1] ?? null;
            if (is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue; // a method of some class, not the function
            }
            $found[$token[2]] = $name;
        }

        return $found;
    }

    /**
     * @return iterable<string> paths relative to the repository root
     */
    private function phpFiles(): iterable
    {
        $skipped = ['/vendor/', '/node_modules/', '/.git/', '/symfony/var/', '/cache/', '/logy/', '/backup/'];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root(), \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = $file->getPathname();
            if ($path === __FILE__ || ! str_ends_with($path, '.php')) {
                continue;
            }
            $relative = substr($path, strlen(self::root()));
            foreach ($skipped as $part) {
                if (str_contains($relative, $part)) {
                    continue 2;
                }
            }
            yield ltrim($relative, '/');
        }
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
