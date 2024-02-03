<?php

namespace Rikudou\Iban\Iban;

use InvalidArgumentException;
use Rikudou\Iban\Helper\ToStringIbanTrait;
use Rikudou\Iban\Helper\Utils;
use Rikudou\Iban\Validator\CompoundValidator;
use Rikudou\Iban\Validator\GenericIbanValidator;
use Rikudou\Iban\Validator\HungarianIbanValidator;
use Rikudou\Iban\Validator\ValidatorInterface;

class HungarianIbanAdapter implements IbanInterface
{
    use ToStringIbanTrait;

    /**
     * @var string
     */
    private $account;

    /**
     * @var string|NULL
     */
    private $iban = null;

    public function __construct(string $account)
    {
        $this->account = $account;
    }

    /**
     * Returns the resulting IBAN.
     *
     * @return string
     */
    public function asString(): string
    {
        if ($this->iban === null) {
            $accountNumber = strtoupper((string) preg_replace('/[\s\-]+/', '', $this->account));

            if (!in_array(strlen($accountNumber), [16, 24])) {
                throw new InvalidArgumentException('Account number length is not valid. It either has to consists of 16 or 24 numbers.');
            }

            $accountNumber = str_pad($accountNumber, 24, '0');

            $checkString = (string) preg_replace_callback('/[A-Z]/', function ($matches) {
                return base_convert($matches[0], 36, 10);
            }, ltrim($accountNumber, '0') . 'HU00');

            $mod = (int) Utils::bcmod($checkString, '97');
            $code = (string) (98 - $mod);

            $this->iban = sprintf(
                'HU%s%s',
                str_pad($code, 2, '0', STR_PAD_LEFT),
                $accountNumber
            );
        }

        return $this->iban;
    }

    /**
     * Returns the validator that checks whether the IBAN is valid.
     *
     * @return ValidatorInterface|NULL
     */
    public function getValidator(): ?ValidatorInterface
    {
        return new CompoundValidator(
            new HungarianIbanValidator($this),
            new GenericIbanValidator($this)
        );
    }
}
