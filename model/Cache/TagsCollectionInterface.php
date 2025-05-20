<?php

declare(strict_types=1);

namespace Gamecon\Cache;

interface TagsCollectionInterface
{
    /**
     * @return array<string>
     */
    public function getTags(): array;
}
