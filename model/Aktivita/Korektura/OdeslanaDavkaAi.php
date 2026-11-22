<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * A batch some proofreading texts point to: where it went and whether this database sent it.
 */
readonly class OdeslanaDavkaAi
{
    public function __construct(
        public string $id,
        // the value of Gamecon\Ai\PoskytovatelAi, kept as a string, a value no case knows stays readable
        public string $poskytovatel,
        // false for rows a copy of another database brought along (ostrá → beta), the batch is that database's
        public bool $zTetoDatabaze,
    ) {
    }
}
