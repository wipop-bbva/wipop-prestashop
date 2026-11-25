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

namespace PrestaShop\Module\Wipop\Payment;

use DateTimeImmutable;
use Db;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationOperationStatus;
use RuntimeException;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PaymentEventRepository implements RefundEventStoreInterface, PreauthorizationEventStoreInterface
{
    public function __construct(private Db $db)
    {
    }

    public static function createDefault(): self
    {
        return new self(Db::getInstance());
    }

    public function save(PaymentAttempt $attempt, PaymentEventData $event): bool
    {
        $exists = $this->eventExists($event->remoteTransactionId, $event->transactionType);
        $this->saveEvent($attempt, $event);

        return !$exists;
    }

    public function saveManualRefund(PaymentAttempt $attempt, PaymentEventData $event, int $employeeId): void
    {
        $this->saveEvent($attempt, $event, $employeeId);
    }

    public function savePreauthorizationOperation(
        PaymentAttempt $attempt,
        PaymentEventData $event,
        ?int $employeeId,
    ): void {
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

    private function eventExists(string $remoteTransactionId, string $transactionType): bool
    {
        $sql = sprintf(
            'SELECT 1
            FROM `%swipop_payment_event`
            WHERE `remote_transaction_id` = %s
                AND `transaction_type` = %s',
            _DB_PREFIX_,
            $this->sqlString($remoteTransactionId),
            $this->sqlString($transactionType)
        );

        return (bool) $this->db->getValue($sql);
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

        $amount = $this->db->getValue($sql);
        if ($amount === false || $amount === null) {
            throw new RuntimeException('Unable to read Wipop completed refund total.');
        }

        return PaymentAmount::toCents((float) $amount);
    }

    public function hasSuccessfulPreauthorizationEvent(int $attemptId, string $eventType): bool
    {
        $successfulStatuses = PreauthorizationOperationStatus::successfulStatuses($eventType);
        if ($successfulStatuses === []) {
            return false;
        }

        $sql = sprintf(
            'SELECT 1
            FROM `%swipop_payment_event`
            WHERE `id_wipop_payment_attempt` = %d
                AND `transaction_type` = %s
                AND `status` IN (%s)',
            _DB_PREFIX_,
            $attemptId,
            $this->sqlString($eventType),
            implode(', ', array_map(
                fn (string $status): string => $this->sqlString($status),
                $successfulStatuses
            ))
        );

        return (bool) $this->db->getValue($sql);
    }

    public function findLatestCompletedCaptureTransactionId(int $attemptId): ?string
    {
        $sql = sprintf(
            'SELECT `remote_transaction_id`
            FROM `%swipop_payment_event`
            WHERE `id_wipop_payment_attempt` = %d
                AND `transaction_type` = %s
                AND `status` = %s
            ORDER BY `received_at` DESC, `id_wipop_payment_event` DESC',
            _DB_PREFIX_,
            $attemptId,
            $this->sqlString(PaymentEventData::TYPE_CAPTURE),
            $this->sqlString('COMPLETED')
        );

        return $this->nullableString($this->db->getValue($sql));
    }

    public function findAttemptIdByRemoteTransactionId(string $remoteTransactionId): ?int
    {
        $remoteTransactionId = $this->nullableString($remoteTransactionId);
        if ($remoteTransactionId === null) {
            return null;
        }

        $sql = sprintf(
            'SELECT `id_wipop_payment_attempt`
            FROM `%swipop_payment_event`
            WHERE `remote_transaction_id` = %s
            ORDER BY `id_wipop_payment_event` DESC',
            _DB_PREFIX_,
            $this->sqlString($remoteTransactionId)
        );

        $attemptId = (int) $this->db->getValue($sql);

        return $attemptId > 0 ? $attemptId : null;
    }

    /**
     * @return array<int, array<string, float|int|string|null>>
     */
    public function findPreauthorizationEventsByAttemptId(int $attemptId): array
    {
        $sql = sprintf(
            'SELECT
                `remote_transaction_id`,
                `parent_transaction_id`,
                `transaction_type`,
                `operation_type`,
                `status`,
                `amount`,
                `currency_iso`,
                `remote_error_code`,
                `remote_error_message`,
                `received_at`
            FROM `%swipop_payment_event`
            WHERE `id_wipop_payment_attempt` = %d
                AND `transaction_type` IN (%s, %s)
            ORDER BY `received_at` DESC, `id_wipop_payment_event` DESC
            LIMIT 10',
            _DB_PREFIX_,
            $attemptId,
            $this->sqlString(PaymentEventData::TYPE_CAPTURE),
            $this->sqlString(PaymentEventData::TYPE_REVERSAL)
        );

        $rows = $this->db->executeS($sql);

        return is_array($rows) ? $rows : [];
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
            $this->normalizeCurrency($totals['currency'] ?? $latestRefund['currency_iso']),
            $totals['count'],
            $this->nullableString($latestRefund['remote_transaction_id']),
            $this->nullableString($latestRefund['status']),
            $this->nullableFloat($latestRefund['amount']),
            $this->nullableString($latestRefund['currency_iso']),
            $this->nullableString($latestRefund['received_at']),
            $this->nullableString($latestRefund['remote_error_code']),
            $this->nullableString($latestRefund['remote_error_message']),
            $this->positiveInt($latestRefund['id_order_slip']),
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
        $updates = array_values(array_diff($columns, ['date_add']));

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
            ORDER BY `received_at` DESC, `id_wipop_payment_event` DESC',
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

        /** @var array{refunded_amount: int|float|string, refund_count: int|string, currency_iso: string|null} $totalsRow */
        $totalsRow = $row;

        return [
            'amount' => (float) $totalsRow['refunded_amount'],
            'count' => (int) $totalsRow['refund_count'],
            'currency' => $this->nullableString($totalsRow['currency_iso']),
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
