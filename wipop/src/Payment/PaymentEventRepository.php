<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

use DateTimeImmutable;
use Db;
use RuntimeException;

final class PaymentEventRepository
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
        $eventId = $this->findIdByRemoteEvent($event->remoteTransactionId, $event->transactionType);
        $now = $this->currentDateTime();
        $data = $this->eventData($attempt, $event, $now);
        $data['date_upd'] = pSQL($now);

        if ($eventId === null) {
            $data['date_add'] = $data['date_upd'];
            if (!$this->db->insert('wipop_payment_event', $data, true)) {
                throw new RuntimeException('Unable to create Wipop payment event.');
            }

            return;
        }

        $condition = sprintf('id_wipop_payment_event = %d', $eventId);
        if (!$this->db->update('wipop_payment_event', $data, $condition, 0, true)) {
            throw new RuntimeException('Unable to update Wipop payment event.');
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
            $this->dbPrefix(),
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
        );
    }

    /**
     * @return array<string, float|int|string|null>
     */
    private function eventData(PaymentAttempt $attempt, PaymentEventData $event, string $receivedAt): array
    {
        return [
            'id_wipop_payment_attempt' => (int) $attempt->id,
            'id_order' => $attempt->orderId,
            'remote_transaction_id' => pSQL($event->remoteTransactionId),
            'parent_transaction_id' => $this->nullableSqlString($event->parentTransactionId),
            'wipop_order_id' => $this->nullableSqlString($event->wipopOrderId),
            'transaction_type' => pSQL($event->transactionType),
            'operation_type' => $this->nullableSqlString($event->operationType),
            'status' => $this->nullableSqlString($event->status),
            'amount' => $event->amount,
            'currency_iso' => $this->nullableSqlString($this->normalizeCurrency($event->currencyIso)),
            'remote_error_code' => $this->nullableSqlString($event->remoteErrorCode),
            'remote_error_message' => $this->nullableSqlString($event->remoteErrorMessage),
            'received_at' => pSQL($receivedAt),
        ];
    }

    private function findIdByRemoteEvent(string $remoteTransactionId, string $transactionType): ?int
    {
        $sql = sprintf(
            'SELECT `id_wipop_payment_event`
            FROM `%swipop_payment_event`
            WHERE `remote_transaction_id` = %s
                AND `transaction_type` = %s
            LIMIT 1',
            $this->dbPrefix(),
            $this->sqlString($remoteTransactionId),
            $this->sqlString($transactionType)
        );

        $eventId = (int) $this->db->getValue($sql);

        return $eventId > 0 ? $eventId : null;
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
                `remote_error_message`
            FROM `%swipop_payment_event`
            WHERE `id_wipop_payment_attempt` = %d
                AND `transaction_type` = %s
            ORDER BY `received_at` DESC, `id_wipop_payment_event` DESC
            LIMIT 1',
            $this->dbPrefix(),
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
            $this->dbPrefix(),
            $attemptId,
            $this->sqlString(PaymentEventData::TYPE_REFUND),
            $this->sqlString('COMPLETED')
        );

        $row = $this->db->getRow($sql);
        if (!is_array($row)) {
            return ['amount' => 0.0, 'count' => 0, 'currency' => null];
        }

        return [
            'amount' => $this->nullableFloat($row['refunded_amount'] ?? null) ?? 0.0,
            'count' => $this->nullableInt($row['refund_count'] ?? null) ?? 0,
            'currency' => $this->nullableString($row['currency_iso'] ?? null),
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
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

    private function normalizeCurrency(?string $currency): string
    {
        return strtoupper(trim((string) $currency));
    }

    private function sqlString(string $value): string
    {
        return sprintf('\'%s\'', pSQL($value));
    }

    private function currentDateTime(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function dbPrefix(): string
    {
        $dbPrefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';

        return is_string($dbPrefix) ? $dbPrefix : 'ps_';
    }
}
