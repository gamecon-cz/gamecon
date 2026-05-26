<?php

namespace Rikudou\Iban\Validator;

use InvalidArgumentException;

class CompoundValidator implements ValidatorInterface
{
    /**
     * @var ValidatorInterface[]
     */
    private $validators;

    public function __construct(ValidatorInterface ...$validators)
    {
        if (!count($validators)) {
            throw new InvalidArgumentException('At least one validator is required');
        }
        $this->validators = $validators;
    }

    public function isValid(): bool
    {
        foreach ($this->validators as $validator) {
            if (!$validator->isValid()) {
                return false;
            }
        }

        return true;
    }
}
