<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AsMiddleware
{
    /** @param string[] $connections */
    public function __construct(
        public array $connections = [],
        public int|null $priority = null,
    ) {
    }
}
