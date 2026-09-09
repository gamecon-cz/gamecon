<?php

declare(strict_types=1);

namespace App\Dto\Cart;

/**
 * The accommodation section as one payload: the day/type grid plus the state that belongs
 * to the booking as a whole. The grid is not a list of independent items — the nights of
 * one booking must be consecutive — so it is served and (later) saved as a set.
 */
class AccommodationOutputDto
{
    /**
     * Days the customer may see, in festival order.
     *
     * @var AccommodationDayOutputDto[]
     */
    public array $days = [];

    /**
     * Room types offered, ordered by name.
     *
     * @var AccommodationTypeOutputDto[]
     */
    public array $types = [];

    /**
     * Variant ids of every night already booked, flattened across types — what the grid
     * renders as ticked.
     *
     * @var int[]
     */
    public array $selectedVariantIds = [];

    /**
     * Fewer nights than this is refused unless the customer may book a single night.
     */
    public int $minimumNights = 2;

    /**
     * Whole section is past its deadline: everything renders read-only.
     */
    public bool $saleClosed = false;

    /**
     * Breakfasts a booked hotel night cancelled and the customer could put back, now that no
     * night covers them any more. Empty unless there is something to offer.
     *
     * @var int[]
     */
    public array $restorableBreakfastVariantIds = [];

    public ?string $roommate = null;

    public bool $declined = false;
}
