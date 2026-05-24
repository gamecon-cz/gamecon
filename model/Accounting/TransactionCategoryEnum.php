<?php

declare(strict_types=1);

namespace Gamecon\Accounting;

enum TransactionCategoryEnum
{
    case ACTIVITY;
    case SHOP_ITEMS;
    case FOOD;
    case ACCOMMODATION;
    case LEFTOVER_FROM_LAST_YEAR;
    case MANUAL_MOVEMENTS;
    case VOLUNTARY_DONATION;
}
