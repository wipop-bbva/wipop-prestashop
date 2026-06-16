<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use Configuration;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;

final class RecurringSubscriptionStopPolicy
{
    public function shouldStopForParentOrderState(int $orderStateId): bool
    {
        return $orderStateId > 0 && in_array($orderStateId, [
            (int) Configuration::get('PS_OS_CANCELED'),
            (int) Configuration::get('PS_OS_REFUND'),
            (int) Configuration::get('PS_OS_ERROR'),
        ], true);
    }

    public function shouldStopForInitialAttemptStatus(PaymentAttemptStatus $status): bool
    {
        return in_array(
            $status,
            [PaymentAttemptStatus::FAILED, PaymentAttemptStatus::CANCELLED, PaymentAttemptStatus::REFUNDED],
            true
        );
    }
}
