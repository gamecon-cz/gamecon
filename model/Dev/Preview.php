<?php
declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * One active preview environment, parsed from the shared deployment
 * state by {@see DeploymentsReader}.
 *
 * Backing file is written by `deploy-preview-branch.sh` (ansible repo,
 * role `preview_deployer`) on the production host.
 */
final readonly class Preview
{
    public function __construct(
        public string             $slug,
        public string             $url,
        public ?string            $image,
        public ?string            $sha7,
        public ?\DateTimeImmutable $deployedAt,
    ) {
    }
}
