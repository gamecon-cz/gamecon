<?php

declare(strict_types=1);

namespace App\Dto\Cart;

use App\Service\EntryFeeService;

/**
 * Current state of the voluntary entry fee, plus what the slider needs to draw itself.
 */
class EntryFeeOutputDto
{
    public string $amount;

    /**
     * Where last year's average sits on the slider, in percent of its width. Precomputed here
     * because the slider is not linear — see $gammaCorrection.
     */
    public int $lastYearAveragePercent;

    public int $lastYear;

    /**
     * The slider maps 0–1000 Kč onto its width as ratio ** gamma, so that the low amounts most
     * people pick get a usable share of the track instead of being squeezed into its first tenth.
     */
    public float $gammaCorrection;

    public int $minimum;

    public int $maximum;

    /**
     * Higher than $maximum: the slider tops out at 1000 Kč, but the text field accepts more.
     * Capped because cena_nakupni is NUMERIC(6,2), which silently truncates above 9999.99.
     */
    public int $maximumAmount;

    public static function fromAmount(string $amount, int $lastYear, int $lastYearAveragePercent): self
    {
        $dto = new self();
        $dto->amount = $amount;
        $dto->lastYear = $lastYear;
        $dto->lastYearAveragePercent = $lastYearAveragePercent;
        $dto->gammaCorrection = EntryFeeService::GAMMA_CORRECTION;
        $dto->minimum = 0;
        $dto->maximum = EntryFeeService::SLIDER_MAXIMUM;
        $dto->maximumAmount = EntryFeeService::MAXIMUM_AMOUNT;

        return $dto;
    }
}
