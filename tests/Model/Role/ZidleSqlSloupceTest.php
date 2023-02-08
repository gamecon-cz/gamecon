<?php

namespace Gamecon\Tests\Model\Role;

use Gamecon\Role\ZidleSqlStruktura;
use Gamecon\Tests\Db\DbTest;

class ZidleSqlSloupceTest extends DbTest
{
    /**
     * @test
     */
    public function Konstanty_odpovidaji_sloupcum() {
        $classReflection   = new \ReflectionClass(ZidleSqlStruktura::class);
        $constantsToValues = $classReflection->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        $zidleTabulkaNazevKonstanty = array_search(ZidleSqlStruktura::ZIDLE_TABULKA, $constantsToValues);
        unset($constantsToValues[$zidleTabulkaNazevKonstanty]);

        $nazvySloupcuPodleKonstant = array_values($constantsToValues);
        $nazvySloupcu              = $this->nazvySloupcuTabulky(ZidleSqlStruktura::ZIDLE_TABULKA);

        sort($nazvySloupcu);
        sort($nazvySloupcuPodleKonstant);
        self::assertSame($nazvySloupcu, $nazvySloupcuPodleKonstant, 'Konstanty s názvy sloupců neodpovídají tabulce');
    }
}
