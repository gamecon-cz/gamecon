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
        /**
         * Lidsky, co zvýhodnění znamená: „první zdarma", „první dva se slevou". Skládá se
         * tady, ne ve frontendu — až se hlášky budou překládat, je to jedno místo.
         */
        public ?string $label = null,
    ) {
    }

    /**
     * @param int|null    $zvyhodnenychCelkem kolik prvních kusů je zvýhodněných včetně tohoto
     *                                        stupně; null = stupeň je otevřený do nekonečna
     * @param bool        $vseZdarma          jestli je zdarma i všechno před tímhle stupněm
     * @param string|null $predchozi          popis předchozího stupně, na který se navazuje;
     *                                        null = tenhle stupeň je v žebříku první
     */
    public function sPopisem(?int $zvyhodnenychCelkem, bool $vseZdarma, ?string $predchozi = null): self
    {
        if ($this->ruleCode === null) {
            return $this;
        }

        // Neomezené pravidlo platí i na všechny další kusy, takže rozsah nejde spočítat.
        // Bez popisu by věta skončila u předchozího stupně a zbytek žebříku by vypadal na
        // plnou cenu. Bez čeho navazovat je to naopak celá nabídka, ne dovětek.
        if ($zvyhodnenychCelkem === null) {
            if ($predchozi === null) {
                return $this;
            }

            $zvyhodneni = $this->zvyhodneni($this->price <= 0.0);

            // Opakovat stejné slovo dvakrát („první tři se slevou, další se slevou") zní
            // krkolomně; když se konec neliší, stačí říct, že to platí i dál.
            return $this->sLabelem(
                str_ends_with($predchozi, $zvyhodneni)
                    ? sprintf('%s i další', $predchozi)
                    : sprintf('%s, další %s', $predchozi, $zvyhodneni),
            );
        }

        if ($zvyhodnenychCelkem < 1) {
            return $this;
        }

        $poradi = match ($zvyhodnenychCelkem) {
            1 => 'první',
            2 => 'první dva',
            3 => 'první tři',
            4 => 'první čtyři',
            // Od pěti se mění pád: „první čtyři“, ale „prvních 5“.
            default => sprintf('prvních %d', $zvyhodnenychCelkem),
        };

        return $this->sLabelem(sprintf('%s %s', $poradi, $this->zvyhodneni($vseZdarma && $this->price <= 0.0)));
    }

    private function zvyhodneni(bool $zdarma): string
    {
        return $zdarma ? 'zdarma' : 'se slevou';
    }

    private function sLabelem(string $label): self
    {
        return new self(
            $this->fromQuantity,
            $this->price,
            $this->discountAmount,
            $this->ruleCode,
            $this->ruleName,
            $label,
        );
    }

    /**
     * @return array{fromQuantity: int, price: string, discountAmount: string, ruleCode: string|null, ruleName: string|null, label: string|null}
     */
    public function toArray(): array
    {
        return [
            'fromQuantity'   => $this->fromQuantity,
            'price'          => number_format($this->price, 2, '.', ''),
            'discountAmount' => number_format($this->discountAmount, 2, '.', ''),
            'ruleCode'       => $this->ruleCode,
            'ruleName'       => $this->ruleName,
            'label'          => $this->label,
        ];
    }
}
