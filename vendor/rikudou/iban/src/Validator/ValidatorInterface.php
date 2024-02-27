<?php

namespace Rikudou\Iban\Validator;

interface ValidatorInterface
{
    public function isValid(): bool;
}
