<?php

declare(strict_types=1);

namespace App\Dto\Admin;

class BulkCancelOutputDto
{
    public function __construct(
        public readonly int $cancelledCount,
        public readonly int $usersAffected,
        public readonly string $reason,
    ) {
    }
}
