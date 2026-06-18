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

use Cart;
use Currency;
use DateTimeImmutable;
use Db;
use PrestaShop\Module\Wipop\Card\CardCofIntent;
use PrestaShop\Module\Wipop\Card\CardCofSelection;
use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Configuration\Settings;
use RuntimeException;
use Wipop\Domain\Transaction;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PaymentAttemptRepository implements
    PaymentAttemptRefundMarkerInterface,
    PaymentAttemptPreauthorizationMarkerInterface
{
    public function __construct(
        private Db $db,
        private ?PaymentEventRepository $paymentEventRepository = null,
    ) {
    }

    public static function createDefault(): self
    {
        $db = Db::getInstance();

        return new self($db, new PaymentEventRepository($db));
    }

    public function createForCart(
        Cart $cart,
        string $paymentMethod,
        Settings $settings,
        ?CardCofSelection $cofSelection = null,
        ?CaptureMode $captureMode = null,
    ): int {
        $currency = new Currency((int) $cart->id_currency);
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $cofSelection ??= CardCofSelection::none();

        $data = [
            'id_cart' => (int) $cart->id,
            'id_customer' => (int) $cart->id_customer,
            'id_shop' => (int) $cart->id_shop,
            'merchant_id' => pSQL($settings->merchantId),
            'payment_method' => pSQL($paymentMethod),
            'capture_mode' => pSQL(($captureMode ?? $settings->captureMode)->value),
            'cof_intent' => pSQL($cofSelection->intent->value),
            'status' => pSQL(PaymentAttemptStatus::INITIALIZED->value),
            'amount' => round((float) $cart->getOrderTotal(true, Cart::BOTH), 2),
            'currency_iso' => pSQL((string) $currency->iso_code),
            'is_live' => (int) $settings->liveMode,
            'terminal_id' => (int) $settings->terminalId,
            'date_add' => pSQL($now),
            'date_upd' => pSQL($now),
        ];
        if ($cofSelection->storedCardId() !== null) {
            $data['id_wipop_stored_card'] = $cofSelection->storedCardId();
        }

        if (!$this->db->insert('wipop_payment_attempt', $data)) {
            throw new RuntimeException('Unable to create Wipop payment attempt.');
        }

        $attemptId = (int) $this->db->Insert_ID();
        if ($attemptId <= 0) {
            throw new RuntimeException('Unable to retrieve the Wipop payment attempt identifier.');
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

    public function markFailed(int $attemptId, ?string $transactionId = null): void
    {
        $data = [
            'status' => pSQL(PaymentAttemptStatus::FAILED->value),
            'date_upd' => pSQL($this->currentDateTime()),
        ];

        $transactionId = $this->nullableString($transactionId);
        if ($transactionId !== null) {
            $data['wipop_transaction_id'] = pSQL($transactionId);
        }

        $this->update($attemptId, $data);
    }

    public function markPaid(int $attemptId): void
    {
        $this->update($attemptId, [
            'status' => pSQL(PaymentAttemptStatus::PAID->value),
            'date_upd' => pSQL($this->currentDateTime()),
        ]);
    }

    public function markCancelled(int $attemptId): void
    {
        $this->update($attemptId, [
            'status' => pSQL(PaymentAttemptStatus::CANCELLED->value),
            'date_upd' => pSQL($this->currentDateTime()),
        ]);
    }

    public function markRefunded(int $attemptId): void
    {
        $this->update($attemptId, [
            'status' => pSQL(PaymentAttemptStatus::REFUNDED->value),
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
            throw new RuntimeException('Unable to update Wipop payment attempt.');
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
            $attempt = $this->findOneByColumn('wipop_transaction_id', $transactionId);
            if ($attempt !== null) {
                return $attempt;
            }

            $attemptId = $this->paymentEventRepository()->findAttemptIdByRemoteTransactionId($transactionId);
            if ($attemptId !== null) {
                return $this->findOneByColumn('id_wipop_payment_attempt', $attemptId);
            }
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

    public function findById(int $attemptId): ?PaymentAttempt
    {
        if ($attemptId <= 0) {
            return null;
        }

        return $this->findOneByColumn('id_wipop_payment_attempt', $attemptId);
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
            ORDER BY `id_wipop_payment_attempt` DESC',
            _DB_PREFIX_,
            $orderId
        );

        $row = $this->db->getRow($sql);
        if (!is_array($row)) {
            return null;
        }

        /** @var array{id_wipop_payment_attempt: int|string} $summaryRow */
        $summaryRow = $row;
        $refundSummary = $this->paymentEventRepository()
            ->findRefundSummaryByAttemptId((int) $summaryRow['id_wipop_payment_attempt']);

        return $this->mapSummaryRow($row, $refundSummary);
    }

    public function syncFromWebhook(
        PaymentAttempt $attempt,
        Transaction $transaction,
        PaymentAttemptStatus $status,
    ): void {
        $now = $this->currentDateTime();
        $status = $attempt->status->syncWithWebhookStatus($status);
        $data = [
            'status' => pSQL($status->value),
            'remote_status' => $this->nullableSqlString($transaction->status?->value),
            'remote_error_code' => $this->nullableSqlString($transaction->errorCode),
            'remote_error_message' => $this->nullableSqlString($transaction->errorMessage),
            'webhook_received_at' => pSQL($now),
            'date_upd' => pSQL($now),
        ];

        $transactionId = $this->nullableString($transaction->id);
        if ($transactionId !== null && $this->shouldStoreTransactionId($attempt, $transactionId)) {
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
                `id_customer`,
                `id_order`,
                `id_shop`,
                `merchant_id`,
                `wipop_transaction_id`,
                `payment_method`,
                `capture_mode`,
                `cof_intent`,
                `id_wipop_stored_card`,
                `status`,
                `amount`,
                `currency_iso`,
                `is_live`,
                `terminal_id`
            FROM `%swipop_payment_attempt`
            WHERE `%s` = %s
            ORDER BY `id_wipop_payment_attempt` DESC',
            _DB_PREFIX_,
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
            $this->nullableInt($row['id_order']),
            PaymentAttemptStatus::from((string) $row['status']),
            (float) $row['amount'],
            (string) $row['currency_iso'],
            $this->nullableString($row['payment_method']),
            CaptureMode::fromValue($row['capture_mode']),
            $this->nullableString($row['wipop_transaction_id']),
            (int) $row['id_shop'],
            (bool) $row['is_live'],
            (int) $row['terminal_id'],
            CardCofIntent::fromValue($row['cof_intent']),
            $this->nullableInt($row['id_wipop_stored_card']),
            (int) $row['id_customer'],
            (string) $row['merchant_id'],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapSummaryRow(array $row, ?PaymentRefundSummary $refundSummary): PaymentAttemptSummary
    {
        return new PaymentAttemptSummary(
            (int) $row['id_wipop_payment_attempt'],
            (int) $row['id_cart'],
            $this->nullableString($row['payment_method']),
            PaymentAttemptStatus::from((string) $row['status']),
            (float) $row['amount'],
            (string) $row['currency_iso'],
            $this->nullableString($row['wipop_order_id']),
            $this->nullableString($row['wipop_transaction_id']),
            $this->nullableString($row['remote_status']),
            $this->nullableString($row['remote_error_code']),
            $this->nullableString($row['remote_error_message']),
            $this->nullableString($row['webhook_received_at']),
            $refundSummary,
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

    private function shouldStoreTransactionId(PaymentAttempt $attempt, string $transactionId): bool
    {
        return $attempt->wipopTransactionId === null
            || $attempt->wipopTransactionId === $transactionId;
    }

    private function currentDateTime(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function paymentEventRepository(): PaymentEventRepository
    {
        if ($this->paymentEventRepository === null) {
            $this->paymentEventRepository = new PaymentEventRepository($this->db);
        }

        return $this->paymentEventRepository;
    }
}
