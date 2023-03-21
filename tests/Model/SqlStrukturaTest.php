<?php

namespace Gamecon\Tests\Model;

use Gamecon\Tests\Db\AbstractDbTest;

abstract class SqlStrukturaTest extends AbstractDbTest
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
        $constantsToValues           = $classReflection->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        $expectedTableConstantSuffix = '_TABULKA';
        $constantNames               = [];
        foreach ($constantsToValues as $nazev => $hodnota) {
            if (str_ends_with($nazev, $expectedTableConstantSuffix)) {
                return $hodnota;
            }
            $constantNames[] = $nazev;
        }
        throw new \LogicException(
            "Nenašli jsme public konstantu s názvem tabulky v {$classReflection->getName()}.
            Očekáváme nějakou co končí '$expectedTableConstantSuffix'. Našli jsme pouze " . implode(',', $constantNames)
        );
    }

    abstract protected function strukturaClass(): string;
}
