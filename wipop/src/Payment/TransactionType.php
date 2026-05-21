<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

final class TransactionType
{
    public const CHARGE = 'CHARGE';
    public const REFUND = 'REFUND';

    public static function isCharge(?string $transactionType): bool
    {
        return self::normalize($transactionType) === self::CHARGE;
    }

    public static function isChargeOrMissing(?string $transactionType): bool
    {
        $transactionType = self::normalize($transactionType);

        return $transactionType === '' || $transactionType === self::CHARGE;
    }

    public static function isRefund(?string $transactionType): bool
    {
        return self::normalize($transactionType) === self::REFUND;
    }

    public static function normalize(?string $transactionType): string
    {
        return strtoupper(trim((string) $transactionType));
    }
}
