<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Role\Role;

class UserRepository
{
    public static function kdySeRegistrovalNaLetosniGc(int $idUzivatele): ?\DateTimeImmutable
    {
        $hodnota = dbOneCol(<<<SQL
SELECT posazen FROM platne_role_uzivatelu WHERE id_uzivatele = $0 AND id_role = $1
SQL,
            [$idUzivatele, Role::PRIHLASEN_NA_LETOSNI_GC],
        );

        return $hodnota
            ? new \DateTimeImmutable($hodnota)
            : null;
    }
}
