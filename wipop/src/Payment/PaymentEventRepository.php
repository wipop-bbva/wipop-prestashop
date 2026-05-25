<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

use DateTimeImmutable;
use Db;
use RuntimeException;

final class PaymentEventRepository implements RefundEventStoreInterface
{
    public function __construct(private Db $db)
    {
    }

    public static function createDefault(): self
    {
        return new self(Db::getInstance());
    }

    public function save(PaymentAttempt $attempt, PaymentEventData $event): void
    {
        $this->saveEvent($attempt, $event);
    }

    public function saveManualRefund(PaymentAttempt $attempt, PaymentEventData $event, int $employeeId): void
    {
        $this->saveEvent($attempt, $event, $employeeId);
    }

    public function linkOrderSlip(string $remoteTransactionId, int $orderSlipId): void
    {
        $condition = sprintf(
            'remote_transaction_id = %s AND transaction_type = %s',
            $this->sqlString($remoteTransactionId),
            $this->sqlString(PaymentEventData::TYPE_REFUND)
        );

        $updated = $this->db->update(
            'wipop_payment_event',
            [
                'id_order_slip' => $orderSlipId,
                'date_upd' => pSQL($this->currentDateTime()),
            ],
            $condition,
            0,
            true
        );

        if (!$updated) {
            throw new RuntimeException('Unable to link Wipop payment event to the order slip.');
        }
    }

    private function saveEvent(PaymentAttempt $attempt, PaymentEventData $event, ?int $employeeId = null): void
    {
        $now = $this->currentDateTime();
        $data = $this->eventData($attempt, $event, $now, $employeeId);
        $data['date_add'] = $now;
        $data['date_upd'] = $now;

        if (!$this->db->execute($this->upsertEventSql($data))) {
            throw new RuntimeException('Unable to save Wipop payment event.');
        }
    }

    public function completedRefundedCentsForAttempt(
        int $attemptId,
        string $currencyIso,
        ?string $excludedRemoteTransactionId = null,
    ): int {
        $conditions = [
            sprintf('`id_wipop_payment_attempt` = %d', $attemptId),
            sprintf('`transaction_type` = %s', $this->sqlString(PaymentEventData::TYPE_REFUND)),
            sprintf('`status` = %s', $this->sqlString('COMPLETED')),
            sprintf('`currency_iso` = %s', $this->sqlString($this->normalizeCurrency($currencyIso))),
            '`amount` IS NOT NULL',
        ];

        $excludedRemoteTransactionId = $this->nullableString($excludedRemoteTransactionId);
        if ($excludedRemoteTransactionId !== null) {
            $conditions[] = sprintf('`remote_transaction_id` != %s', $this->sqlString($excludedRemoteTransactionId));
        }

        $sql = sprintf(
            'SELECT COALESCE(SUM(`amount`), 0)
            FROM `%swipop_payment_event`
            WHERE %s',
            _DB_PREFIX_,
            implode(' AND ', $conditions)
        );

        return PaymentAmount::toCents((float) $this->db->getValue($sql));
    }

    public function findRefundSummaryByAttemptId(int $attemptId): ?PaymentRefundSummary
    {
        $latestRefund = $this->findLatestRefundRow($attemptId);
        if ($latestRefund === null) {
            return null;
        }

        $totals = $this->refundTotals($attemptId);

        return new PaymentRefundSummary(
            $totals['amount'],
            $this->normalizeCurrency($totals['currency'] ?? $latestRefund['currency_iso'] ?? ''),
            $totals['count'],
            $this->nullableString($latestRefund['remote_transaction_id'] ?? null),
            $this->nullableString($latestRefund['status'] ?? null),
            $this->nullableFloat($latestRefund['amount'] ?? null),
            $this->nullableString($latestRefund['currency_iso'] ?? null),
            $this->nullableString($latestRefund['received_at'] ?? null),
            $this->nullableString($latestRefund['remote_error_code'] ?? null),
            $this->nullableString($latestRefund['remote_error_message'] ?? null),
            $this->positiveInt($latestRefund['id_order_slip'] ?? null),
        );
    }

    /**
     * @return array<string, float|int|string|null>
     */
    private function eventData(
        PaymentAttempt $attempt,
        PaymentEventData $event,
        string $receivedAt,
        ?int $employeeId = null,
    ): array {
        $data = [
            'id_wipop_payment_attempt' => (int) $attempt->id,
            'id_order' => $attempt->orderId,
            'remote_transaction_id' => $event->remoteTransactionId,
            'parent_transaction_id' => $this->nullableString($event->parentTransactionId),
            'wipop_order_id' => $this->nullableString($event->wipopOrderId),
            'transaction_type' => $event->transactionType,
            'operation_type' => $this->nullableString($event->operationType),
            'status' => $this->nullableString($event->status),
            'amount' => $event->amount,
            'currency_iso' => $this->nullableString($this->normalizeCurrency($event->currencyIso)),
            'remote_error_code' => $this->nullableString($event->remoteErrorCode),
            'remote_error_message' => $this->nullableString($event->remoteErrorMessage),
            'received_at' => $receivedAt,
        ];

        if ($employeeId !== null) {
            $data['id_employee'] = $employeeId;
        }

        return $data;
    }

    /**
     * @param array<string, float|int|string|null> $data
     */
    private function upsertEventSql(array $data): string
    {
        $columns = array_keys($data);
        $updates = array_filter(
            $columns,
            static fn (string $column): bool => $column !== 'date_add'
        );

        return sprintf(
            'INSERT INTO `%swipop_payment_event` (%s)
            VALUES (%s)
            ON DUPLICATE KEY UPDATE %s',
            _DB_PREFIX_,
            implode(', ', array_map([$this, 'sqlColumn'], $columns)),
            implode(', ', array_map([$this, 'sqlValue'], $data)),
            implode(', ', array_map(
                fn (string $column): string => sprintf(
                    '%s = VALUES(%s)',
                    $this->sqlColumn($column),
                    $this->sqlColumn($column)
                ),
                $updates
            ))
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findLatestRefundRow(int $attemptId): ?array
    {
        $sql = sprintf(
            'SELECT
                `remote_transaction_id`,
                `status`,
                `amount`,
                `currency_iso`,
                `received_at`,
                `remote_error_code`,
                `remote_error_message`,
                `id_order_slip`
            FROM `%swipop_payment_event`
            WHERE `id_wipop_payment_attempt` = %d
                AND `transaction_type` = %s
            ORDER BY `received_at` DESC, `id_wipop_payment_event` DESC
            LIMIT 1',
            _DB_PREFIX_,
            $attemptId,
            $this->sqlString(PaymentEventData::TYPE_REFUND)
        );

        $row = $this->db->getRow($sql);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{amount: float, count: int, currency: ?string}
     */
    private function refundTotals(int $attemptId): array
    {
        $sql = sprintf(
            'SELECT
                COALESCE(SUM(`amount`), 0) AS `refunded_amount`,
                COUNT(*) AS `refund_count`,
                MAX(`currency_iso`) AS `currency_iso`
            FROM `%swipop_payment_event`
            WHERE `id_wipop_payment_attempt` = %d
                AND `transaction_type` = %s
                AND `status` = %s
                AND `amount` IS NOT NULL',
            _DB_PREFIX_,
            $attemptId,
            $this->sqlString(PaymentEventData::TYPE_REFUND),
            $this->sqlString('COMPLETED')
        );

        $row = $this->db->getRow($sql);
        if (!is_array($row)) {
            return ['amount' => 0.0, 'count' => 0, 'currency' => null];
        }

        return [
            'amount' => (float) ($row['refunded_amount'] ?? 0),
            'count' => (int) ($row['refund_count'] ?? 0),
            'currency' => $this->nullableString($row['currency_iso'] ?? null),
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeCurrency(?string $currency): string
    {
        return strtoupper(trim((string) $currency));
    }

    private function sqlString(string $value): string
    {
        return sprintf('\'%s\'', pSQL($value));
    }

    private function sqlColumn(string $column): string
    {
        return sprintf('`%s`', $column);
    }

    private function sqlValue(float|int|string|null $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return number_format($value, 6, '.', '');
        }

        return $this->sqlString($value);
    }

    private function currentDateTime(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
