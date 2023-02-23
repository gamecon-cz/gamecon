<?php

namespace Gamecon\Tests\Model;

use Gamecon\Role\RoleSqlStruktura;
use Gamecon\Tests\Db\DbTest;

abstract class SqlStrukturaTest extends DbTest
{
    /**
     * @test
     */
    public function Konstanty_odpovidaji_sloupcum() {
        $sqlStrukturaClass = $this->strukturaClass();

        $classReflection   = new \ReflectionClass($sqlStrukturaClass);
        $constantsToValues = $classReflection->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        $tabulka           = $this->nazevTabulkyZKonstant($classReflection);

        $roleTabulkaNazevKonstanty = array_search($tabulka, $constantsToValues);
        unset($constantsToValues[$roleTabulkaNazevKonstanty]);

        $nazvySloupcuPodleKonstant = array_values($constantsToValues);
        $nazvySloupcu              = $this->nazvySloupcuTabulky($tabulka);

        sort($nazvySloupcu);
        sort($nazvySloupcuPodleKonstant);
        self::assertSame(
            $nazvySloupcu,
            $nazvySloupcuPodleKonstant,
            "Konstanty s názvy sloupců neodpovídají tabulce '$tabulka'"
        );
    }

    protected function nazevTabulkyZKonstant(\ReflectionClass $classReflection): string {
        $constantsToValues = $classReflection->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        foreach ($constantsToValues as $nazev => $hodnota) {
            if (str_ends_with($nazev, '_TABULKA')) {
                return $hodnota;
            }
        }
        throw new \LogicException("Nenašli jsme konstantu s názvem tabulky v {$classReflection->getName()}");
    }

    abstract protected function strukturaClass(): string;
}
