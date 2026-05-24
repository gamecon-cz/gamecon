<?php
declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * One active dockerized year-archive (YYYY.gamecon.cz), parsed from
 * the shared deployment state by {@see DeploymentsReader}.
 *
 * Backing file is written by `deploy-year-archive.sh` (ansible repo,
 * role `year_archive_deployer`) on the production host.
 */
final readonly class Archive
{
    public function __construct(
        public int                $year,
        public string             $url,
        public ?string            $image,
        public ?string            $sha7,
        public ?\DateTimeImmutable $deployedAt,
    ) {
    }
}
