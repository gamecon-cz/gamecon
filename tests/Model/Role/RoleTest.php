<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Role;

use Gamecon\Role\Role;
use Gamecon\Tests\Db\AbstractTestDb;

class RoleTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function Ke_kazde_roli_muzu_ziskat_nazev()
    {
        $idcka = dbFetchColumn(<<<SQL
SELECT id_role FROM prava_role WHERE id_role > 0
SQL,
        );
        foreach ($idcka as $idRole) {
            self::assertNotEmpty(Role::nazevRolePodleId((int)$idRole), "Chybí název role pro ID $idRole");
        }
    }
}
