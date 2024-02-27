<?php declare(strict_types=1);

namespace Granam\Tests\Strict\Object;

use PHPUnit\Framework\TestCase;

class ObjectTest extends TestCase
{

    use StrictObjectTestTrait;

    /**
     * @return \Granam\Strict\Object\StrictObject
     */
    protected function createObjectInstance()
    {
        // returns concrete object as a tested abstract one
        return new AnObject();
    }
}