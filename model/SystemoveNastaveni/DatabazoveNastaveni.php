<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

readonly class DatabazoveNastaveni
{
    public static function vytvorZGlobals(): static
    {
        static $databazoveNastaveni;
        if (!$databazoveNastaveni) {
            $databazoveNastaveni = new static(
                DB_SERV,
                DB_NAME,
                DB_ANONYM_SERV,
                DB_ANONYM_NAME
            );
        }
        return $databazoveNastaveni;
    }

    public function __construct(
        private string $serverHlavniDatabase,
        private string $hlavniDatabaze,
        private string $serverAnonymizovaneDatabase,
        private string $anonymizovanaDatabaze,
    )
    {
    }

    public function serverHlavniDatabaze(): string
    {
        return $this->serverHlavniDatabase;
    }

    public function hlavniDatabaze(): string
    {
        return $this->hlavniDatabaze;
    }

    public function serverAnonymizovaneDatabase(): string
    {
        return $this->serverAnonymizovaneDatabase;
    }

    public function anonymizovanaDatabaze(): string
    {
        return $this->anonymizovanaDatabaze;
    }

}
