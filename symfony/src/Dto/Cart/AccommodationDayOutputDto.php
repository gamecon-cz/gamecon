<?php

declare(strict_types=1);

namespace App\Dto\Cart;

/** A column header of the accommodation grid: one night. */
class AccommodationDayOutputDto
{
    /**
     * 0 = Wednesday … 4 = Sunday, matching shop_predmety.ubytovani_den.
     */
    public int $day;

    public string $name;
}
