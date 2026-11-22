<?php

declare(strict_types=1);

namespace Gamecon\Ai;

/**
 * What is stored for one provider, for the Dev page. The token itself never leaves {@see PripojeniAi}.
 */
readonly class StavPripojeniAi
{
    public function __construct(
        public PoskytovatelAi $poskytovatel,
        // false: no row, nothing was entered
        public bool $maToken,
        // the database the token was entered in, null without a token
        public ?string $prostredi,
        // false for a token of another database, it is not used here
        public bool $jeZTohotoProstredi,
        // the last characters of a token of this database, null when it cannot be decrypted (another key)
        public ?string $konecTokenu,
        // null for the provider's default
        public ?string $model,
        public bool $pouzivat,
        public ?int $zmenil,
        public ?\DateTimeImmutable $zmenenoKdy,
    ) {
    }

    /**
     * The token can be used here: entered in this database and readable with its key.
     */
    public function jePouzitelny(): bool
    {
        return $this->maToken && $this->jeZTohotoProstredi && $this->konecTokenu !== null;
    }

    public function modelKPouziti(): string
    {
        return $this->model ?? $this->poskytovatel->vychoziModel();
    }
}
