<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model;

use Gamecon\Tests\Db\AbstractTestDb;

abstract class AbstractTestSqlStruktura extends AbstractTestDb
{
    /**
     * @test
     */
    public function Konstanty_odpovidaji_sloupcum()
    {
        $sqlStrukturaClass = $this->strukturaClass();

        $classReflection = new \ReflectionClass($sqlStrukturaClass);

        self::assertSame(
            preg_replace(
                '~Test$~',
                '',
                (new \ReflectionClass(static::class))->getShortName(),
            ),
            $classReflection->getShortName(),
            'Název třídy neodpovídá očekávanému názvu podle testu',
        );

        $constantsToValues = $classReflection->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        $tabulka = $this->nazevTabulkyZKonstant($classReflection);

        // Remove all table-name constants (ending with _TABULKA) and virtual-column constants
        $virtualniSloupce = $this->virtualniSloupce();
        foreach ($constantsToValues as $nazev => $hodnota) {
            if (str_ends_with($nazev, '_TABULKA') || in_array($hodnota, $virtualniSloupce, true)) {
                unset($constantsToValues[$nazev]);
            }
        }

        $nazvySloupcuPodleKonstant = array_values($constantsToValues);
        $nazvySloupcu = $this->nazvySloupcuTabulky($tabulka);

        sort($nazvySloupcu);
        sort($nazvySloupcuPodleKonstant);
        $chybi = array_diff($nazvySloupcu, $nazvySloupcuPodleKonstant);
        self::assertSame(
            [],
            $chybi,
            sprintf(
                "Konstanty s názvy sloupců neodpovídají tabulce '%s', chybí %s",
                $tabulka,
                var_export(implode(', ', $chybi), true),
            ),
        );
        $pprebyva = array_diff($nazvySloupcuPodleKonstant, $nazvySloupcu);
        self::assertSame(
            [],
            $pprebyva,
            sprintf(
                "Konstanty s názvy sloupců neodpovídají tabulce '%s', přebývá %s",
                $tabulka,
                var_export(implode(', ', $pprebyva), true),
            ),
        );
    }

    protected function nazevTabulkyZKonstant(\ReflectionClass $classReflection): string
    {
        $constantsToValues = $classReflection->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        $expectedTableConstantSuffix = '_TABULKA';
        $constantNames = [];
        foreach ($constantsToValues as $nazev => $hodnota) {
            if (str_ends_with($nazev, $expectedTableConstantSuffix)) {
                return $hodnota;
            }
            $constantNames[] = $nazev;
        }
        throw new \LogicException("Nenašli jsme public konstantu s názvem tabulky v {$classReflection->getName()}.
            Očekáváme nějakou co končí '{$expectedTableConstantSuffix}'. Našli jsme pouze " . implode(',', $constantNames), );
    }

    /**
     * Constants that represent virtual columns (e.g., from a view) not on the base table.
     *
     * @return string[]
     */
    protected function virtualniSloupce(): array
    {
        return [];
    }

    abstract protected function strukturaClass(): string;
}
