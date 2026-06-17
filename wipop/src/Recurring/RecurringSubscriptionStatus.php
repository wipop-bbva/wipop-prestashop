<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

enum RecurringSubscriptionStatus: string
{
    case PENDING_INITIAL_PAYMENT = 'pending_initial_payment';
    case ACTIVE = 'active';
    case STOPPED = 'stopped';
}
