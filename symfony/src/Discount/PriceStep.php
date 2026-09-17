<?php

declare(strict_types=1);

namespace App\Discount;

/**
 * Cena za jeden kus podle toho, kolikátý v pořadí je.
 *
 * Nároky se vyčerpávají: kdo má „dvě trička zdarma" a k tomu „jedno tričko zdarma", má
 * první tři za nulu a čtvrté za plnou cenu. Frontend takový žebřík dostane celý dopředu,
 * takže po přidání do košíku umí hned ukázat cenu dalšího kusu, aniž by se ptal serveru.
 */
final readonly class PriceStep
{
    public function __construct(
        /**
         * Od kolikátého kusu (1 = první) tahle cena platí.
         */
        public int $fromQuantity,
        public float $price,
        public float $discountAmount,
        /**
         * null u stupně bez slevy — tam není co pojmenovat.
         */
        public ?string $ruleCode,
        public ?string $ruleName,
    ) {
    }

    /**
     * @return array{fromQuantity: int, price: string, discountAmount: string, ruleCode: string|null, ruleName: string|null}
     */
    public function toArray(): array
    {
        return [
            'fromQuantity'   => $this->fromQuantity,
            'price'          => number_format($this->price, 2, '.', ''),
            'discountAmount' => number_format($this->discountAmount, 2, '.', ''),
            'ruleCode'       => $this->ruleCode,
            'ruleName'       => $this->ruleName,
        ];
    }
}
