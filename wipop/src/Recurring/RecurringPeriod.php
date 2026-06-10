<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use function trim;

enum RecurringPeriod: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public static function fromValue(mixed $value): ?self
    {
        if (!is_scalar($value)) {
            return null;
        }

        return self::tryFrom(trim((string) $value));
    }

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Monthly',
            self::YEARLY => 'Yearly',
        };
    }
}
