<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

enum RecurringChargeStatus: string
{
    case INITIALIZED = 'initialized';
    case SUBMITTED = 'submitted';
    case PAID = 'paid';
    case FAILED = 'failed';
}
