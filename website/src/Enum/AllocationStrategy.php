<?php

declare(strict_types=1);

namespace App\Enum;

enum AllocationStrategy: string
{
    /**
     * If running low on free locations, we may want to prioritize emptying locations over less WHs
     */
    case EmptyLocationsFirst = 'empty_locations_first';

    /**
     * We aim to pick from the WH with the biggest quantity. This way item will have bigger spread across
     * different WH increasing chances, that the next order will be picked from less WHs
     */
    case FewestWarehouses = 'fewest_warehouses';
}
