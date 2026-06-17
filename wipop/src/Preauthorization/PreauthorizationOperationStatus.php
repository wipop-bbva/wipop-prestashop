<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Preauthorization;

use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use Wipop\Domain\TransactionStatus;

final class PreauthorizationOperationStatus
{
    public static function isSuccessful(string $eventType, ?string $status): bool
    {
        $status = self::normalize($status);

        return $status !== null && in_array($status, self::successfulStatuses($eventType), true);
    }

    public static function isCancelled(?string $status): bool
    {
        return self::normalize($status) === TransactionStatus::CANCELLED->value;
    }

    /**
     * @return string[]
     */
    public static function successfulStatuses(string $eventType): array
    {
        return match ($eventType) {
            PaymentEventData::TYPE_CAPTURE => [TransactionStatus::COMPLETED->value],
            PaymentEventData::TYPE_REVERSAL => [
                TransactionStatus::COMPLETED->value,
                TransactionStatus::CANCELLED->value,
            ],
            default => [],
        };
    }

    public static function normalize(?string $status): ?string
    {
        $status = strtoupper(trim((string) $status));

        return $status === '' ? null : $status;
    }
}
