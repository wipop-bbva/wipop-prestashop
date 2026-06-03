<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

enum PaymentAttemptStatus: string
{
    case INITIALIZED = 'initialized';
    case CHECKOUT_PENDING = 'checkout_pending';
    case AUTHORIZED = 'authorized';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function syncWithWebhookStatus(self $webhookStatus): self
    {
        if (in_array($this, [self::PAID, self::FAILED, self::CANCELLED, self::REFUNDED], true)) {
            return $this;
        }

        if ($this === self::AUTHORIZED && in_array($webhookStatus, [self::CHECKOUT_PENDING, self::FAILED], true)) {
            return self::AUTHORIZED;
        }

        return $webhookStatus;
    }
}
