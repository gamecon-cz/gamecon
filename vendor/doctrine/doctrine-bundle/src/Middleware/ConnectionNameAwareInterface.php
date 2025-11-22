<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Middleware;

interface ConnectionNameAwareInterface
{
    public function setConnectionName(string $name): void;
}
