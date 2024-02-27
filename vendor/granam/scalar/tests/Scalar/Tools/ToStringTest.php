<?php declare(strict_types=1);

namespace Granam\Tests\Scalar\Tools;

use Granam\Scalar\Tools\Exceptions\WrongParameterType;
use Granam\Scalar\Tools\ToString;
use PHPUnit\Framework\TestCase;

class ToStringTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideScalarValue
     * @param $scalarValue
     */
    public function I_get_scalar_values_as_string($scalarValue): void
    {
        self::assertSame((string)$scalarValue, ToString::toString($scalarValue /* default */));
        self::assertSame((string)$scalarValue, ToString::toString($scalarValue, true /* strict */));
        self::assertSame((string)$scalarValue, ToString::toString($scalarValue, false /* not strict */));
    }

    public function provideScalarValue(): array
    {
        return [
            ['foo'],
            [123456],
            [123456.789876],
            [0.999999999],
            [PHP_INT_MAX],
            [false],
            [true],
            [0],
            ['']
        ];
    }

    /**
     * @test
     */
    public function I_get_empty_string_from_null_if_not_strict(): void
    {
        self::assertSame('', ToString::toString(null, false /* not strict */));
    }

    /**
     * @test
     */
    public function I_can_get_string_or_null_by_specialized_method(): void
    {
        self::assertNull(ToString::toStringOrNull(null));
    }

    /**
     * @test
     */
    public function I_cannot_pass_through_with_null_by_default(): void
    {
        $this->expectException(WrongParameterType::class);
        $this->expectExceptionMessageMatches('~^In strict mode .+got NULL$~');
        ToString::toString(null);
    }

    /**
     * @test
     */
    public function I_cannot_pass_through_with_null_if_strict(): void
    {
        $this->expectException(WrongParameterType::class);
        $this->expectExceptionMessageMatches('~In strict mode .+got NULL$~');
        ToString::toString(null, true /* strict */);
    }

    /**
     * @test
     */
    public function Throws_exception_with_array(): void
    {
        $this->expectException(WrongParameterType::class);
        $this->expectExceptionMessageMatches('~array {}$~');
        /** @noinspection PhpParamsInspection */
        ToString::toString([]);
    }

    /**
     * @test
     */
    public function Throws_exception_with_resource(): void
    {
        $this->expectException(WrongParameterType::class);
        $this->expectExceptionMessageMatches('~got resource$~');
        ToString::toString(\tmpfile());
    }

    /**
     * @test
     */
    public function Throws_exception_with_object(): void
    {
        $this->expectException(WrongParameterType::class);
        $this->expectExceptionMessageMatches('~got instance of [\\\]stdClass$~');
        ToString::toString(new \stdClass());
    }

    /**
     * @test
     */
    public function I_get_value_from_to_string_object(): void
    {
        $objectWithToString = new TestObjectWithToString($string = 'foo');
        self::assertSame($string, ToString::toString($objectWithToString));
    }
}
