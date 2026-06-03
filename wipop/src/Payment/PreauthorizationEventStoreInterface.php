<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

interface PreauthorizationEventStoreInterface
{
    public function savePreauthorizationOperation(
        PaymentAttempt $attempt,
        PaymentEventData $event,
        ?int $employeeId,
    ): void;

    public function hasSuccessfulPreauthorizationEvent(int $attemptId, string $eventType): bool;
}
