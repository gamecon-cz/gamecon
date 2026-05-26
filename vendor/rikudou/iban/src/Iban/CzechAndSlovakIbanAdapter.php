<?php

namespace Rikudou\Iban\Iban;

use Rikudou\Iban\Helper\ToStringIbanTrait;
use Rikudou\Iban\Validator\GenericIbanValidator;
use Rikudou\Iban\Validator\ValidatorInterface;

/**
 * @internal
 */
abstract class CzechAndSlovakIbanAdapter implements IbanInterface
{
    use ToStringIbanTrait;

    /**
     * @var string
     */
    protected $accountNumber;

    /**
     * @var string
     */
    protected $bankCode;

    /**
     * @var string|null
     */
    private $iban = null;

    public function __construct(string $accountNumber, string $bankCode)
    {
        $this->accountNumber = $accountNumber;
        $this->bankCode = $bankCode;
    }

    /**
     * Returns the resulting IBAN.
     *
     * @return string
     */
    public function asString(): string
    {
        if (is_null($this->iban)) {
            $countryCode = $this->getCountryCode();
            $part1 = ord($countryCode[0]) - ord('A') + 10;
            $part2 = ord($countryCode[1]) - ord('A') + 10;

            $accountPrefix = 0;
            $accountNumber = $this->accountNumber;
            if (strpos($accountNumber, '-') !== false) {
                $accountParts = explode('-', $accountNumber);
                $accountPrefix = $accountParts[0];
                $accountNumber = $accountParts[1];
            }

            $numeric = sprintf('%04d%06s%010s%d%d00', $this->bankCode, $accountPrefix, $accountNumber, $part1, $part2);

            $mod = '';
            foreach (str_split($numeric) as $n) {
                $mod = ($mod . $n) % 97;
            }
            $mod = intval($mod);

            $this->iban = sprintf('%.2s%02d%04s%06s%010s', $countryCode, 98 - $mod, $this->bankCode, $accountPrefix, $accountNumber);
        }

        return $this->iban;
    }

    /**
     * Returns the validator that checks whether the IBAN is valid.
     *
     * @return ValidatorInterface|null
     */
    public function getValidator(): ?ValidatorInterface
    {
        return new GenericIbanValidator($this);
    }

    abstract protected function getCountryCode(): string;
}
