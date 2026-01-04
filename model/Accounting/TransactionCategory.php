<?php

namespace Gamecon\Accounting;

enum TransactionCategory
{
    case ACTIVITY;
    case SHOP_ITEMS;
    case FOOD;
    case ACCOMMODATION;
    case LEFTOVER_FROM_LAST_YEAR;
    case MANUAL_MOVEMENTS;
}
