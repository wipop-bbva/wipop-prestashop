<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use PrestaShop\Module\Wipop\Exception\WebhookException;
use Throwable;
use Wipop\Domain\Transaction;
use Wipop\Serializer\Hydrator;

final class WebhookTransactionHydrator
{
    private Hydrator $hydrator;

    public function __construct(?Hydrator $hydrator = null)
    {
        $this->hydrator = $hydrator ?? new Hydrator();
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws WebhookException
     */
    public function hydrate(array $payload): Transaction
    {
        try {
            return $this->hydrator->hydrate(Transaction::class, $payload);
        } catch (Throwable $throwable) {
            throw new WebhookException('Payload does not match the expected transaction model.', 400, $throwable);
        }
    }
}
