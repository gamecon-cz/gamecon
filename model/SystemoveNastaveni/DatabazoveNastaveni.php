<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

class DatabazoveNastaveni
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

    public function symfonyDatabaseUrl(): string
    {
        return "mysql://{$this->mocnyUzivatelHlavniDatabaze()}:{$this->mocneHesloHlavniDatabaze()}@{$this->serverHlavniDatabaze()}:{$this->portHlavniDatabaze()}/{$this->hlavniDatabaze()}?serverVersion=10.3.27-MariaDB&&charset=utf8";
    }

    private function mocneHesloHlavniDatabaze(): string
    {
        return DBM_PASS;
    }

    private function mocnyUzivatelHlavniDatabaze(): string
    {
        return DBM_USER;
    }

    private function portHlavniDatabaze(): int
    {
        return (int)DB_PORT;
    }
}
