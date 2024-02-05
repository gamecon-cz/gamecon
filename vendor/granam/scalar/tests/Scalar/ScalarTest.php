<?php declare(strict_types=1);

namespace Granam\Tests\Scalar;

use Granam\Scalar\Scalar;
use Granam\Scalar\ScalarInterface;
use Granam\Scalar\Tools\Exceptions\WrongParameterType;
use PHPUnit\Framework\TestCase;

class ScalarTest extends TestCase
{
    /**
     * @test
     */
    public function I_can_create_it(): void
    {
        $scalar = new Scalar('foo');
        self::assertNotNull($scalar);
        self::assertInstanceOf(
            ScalarInterface::class,
            $scalar,
            'Scalar object has to implement Granam\Scalar\ScalarInterface'
        );
    }

    /**
     * @test
     */
    public function I_can_turn_it_into_string(): void
    {
        $stringScalar = new Scalar($string = 'foo');
        self::assertSame($string, (string)$stringScalar);

        $integerScalar = new Scalar($integer = 123456);
        self::assertSame((string)$integer, (string)$integerScalar);

        $floatScalar = new Scalar($float = 123456.789654);
        self::assertSame((string)$float, (string)$floatScalar);

        $almostIntegerFloatScalar = new Scalar($almostIntegerFloat = 0.9999999999);
        self::assertSame((string)$almostIntegerFloat, (string)$almostIntegerFloatScalar);

        $falseScalar = new Scalar($false = false);
        self::assertSame((string)$false, (string)$falseScalar);

        $trueScalar = new Scalar($true = true);
        self::assertSame((string)$true, (string)$trueScalar);
    }

    /**
     * @test
     */
    public function I_can_use_it_with_integer(): void
    {
        $withInteger = new Scalar($integerValue = 1);
        self::assertSame($integerValue, $withInteger->getValue());
        self::assertSame((string)$integerValue, (string)$withInteger);
    }

    /**
     * @test
     */
    public function I_can_use_it_with_float(): void
    {
        $withFloat = new Scalar($floatValue = 1.1);
        self::assertSame($floatValue, $withFloat->getValue());
        self::assertSame((string)$floatValue, (string)$withFloat);
    }

    /**
     * @test
     */
    public function I_can_use_it_with_false(): void
    {
        $withFalse = new Scalar($false = false);
        self::assertFalse($withFalse->getValue());
        self::assertSame((string)$false, (string)$withFalse);
        self::assertSame('', (string)$withFalse);
    }

    /**
     * @test
     */
    public function I_can_use_it_with_true(): void
    {
        $withTrue = new Scalar($true = true);
        self::assertSame($true, $withTrue->getValue());
        self::assertSame((string)$true, (string)$withTrue);
        self::assertSame('1', (string)$withTrue);
    }

    /**
     * @test
     */
    public function I_can_use_it_with_null_if_not_strict(): void
    {
        $withNull = new Scalar($null = null, false /* not strict */);
        self::assertSame($null, $withNull->getValue());
        self::assertSame((string)$null, (string)$withNull);
        self::assertSame('', (string)$withNull);
    }

    /**
     * @test
     */
    public function I_can_not_use_it_with_null_by_default(): void
    {
        $this->expectException(WrongParameterType::class);
        $this->expectExceptionMessageMatches('~got NULL$~');
        new Scalar(null);
    }

    /**
     * @test
     */
    public function I_can_not_use_it_with_null_if_strict(): void
    {
        $this->expectException(WrongParameterType::class);
        $this->expectExceptionMessageMatches('~got NULL$~');
        new Scalar(null, true /* strict */);
    }

    /**
     * @test
     */
    public function I_can_not_use_array(): void
    {
        $this->expectException(WrongParameterType::class);
        $this->expectExceptionMessageMatches('~array {}$~');
        /** @noinspection PhpParamsInspection */
        new Scalar([]);
    }

    /**
     * @test
     */
    public function I_can_not_use_resource(): void
    {
        $this->expectException(WrongParameterType::class);
        $this->expectExceptionMessageMatches('~got resource$~');
        new Scalar(tmpfile());
    }

    /**
     * @test
     */
    public function I_can_not_use_standard_object(): void
    {
        $this->expectException(WrongParameterType::class);
        $this->expectExceptionMessageMatches('~got instance of [\\\]stdClass$~');
        new Scalar(new \stdClass());
    }

    /**
     * @test
     */
    public function I_can_use_it_with_to_string_object(): void
    {
        $strictString = new Scalar(new TestWithToString($stringValue = 'foo'));
        self::assertSame($stringValue, (string)$strictString);
    }

    /**
     * @test
     */
    public function I_got_empty_string_and_warning_on_invalid_to_string_conversion(): void
    {
        $invalidToStringScalar = new TestInvalidToStringScalar('foo', false);
        $errors = [];
        set_error_handler(
            static function ($errorNumber) use (&$errors) {
                $errors[] = $errorNumber;
            },
            E_USER_WARNING
        );
        self::assertEmpty($errors);
        self::assertSame('', (string)$invalidToStringScalar);
        restore_error_handler();
        self::assertNotEmpty($errors);
        self::assertCount(1, $errors);
        self::assertSame(E_USER_WARNING, $errors[0]);
    }

}

/** inner */
class TestWithToString
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return (string)$this->value;
    }
}

class TestInvalidToStringScalar extends Scalar
{

    public function __toString()
    {
        $this->value = [];

        return parent::__toString();
    }
}
