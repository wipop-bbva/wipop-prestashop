<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

use PrestaShop\Module\Wipop\Configuration\Settings;
use Wipop\Domain\Transaction;

final class PaymentAttemptRepository
{
    public function __construct(private \Db $db)
    {
    }

    public static function createDefault(): self
    {
        return new self(\Db::getInstance());
    }

    public function createForCart(
        \Cart $cart,
        string $paymentMethod,
        Settings $settings,
    ): int {
        $currency = new \Currency((int) $cart->id_currency);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $data = [
            'id_cart' => (int) $cart->id,
            'id_shop' => (int) $cart->id_shop,
            'payment_method' => pSQL($paymentMethod),
            'status' => pSQL(PaymentAttemptStatus::INITIALIZED->value),
            'amount' => round((float) $cart->getOrderTotal(true, \Cart::BOTH), 2),
            'currency_iso' => pSQL((string) $currency->iso_code),
            'is_live' => (int) $settings->liveMode,
            'terminal_id' => (int) $settings->terminalId,
            'date_add' => pSQL($now),
            'date_upd' => pSQL($now),
        ];

        if (!$this->db->insert('wipop_payment_attempt', $data)) {
            throw new \RuntimeException('Unable to create Wipop payment attempt.');
        }

        $attemptId = (int) $this->db->Insert_ID();
        if ($attemptId <= 0) {
            throw new \RuntimeException('Unable to retrieve the Wipop payment attempt identifier.');
        }

        return $attemptId;
    }

    public function assignWipopOrderId(int $attemptId, string $wipopOrderId): void
    {
        $this->update($attemptId, [
            'wipop_order_id' => pSQL($wipopOrderId),
            'date_upd' => pSQL($this->currentDateTime()),
        ]);
    }

    public function markCheckoutPending(int $attemptId, ?string $transactionId): void
    {
        $data = [
            'status' => pSQL(PaymentAttemptStatus::CHECKOUT_PENDING->value),
            'date_upd' => pSQL($this->currentDateTime()),
        ];

        if ($transactionId !== null) {
            $data['wipop_transaction_id'] = pSQL($transactionId);
        }

        $this->update($attemptId, $data);
    }

    public function markFailed(int $attemptId): void
    {
        $this->update($attemptId, [
            'status' => pSQL(PaymentAttemptStatus::FAILED->value),
            'date_upd' => pSQL($this->currentDateTime()),
        ]);
    }

    /**
     * @param array<string, float|int|string|null> $data
     */
    private function update(int $attemptId, array $data): void
    {
        $condition = sprintf('id_wipop_payment_attempt = %d', $attemptId);

        if (!$this->db->update('wipop_payment_attempt', $data, $condition, 0, true)) {
            throw new \RuntimeException('Unable to update Wipop payment attempt.');
        }
    }

    public function findByRemoteIdentifiers(?string $wipopOrderId, ?string $transactionId): ?PaymentAttempt
    {
        $wipopOrderId = $this->nullableString($wipopOrderId);
        if ($wipopOrderId !== null) {
            $attempt = $this->findOneByColumn('wipop_order_id', $wipopOrderId);
            if ($attempt !== null) {
                return $attempt;
            }
        }

        $transactionId = $this->nullableString($transactionId);
        if ($transactionId !== null) {
            return $this->findOneByColumn('wipop_transaction_id', $transactionId);
        }

        return null;
    }

    public function findLatestByCartId(int $cartId): ?PaymentAttempt
    {
        if ($cartId <= 0) {
            return null;
        }

        return $this->findOneByColumn('id_cart', $cartId);
    }

    public function findSummaryByOrderId(int $orderId): ?PaymentAttemptSummary
    {
        if ($orderId <= 0) {
            return null;
        }

        $sql = sprintf(
            'SELECT
                `id_wipop_payment_attempt`,
                `id_cart`,
                `payment_method`,
                `status`,
                `amount`,
                `currency_iso`,
                `wipop_order_id`,
                `wipop_transaction_id`,
                `remote_status`,
                `remote_error_code`,
                `remote_error_message`,
                `webhook_received_at`
            FROM `%swipop_payment_attempt`
            WHERE `id_order` = %d
            ORDER BY `id_wipop_payment_attempt` DESC
            LIMIT 1',
            $this->dbPrefix(),
            $orderId
        );

        $row = $this->db->getRow($sql);
        if (!is_array($row)) {
            return null;
        }

        return $this->mapSummaryRow($row);
    }

    public function syncFromWebhook(
        PaymentAttempt $attempt,
        Transaction $transaction,
        PaymentAttemptStatus $status,
    ): void {
        $now = $this->currentDateTime();
        $data = [
            'status' => pSQL($status->value),
            'remote_status' => $transaction->status === null ? null : pSQL($transaction->status->value),
            'remote_error_code' => $this->nullableSqlString($transaction->errorCode),
            'remote_error_message' => $this->nullableSqlString($transaction->errorMessage),
            'webhook_received_at' => pSQL($now),
            'date_upd' => pSQL($now),
        ];

        $transactionId = $this->nullableString($transaction->id);
        if ($transactionId !== null) {
            $data['wipop_transaction_id'] = pSQL($transactionId);
        }

        $wipopOrderId = $this->nullableString($transaction->orderId);
        if ($wipopOrderId !== null) {
            $data['wipop_order_id'] = pSQL($wipopOrderId);
        }

        $this->update($attempt->id, $data);
    }

    public function assignOrderId(int $attemptId, int $orderId): void
    {
        $this->update($attemptId, [
            'id_order' => $orderId,
            'date_upd' => pSQL($this->currentDateTime()),
        ]);
    }

    private function findOneByColumn(string $column, int|string $value): ?PaymentAttempt
    {
        $sql = sprintf(
            'SELECT
                `id_wipop_payment_attempt`,
                `id_cart`,
                `id_order`,
                `status`,
                `amount`,
                `currency_iso`
            FROM `%swipop_payment_attempt`
            WHERE `%s` = %s
            ORDER BY `id_wipop_payment_attempt` DESC
            LIMIT 1',
            $this->dbPrefix(),
            bqSQL($column),
            $this->sqlValue($value)
        );

        $row = $this->db->getRow($sql);
        if (!is_array($row)) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): PaymentAttempt
    {
        return new PaymentAttempt(
            (int) $row['id_wipop_payment_attempt'],
            (int) $row['id_cart'],
            $this->nullableInt($row['id_order'] ?? null),
            PaymentAttemptStatus::from((string) $row['status']),
            (float) $row['amount'],
            (string) $row['currency_iso'],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapSummaryRow(array $row): PaymentAttemptSummary
    {
        return new PaymentAttemptSummary(
            (int) $row['id_wipop_payment_attempt'],
            (int) $row['id_cart'],
            $this->nullableString($row['payment_method'] ?? null),
            PaymentAttemptStatus::from((string) $row['status']),
            (float) $row['amount'],
            (string) $row['currency_iso'],
            $this->nullableString($row['wipop_order_id'] ?? null),
            $this->nullableString($row['wipop_transaction_id'] ?? null),
            $this->nullableString($row['remote_status'] ?? null),
            $this->nullableString($row['remote_error_code'] ?? null),
            $this->nullableString($row['remote_error_message'] ?? null),
            $this->nullableString($row['webhook_received_at'] ?? null),
        );
    }

    private function sqlValue(int|string $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return sprintf('\'%s\'', pSQL($value));
    }

    private function nullableInt(mixed $value): ?int
    {
        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    private function nullableSqlString(?string $value): ?string
    {
        $value = $this->nullableString($value);

        return $value === null ? null : pSQL($value);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function currentDateTime(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function dbPrefix(): string
    {
        $dbPrefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';

        return is_string($dbPrefix) ? $dbPrefix : 'ps_';
    }
}
