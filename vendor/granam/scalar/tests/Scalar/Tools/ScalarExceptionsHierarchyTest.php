<?php declare(strict_types=1);

namespace Granam\Tests\Scalar\Tools;

use Granam\Scalar\Scalar;
use Granam\Tests\ExceptionsHierarchy\Exceptions\AbstractExceptionsHierarchyTest;

class ScalarExceptionsHierarchyTest extends AbstractExceptionsHierarchyTest
{
    protected function getTestedNamespace(): string
    {
        return \str_replace('\Tests', '', __NAMESPACE__);
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    protected function getRootNamespace(): string
    {
        $rootReflection = new \ReflectionClass(Scalar::class);

        return $rootReflection->getNamespaceName();
    }

    protected function getExternalRootExceptionsSubDir(): string
    {
        return '';
    }
}
