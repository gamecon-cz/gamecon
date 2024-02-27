<?php declare(strict_types=1);

namespace Granam\Scalar;

use Granam\Scalar\Tools\ToScalar;
use Granam\Scalar\Tools\ToString;
use Granam\Tools\ValueDescriber;
use Granam\Strict\Object\StrictObject;

class Scalar extends StrictObject implements ScalarInterface
{

    /**
     * @var bool|float|int|string|null
     */
    protected $value;

    /**
     * @param bool|float|int|string|null|ScalarInterface $value
     * @param bool $strict = true Raises an exception on NULL by default
     *
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public function __construct($value, bool $strict = true)
    {
        $this->value = ToScalar::toScalar($value, $strict);
    }

    /**
     * @return bool|float|int|string|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return ToString::toString($this->getValue(), false /* not strict */);
        } catch (\Exception $exception) {
            /** __toString MUST NOT throw an exception, @link http://php.net/manual/en/language.oop5.magic.php#object.tostring */
            trigger_error(
                'The value ' . ValueDescriber::describe($this->getValue()) . ' can not be casted into string' .
                ' (' . $exception->getFile() . ':' . $exception->getLine() . ' - ' . $exception->getMessage() . ' - ' . $exception->getCode() . ')',
                E_USER_WARNING
            );

            return '';
        }
    }

}
