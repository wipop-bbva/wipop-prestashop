<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use DateTimeImmutable;
use Db;
use RuntimeException;

use function pSQL;
use function sprintf;
use function trim;

final class RecurringChargeRepository
{
    public function __construct(private Db $db)
    {
    }

    public static function createDefault(): self
    {
        return new self(Db::getInstance());
    }

    public function findBySubscriptionSequence(int $subscriptionId, int $sequence): ?RecurringCharge
    {
        if ($subscriptionId <= 0 || $sequence <= 0) {
            return null;
        }

        $sql = sprintf(
            'SELECT *
            FROM `%swipop_recurring_charge`
            WHERE `id_wipop_recurring_subscription` = %d
              AND `sequence` = %d',
            _DB_PREFIX_,
            $subscriptionId,
            $sequence
        );
        $row = $this->db->getRow($sql);

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function findByAttemptId(int $attemptId): ?RecurringCharge
    {
        if ($attemptId <= 0) {
            return null;
        }

        $sql = sprintf(
            'SELECT *
            FROM `%swipop_recurring_charge`
            WHERE `id_wipop_payment_attempt` = %d',
            _DB_PREFIX_,
            $attemptId
        );
        $row = $this->db->getRow($sql);

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function findByOrderId(int $orderId): ?RecurringCharge
    {
        if ($orderId <= 0) {
            return null;
        }

        $sql = sprintf(
            'SELECT *
            FROM `%swipop_recurring_charge`
            WHERE `id_order` = %d
            ORDER BY `id_wipop_recurring_charge` DESC',
            _DB_PREFIX_,
            $orderId
        );
        $row = $this->db->getRow($sql);

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function createInitialized(
        int $subscriptionId,
        int $sequence,
        int $paymentAttemptId,
        int $cartId,
        string $wipopOrderId,
    ): RecurringCharge {
        $existing = $this->findBySubscriptionSequence($subscriptionId, $sequence);
        if ($existing !== null) {
            return $existing;
        }

        $now = $this->currentDateTime();
        if (
            !$this->db->insert('wipop_recurring_charge', [
                'id_wipop_recurring_subscription' => $subscriptionId,
                'sequence' => $sequence,
                'id_wipop_payment_attempt' => $paymentAttemptId,
                'id_cart' => $cartId,
                'wipop_order_id' => pSQL($wipopOrderId),
                'status' => pSQL(RecurringChargeStatus::INITIALIZED->value),
                'date_add' => pSQL($now),
                'date_upd' => pSQL($now),
            ])
        ) {
            throw new RuntimeException('Unable to create Wipop recurring charge.');
        }

        $chargeId = (int) $this->db->Insert_ID();
        $charge = $this->findBySubscriptionSequence($subscriptionId, $sequence);
        if ($charge === null || $charge->id !== $chargeId) {
            throw new RuntimeException('Unable to retrieve Wipop recurring charge.');
        }

        return $charge;
    }

    public function markSubmitted(int $chargeId, ?string $transactionId): void
    {
        $data = [
            'status' => pSQL(RecurringChargeStatus::SUBMITTED->value),
            'date_upd' => pSQL($this->currentDateTime()),
        ];
        $transactionId = $this->nullableString($transactionId);
        if ($transactionId !== null) {
            $data['wipop_transaction_id'] = pSQL($transactionId);
        }

        $this->update($chargeId, $data);
    }

    public function markPaid(int $chargeId, int $orderId, string $transactionId): void
    {
        $this->update($chargeId, [
            'id_order' => $orderId,
            'wipop_transaction_id' => pSQL($transactionId),
            'status' => pSQL(RecurringChargeStatus::PAID->value),
            'date_upd' => pSQL($this->currentDateTime()),
        ]);
    }

    public function markFailed(
        int $chargeId,
        ?string $errorCode,
        ?string $errorMessage,
        ?string $transactionId = null,
    ): void {
        $data = [
            'status' => pSQL(RecurringChargeStatus::FAILED->value),
            'remote_error_code' => $this->nullableSqlString($errorCode),
            'remote_error_message' => $this->nullableSqlString($errorMessage),
            'date_upd' => pSQL($this->currentDateTime()),
        ];
        $transactionId = $this->nullableString($transactionId);
        if ($transactionId !== null) {
            $data['wipop_transaction_id'] = pSQL($transactionId);
        }

        $this->update($chargeId, $data);
    }

    /**
     * @param array<string, float|int|string|null> $data
     */
    private function update(int $chargeId, array $data): void
    {
        $condition = sprintf('id_wipop_recurring_charge = %d', $chargeId);
        if (!$this->db->update('wipop_recurring_charge', $data, $condition, 0, true)) {
            throw new RuntimeException('Unable to update Wipop recurring charge.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): RecurringCharge
    {
        return new RecurringCharge(
            (int) $row['id_wipop_recurring_charge'],
            (int) $row['id_wipop_recurring_subscription'],
            (int) $row['sequence'],
            $this->nullableInt($row['id_wipop_payment_attempt']),
            $this->nullableInt($row['id_cart']),
            $this->nullableInt($row['id_order']),
            (string) $row['wipop_order_id'],
            $this->nullableString($row['wipop_transaction_id']),
            RecurringChargeStatus::from((string) $row['status']),
            $this->nullableString($row['remote_error_code']),
            $this->nullableString($row['remote_error_message']),
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
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
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
