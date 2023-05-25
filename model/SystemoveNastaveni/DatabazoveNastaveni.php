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


    public function prihlasovaciUdajeSoucasneDatabaze(): array
    {
        return [
            'DBM_USER' => try_constant('DBM_USER'),
            'DBM_PASS' => try_constant('DBM_PASS'),
            'DB_USER'  => try_constant('DB_USER'),
            'DB_PASS'  => try_constant('DB_PASS'),
            'DB_NAME'  => $this->hlavniDatabaze(),
            'DB_SERV'  => $this->serverHlavniDatabaze(),
            'DB_PORT'  => try_constant('DB_PORT'),
        ];
    }

}
