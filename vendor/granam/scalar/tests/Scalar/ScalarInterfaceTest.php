<?php declare(strict_types = 1);

namespace Granam\Tests\Scalar;

use Granam\Scalar\ScalarInterface;
use PHPUnit\Framework\TestCase;

class ScalarInterfaceTest extends TestCase
{
    /**
     * @test
     */
    public function interface_exists(): void
    {
        self::assertTrue(interface_exists(ScalarInterface::class));
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function has_expected_methods(): void
    {
        $reflection = new \ReflectionClass(ScalarInterface::class);
        self::assertTrue($reflection->hasMethod('__toString'));
        $__toString = $reflection->getMethod('__toString');
        self::assertTrue($__toString->isPublic());
        self::assertSame(0, $__toString->getNumberOfParameters());
        self::assertTrue($reflection->hasMethod('getValue'));
        $getValue = $reflection->getMethod('getValue');
        self::assertTrue($getValue->isPublic());
        self::assertSame(0, $getValue->getNumberOfParameters());
    }
}