<?php

namespace Rikudou\Iban\Iban;

use Rikudou\Iban\Validator\CompoundValidator;
use Rikudou\Iban\Validator\CzechIbanValidator;
use Rikudou\Iban\Validator\GenericIbanValidator;
use Rikudou\Iban\Validator\ValidatorInterface;

class CzechIbanAdapter extends CzechAndSlovakIbanAdapter
{
    public function getValidator(): ?ValidatorInterface
    {
        return new CompoundValidator(
            new CzechIbanValidator($this->accountNumber, $this->bankCode),
            new GenericIbanValidator($this)
        );
    }

    protected function getCountryCode(): string
    {
        return 'CZ';
    }
}
