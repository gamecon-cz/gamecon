<?php

declare(strict_types=1);

namespace Granam\Tests\String;

use Granam\Tests\ExceptionsHierarchy\Exceptions\AbstractExceptionsHierarchyTest;

class GranamStringExceptionsHierarchyTest extends AbstractExceptionsHierarchyTest
{
    protected function getTestedNamespace(): string
    {
        return $this->getRootNamespace();
    }

    protected function getRootNamespace(): string
    {
        return \str_replace('\Tests', '', __NAMESPACE__);
    }

    /**
     * @return array|string[]
     */
    protected function getExternalRootNamespaces(): array
    {
        return ['Granam\Scalar'];
    }
}
