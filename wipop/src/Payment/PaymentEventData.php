<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

final class PaymentEventData
{
    public const TYPE_REFUND = TransactionType::REFUND;
    public const TYPE_CAPTURE = 'CAPTURE';
    public const TYPE_REVERSAL = 'REVERSAL';

    public function __construct(
        public readonly string $remoteTransactionId,
        public readonly ?string $parentTransactionId,
        public readonly ?string $wipopOrderId,
        public readonly string $transactionType,
        public readonly ?string $operationType,
        public readonly ?string $status,
        public readonly ?float $amount,
        public readonly ?string $currencyIso,
        public readonly ?string $remoteErrorCode,
        public readonly ?string $remoteErrorMessage,
    ) {
    }
}
