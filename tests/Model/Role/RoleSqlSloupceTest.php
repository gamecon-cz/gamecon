<?php

namespace Gamecon\Tests\Model\Role;

use Gamecon\Role\RoleSqlStruktura;
use Gamecon\Tests\Db\DbTest;

class RoleSqlSloupceTest extends DbTest
{
    /**
     * @test
     */
    public function Konstanty_odpovidaji_sloupcum() {
        $classReflection   = new \ReflectionClass(RoleSqlStruktura::class);
        $constantsToValues = $classReflection->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        $roleTabulkaNazevKonstanty = array_search(RoleSqlStruktura::ROLE_TABULKA, $constantsToValues);
        unset($constantsToValues[$roleTabulkaNazevKonstanty]);

        $nazvySloupcuPodleKonstant = array_values($constantsToValues);
        $nazvySloupcu              = $this->nazvySloupcuTabulky(RoleSqlStruktura::ROLE_TABULKA);

        sort($nazvySloupcu);
        sort($nazvySloupcuPodleKonstant);
        self::assertSame($nazvySloupcu, $nazvySloupcuPodleKonstant, 'Konstanty s názvy sloupců neodpovídají tabulce');
    }
}
