<?php

declare(strict_types=1);

namespace Granam\String;

use Granam\Scalar\Scalar;
use Granam\Scalar\Tools\ToString;
use Granam\Scalar\Tools\Exceptions\WrongParameterType as ToString_WrongParameterType;

class StringObject extends Scalar implements StringInterface
{

    /**
     * @param bool|float|int|null|object|string $value
     * @param bool $strict = true NULL raises an exception
     * @throws \Granam\String\Exceptions\WrongParameterType
     */
    public function __construct($value, bool $strict = true)
    {
        try {
            parent::__construct(ToString::toString($value, $strict));
        } catch (ToString_WrongParameterType $exception) {
            // wrapping by a local one
            throw new Exceptions\WrongParameterType($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @param bool $withoutWhiteCharacters = false
     * @return bool
     */
    public function isEmpty(bool $withoutWhiteCharacters = false): bool
    {
        if (!$withoutWhiteCharacters) {
            return $this->getValue() === '';
        }

        return \trim($this->getValue()) === '';
    }

    public function getValue(): string
    {
        return parent::getValue();
    }

}
