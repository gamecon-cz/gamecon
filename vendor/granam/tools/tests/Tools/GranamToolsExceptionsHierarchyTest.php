<?php declare(strict_types=1);

namespace Granam\Tests\Tools;

use Granam\Tests\ExceptionsHierarchy\Exceptions\AbstractExceptionsHierarchyTest;
use Granam\Tools\Exceptions\FileUploadException;

class GranamToolsExceptionsHierarchyTest extends AbstractExceptionsHierarchyTest
{
    /**
     * @return string
     */
    protected function getTestedNamespace(): string
    {
        return $this->getRootNamespace();
    }

    /**
     * @return string
     */
    protected function getRootNamespace(): string
    {
        return str_replace('\Tests', '', __NAMESPACE__);
    }

    /**
     * @return array|string[]
     */
    protected function getExceptionClassesSkippedFromUsageTest(): array
    {
        return [FileUploadException::class];
    }

}