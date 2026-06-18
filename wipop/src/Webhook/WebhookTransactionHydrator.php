<?php
/**
 * 2007-2026 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2026 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use DateTimeImmutable;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use Throwable;
use Wipop\Domain\Card;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class WebhookTransactionHydrator
{
    private const INVALID_PAYLOAD_MESSAGE = 'Payload does not match the expected transaction model.';

    /**
     * @param array<string, mixed> $payload
     *
     * @throws WebhookException
     */
    public function hydrate(array $payload): Transaction
    {
        try {
            return $this->mapTransaction($this->withCustomerId($payload));
        } catch (WebhookException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new WebhookException(self::INVALID_PAYLOAD_MESSAGE, 400, $throwable);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws WebhookException
     */
    private function mapTransaction(array $payload): Transaction
    {
        $transaction = new Transaction();
        $transaction->id = $this->nullableString($payload['id'] ?? null);
        $transaction->method = $this->nullableString($payload['method'] ?? null);
        $transaction->amount = $this->nullableFloat($payload['amount'] ?? null);
        $transaction->creationDate = $this->nullableDate($payload['creation_date'] ?? null);
        $transaction->operationDate = $this->nullableDate($payload['operation_date'] ?? null);
        $transaction->status = $this->nullableStatus($payload['status'] ?? null);
        $transaction->description = $this->nullableString($payload['description'] ?? null);
        $transaction->transactionType = $this->nullableString($payload['transaction_type'] ?? null);
        $transaction->operationType = $this->nullableString($payload['operation_type'] ?? null);
        $transaction->errorMessage = $this->nullableString($payload['error_message'] ?? null);
        $transaction->errorCode = $this->nullableString($payload['error_code'] ?? null);
        $transaction->authorization = $this->nullableString($payload['authorization'] ?? null);
        $transaction->orderId = $this->nullableString($payload['order_id'] ?? null);
        $transaction->customerId = $this->nullableString($payload['customer_id'] ?? null);
        $transaction->dueDate = $this->nullableDate($payload['due_date'] ?? null);
        $transaction->currency = $this->nullableString($payload['currency'] ?? null);
        $transaction->originChannel = $this->nullableString($payload['origin_channel'] ?? null);

        $card = $payload['card'] ?? null;
        if (is_array($card)) {
            $transaction->card = $this->mapCard($card);
        }

        return $transaction;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mapCard(array $payload): Card
    {
        $card = new Card();
        $card->id = $this->nullableString($payload['id'] ?? null);
        $card->number = $this->nullableString($payload['number'] ?? null);
        $card->holderName = $this->nullableString($payload['holder_name'] ?? null);
        $card->expirationMonth = $this->nullableString($payload['expiration_month'] ?? null);
        $card->expirationYear = $this->nullableString($payload['expiration_year'] ?? null);
        $card->cvv2 = $this->nullableString($payload['cvv2'] ?? null);
        $card->cardNumber = $this->nullableString($payload['card_number'] ?? null);
        $card->brand = $this->nullableString($payload['brand'] ?? null);
        $card->type = $this->nullableString($payload['type'] ?? null);
        $card->bankName = $this->nullableString($payload['bank_name'] ?? null);
        $card->bankCode = $this->nullableString($payload['bank_code'] ?? null);

        return $card;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function withCustomerId(array $payload): array
    {
        if (isset($payload['customer_id'])) {
            return $payload;
        }

        $customer = $payload['customer'] ?? null;
        if (is_array($customer) && isset($customer['id']) && is_string($customer['id'])) {
            $payload['customer_id'] = $customer['id'];
        }

        return $payload;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new WebhookException(self::INVALID_PAYLOAD_MESSAGE, 400);
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        throw new WebhookException(self::INVALID_PAYLOAD_MESSAGE, 400);
    }

    private function nullableDate(mixed $value): ?DateTimeImmutable
    {
        $value = $this->nullableString($value);
        if ($value === null || trim($value) === '') {
            return null;
        }

        return new DateTimeImmutable($value);
    }

    private function nullableStatus(mixed $value): ?TransactionStatus
    {
        $value = $this->nullableString($value);
        if ($value === null || $value === '') {
            return null;
        }

        $status = TransactionStatus::tryFrom($value);
        if ($status === null) {
            throw new WebhookException(self::INVALID_PAYLOAD_MESSAGE, 400);
        }

        return $status;
    }
}
