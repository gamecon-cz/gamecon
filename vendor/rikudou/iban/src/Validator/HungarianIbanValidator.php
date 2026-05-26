<?php

namespace Rikudou\Iban\Validator;

use Rikudou\Iban\Iban\IbanInterface;

/**
 * @see https://www.ecbs.org/Download/Tr201v3.9.pdf
 */
class HungarianIbanValidator implements ValidatorInterface
{
    private const WEIGHTS = [
        9,
        7,
        3,
        1,
    ];

    /** @var IbanInterface */
    private $iban;

    public function __construct(IbanInterface $iban)
    {
        $this->iban = $iban;
    }

    public function isValid(): bool
    {
        $stringIban = strtoupper($this->iban->asString());
        if (substr($stringIban, 0, 2) !== 'HU') {
            return false;
        }

        $accountNumber = substr($stringIban, 4);
        if (strlen($accountNumber) !== 24) {
            return false;
        }

        $bankBranchPart = substr($accountNumber, 0, 8);
        $accountNumberPart = substr($accountNumber, 8);

        return $this->checkGroup($bankBranchPart)
            && $this->checkGroup($accountNumberPart);
    }

    private function checkGroup(string $group): bool
    {
        $length = strlen($group) - 1;
        $expectedChecksum = (int) substr($group, $length, 1);

        $sum = 0;
        for ($i = 0; $i < $length; $i++) {
            $weight = self::WEIGHTS[$i % count(self::WEIGHTS)];
            $sum += (int) $group[$i] * $weight;
        }

        $lastDigit = $sum % 10;
        if ($lastDigit === 0) {
            $lastDigit = 10;
        }

        $actualChecksum = 10 - $lastDigit;

        return $actualChecksum === $expectedChecksum;
    }
}
