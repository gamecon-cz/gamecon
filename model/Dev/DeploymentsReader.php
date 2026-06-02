<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Reads the shared deployment state and returns domain objects
 * ({@see Preview}, {@see Archive}).
 *
 * Storage layout (one file per record):
 *
 *     /var/lib/gamecon/deployments/
 *     ├── previews/
 *     │   ├── phase1-preview.json
 *     │   └── feature-x.json
 *     └── archives/
 *         ├── 2024.json
 *         └── 2025.json
 *
 * Files are written by the host-side deploy scripts on production
 * (`deploy-preview-branch.sh`, `deploy-year-archive.sh` from the ansible
 * repo). Each deploy / teardown writes/removes exactly one file, so
 * concurrent deploys never touch the same file → no locking needed.
 *
 * Admin is read-only here; the contract is deliberately small so that
 * if/when the admin app itself gets dockerized we can bind-mount
 * `/var/lib/gamecon/deployments/` read-only into the container with
 * zero code changes.
 *
 * One bad record (invalid JSON, garbage slug, ...) only hides that
 * one entry — the rest of the list keeps rendering. Compare with a
 * single-JSON layout where one syntax error blanks the whole page.
 */
final class DeploymentsReader
{
    public const DEFAULT_DIRECTORY = '/var/lib/gamecon/deployments';
    public const SUPPORTED_SCHEMA = 1;

    private const SUBDIR_PREVIEWS = 'previews';
    private const SUBDIR_ARCHIVES = 'archives';

    public function __construct(
        private readonly string $directory = self::DEFAULT_DIRECTORY,
    ) {
    }

    /**
     * Human-readable reason why the state can't be read — `null` if OK.
     * Pointed at admin users so they know which knob to turn.
     */
    public function unavailableReason(): ?string
    {
        if (! is_dir($this->directory)) {
            return "Directory `{$this->directory}` does not exist. "
                . 'It is created by the ansible roles `preview_deployer` / '
                . '`year_archive_deployer` on the production host. '
                . 'On local / beta nothing will show.';
        }
        if (! is_readable($this->directory)) {
            return "Directory `{$this->directory}` is not readable by "
                . get_current_user() . '. Expected mode 0755 root:root.';
        }

        return null;
    }

    /**
     * @return list<Preview>
     */
    public function readPreviews(): array
    {
        $previews = [];
        foreach ($this->readFilesInSubdir(self::SUBDIR_PREVIEWS) as $data) {
            $preview = $this->buildPreview($data);
            if ($preview !== null) {
                $previews[] = $preview;
            }
        }

        return $previews;
    }

    /**
     * @return list<Archive>
     */
    public function readArchives(): array
    {
        $archives = [];
        foreach ($this->readFilesInSubdir(self::SUBDIR_ARCHIVES) as $data) {
            $archive = $this->buildArchive($data);
            if ($archive !== null) {
                $archives[] = $archive;
            }
        }

        return $archives;
    }

    /**
     * The most recent `deployed_at` across all records — a useful
     * "data freshness" stamp for the UI. Falls back to `null` if
     * nothing has been deployed yet.
     */
    public function updatedAt(): ?\DateTimeImmutable
    {
        $maxStamp = null;
        foreach ([self::SUBDIR_PREVIEWS, self::SUBDIR_ARCHIVES] as $subdir) {
            foreach ($this->readFilesInSubdir($subdir) as $data) {
                $stamp = $this->parseTimestamp($data['deployed_at'] ?? null);
                if ($stamp !== null && ($maxStamp === null || $stamp > $maxStamp)) {
                    $maxStamp = $stamp;
                }
            }
        }

        return $maxStamp;
    }

    /**
     * Iterate over `*.json` files in `previews/` or `archives/`, return
     * each parsed body. Skips:
     *  - missing subdirectory (treated as empty),
     *  - filenames that don't look like a slug/year (defensive — we
     *    use the basename as a fallback identifier),
     *  - files with malformed JSON or with a higher `schema_version`
     *    than we know about (forward-compat: we'd rather hide a
     *    record than misread it).
     *
     * @return iterable<array<string,mixed>>
     */
    private function readFilesInSubdir(string $subdir): iterable
    {
        $path = $this->directory . '/' . $subdir;
        if (! is_dir($path) || ! is_readable($path)) {
            return;
        }
        $files = glob($path . '/*.json') ?: [];
        sort($files); // deterministic order; UI then sorts per its own rule

        foreach ($files as $file) {
            // Filename sanity. Deploy scripts use [a-z0-9-]{1,30} for preview slugs
            // and 4-digit years for archives. Anything else is a hand-edited or
            // orphaned file we'd rather skip than misread.
            $name = basename($file, '.json');
            if (! preg_match('~^[a-z0-9-]{1,30}$~', $name)) {
                continue;
            }
            $raw = @file_get_contents($file);
            if ($raw === false || $raw === '') {
                continue;
            }
            $data = json_decode($raw, true);
            if (! is_array($data)) {
                continue;
            }
            $schema = isset($data['schema_version']) ? (int) $data['schema_version'] : self::SUPPORTED_SCHEMA;
            if ($schema > self::SUPPORTED_SCHEMA) {
                // Future schema. Skip — better to hide one record than
                // to render it through a guessed mapping.
                continue;
            }
            // Inject filename as fallback ID so the builder can fill
            // in slug/year from the file path if the body forgot it.
            $data['__filename'] = $name;
            yield $data;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function buildPreview(array $data): ?Preview
    {
        $slug = isset($data['slug']) ? (string) $data['slug'] : (string) ($data['__filename'] ?? '');
        $url = isset($data['url']) ? (string) $data['url'] : null;
        if ($slug === '' || $url === null || $url === '') {
            return null;
        }

        return new Preview(
            slug: $slug,
            url: $url,
            image: isset($data['image']) ? (string) $data['image'] : null,
            sha7: isset($data['sha7']) ? (string) $data['sha7'] : null,
            deployedAt: $this->parseTimestamp($data['deployed_at'] ?? null),
            branch: isset($data['branch']) && $data['branch'] !== null ? (string) $data['branch'] : null,
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    private function buildArchive(array $data): ?Archive
    {
        $year = isset($data['year'])
            ? (int) $data['year']
            : (ctype_digit((string) ($data['__filename'] ?? '')) ? (int) $data['__filename'] : 0);
        $url = isset($data['url']) ? (string) $data['url'] : null;
        if ($year === 0 || $url === null || $url === '') {
            return null;
        }

        return new Archive(
            year: $year,
            url: $url,
            image: isset($data['image']) ? (string) $data['image'] : null,
            sha7: isset($data['sha7']) ? (string) $data['sha7'] : null,
            deployedAt: $this->parseTimestamp($data['deployed_at'] ?? null),
        );
    }

    private function parseTimestamp(mixed $value): ?\DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
