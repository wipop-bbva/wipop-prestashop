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

namespace PrestaShop\Module\Wipop\Recurring;

use Cart;
use Currency;
use DateTimeImmutable;
use Db;
use PrestaShop\Module\Wipop\Configuration\Settings;
use RuntimeException;
use Throwable;

use function pSQL;
use function sprintf;
use function trim;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class RecurringSubscriptionRepository
{
    public function __construct(private Db $db)
    {
    }

    public static function createDefault(): self
    {
        return new self(Db::getInstance());
    }

    /**
     * @param RecurringCartGroup[] $groups
     */
    public function createPendingFromCart(
        int $attemptId,
        Cart $cart,
        Settings $settings,
        ?int $storedCardId,
        array $groups,
    ): void {
        if ($attemptId <= 0 || $groups === []) {
            return;
        }

        $currency = new Currency((int) $cart->id_currency);
        $now = $this->currentDateTime();

        if (!$this->db->execute('START TRANSACTION', false)) {
            throw new RuntimeException('Unable to start Wipop recurring subscription creation.');
        }

        try {
            foreach ($groups as $group) {
                $data = [
                    'id_initial_payment_attempt' => $attemptId,
                    'id_customer' => (int) $cart->id_customer,
                    'id_shop' => (int) $cart->id_shop,
                    'merchant_id' => pSQL($settings->merchantId),
                    'is_live' => (int) $settings->liveMode,
                    'terminal_id' => (int) $settings->terminalId,
                    'id_currency' => (int) $cart->id_currency,
                    'id_lang' => (int) $cart->id_lang,
                    'id_address_invoice' => (int) $cart->id_address_invoice,
                    'id_address_delivery' => (int) $cart->id_address_delivery,
                    'period' => pSQL($group->period->value),
                    'status' => pSQL(RecurringSubscriptionStatus::PENDING_INITIAL_PAYMENT->value),
                    'amount' => $group->amount,
                    'currency_iso' => pSQL((string) $currency->iso_code),
                    'date_add' => pSQL($now),
                    'date_upd' => pSQL($now),
                ];
                if ($storedCardId !== null) {
                    $data['id_wipop_stored_card'] = $storedCardId;
                }

                if (!$this->db->insert('wipop_recurring_subscription', $data)) {
                    throw new RuntimeException('Unable to create Wipop recurring subscription.');
                }

                $subscriptionId = (int) $this->db->Insert_ID();
                if ($subscriptionId <= 0) {
                    throw new RuntimeException('Unable to retrieve Wipop recurring subscription id.');
                }

                foreach ($group->items as $item) {
                    if (
                        !$this->db->insert('wipop_recurring_subscription_item', [
                            'id_wipop_recurring_subscription' => $subscriptionId,
                            'id_product' => $item->productId,
                            'id_product_attribute' => $item->productAttributeId,
                            'quantity' => $item->quantity,
                        ])
                    ) {
                        throw new RuntimeException('Unable to create Wipop recurring subscription item.');
                    }
                }
            }

            if (!$this->db->execute('COMMIT', false)) {
                throw new RuntimeException('Unable to commit Wipop recurring subscription creation.');
            }
        } catch (Throwable $throwable) {
            $this->db->execute('ROLLBACK', false);

            throw $throwable;
        }
    }

    public function hasPendingForAttempt(int $attemptId): bool
    {
        if ($attemptId <= 0) {
            return false;
        }

        $sql = sprintf(
            'SELECT COUNT(*)
            FROM `%swipop_recurring_subscription`
            WHERE `id_initial_payment_attempt` = %d
              AND `status` = \'%s\'',
            _DB_PREFIX_,
            $attemptId,
            pSQL(RecurringSubscriptionStatus::PENDING_INITIAL_PAYMENT->value)
        );

        $count = $this->db->getValue($sql);
        if ($count === false || $count === null) {
            throw new RuntimeException('Unable to read pending Wipop recurring subscriptions.');
        }

        return (int) $count > 0;
    }

    public function activateForAttempt(
        int $attemptId,
        int $parentOrderId,
        int $storedCardId,
        DateTimeImmutable $paidAt,
        RecurringDateCalculator $dateCalculator,
    ): void {
        if ($attemptId <= 0 || $parentOrderId <= 0 || $storedCardId <= 0) {
            return;
        }

        $subscriptions = $this->findPendingByAttemptId($attemptId);
        $now = $this->currentDateTime();
        foreach ($subscriptions as $subscription) {
            $nextAttemptAt = $dateCalculator->next($paidAt, $subscription->period)->format('Y-m-d H:i:s');
            $this->update($subscription->id, [
                'id_parent_order' => $parentOrderId,
                'id_wipop_stored_card' => $storedCardId,
                'status' => pSQL(RecurringSubscriptionStatus::ACTIVE->value),
                'next_attempt_at' => pSQL($nextAttemptAt),
                'date_upd' => pSQL($now),
            ]);
        }
    }

    /**
     * @return int[]
     */
    public function findDueIds(int $limit): array
    {
        $limit = max(1, $limit);
        $sql = sprintf(
            'SELECT `id_wipop_recurring_subscription`
            FROM `%swipop_recurring_subscription`
            WHERE `status` = \'%s\'
              AND `next_attempt_at` IS NOT NULL
              AND `next_attempt_at` <= \'%s\'
            ORDER BY `next_attempt_at` ASC, `id_wipop_recurring_subscription` ASC
            LIMIT %d',
            _DB_PREFIX_,
            pSQL(RecurringSubscriptionStatus::ACTIVE->value),
            pSQL($this->currentDateTime()),
            $limit
        );

        $rows = $this->requiredRows($sql);

        return array_map('intval', array_column($rows, 'id_wipop_recurring_subscription'));
    }

    public function findById(int $subscriptionId): ?RecurringSubscription
    {
        if ($subscriptionId <= 0) {
            return null;
        }

        $sql = sprintf(
            'SELECT * FROM `%swipop_recurring_subscription`
            WHERE `id_wipop_recurring_subscription` = %d',
            _DB_PREFIX_,
            $subscriptionId
        );
        $row = $this->db->getRow($sql);

        return is_array($row) ? $this->mapRowWithItems($row) : null;
    }

    public function findByParentOrderId(int $orderId): ?RecurringSubscription
    {
        if ($orderId <= 0) {
            return null;
        }

        $sql = sprintf(
            'SELECT *
            FROM `%swipop_recurring_subscription`
            WHERE `id_parent_order` = %d
            ORDER BY `id_wipop_recurring_subscription` ASC',
            _DB_PREFIX_,
            $orderId
        );
        $row = $this->db->getRow($sql);

        return is_array($row) ? $this->mapRowWithItems($row) : null;
    }

    /**
     * @return RecurringSubscription[]
     */
    public function findActiveByParentOrderId(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        $sql = sprintf(
            'SELECT *
            FROM `%swipop_recurring_subscription`
            WHERE `id_parent_order` = %d
              AND `status` = \'%s\'
            ORDER BY `id_wipop_recurring_subscription` ASC',
            _DB_PREFIX_,
            $orderId,
            pSQL(RecurringSubscriptionStatus::ACTIVE->value)
        );

        return $this->mapRowsWithItems($this->requiredRows($sql));
    }

    public function stop(int $subscriptionId): void
    {
        $this->update($subscriptionId, [
            'status' => pSQL(RecurringSubscriptionStatus::STOPPED->value),
            'next_attempt_at' => null,
            'date_upd' => pSQL($this->currentDateTime()),
        ]);
    }

    public function stopPendingForAttempt(int $attemptId, int $parentOrderId): void
    {
        if ($attemptId <= 0 || $parentOrderId <= 0) {
            return;
        }

        $now = $this->currentDateTime();
        foreach ($this->findPendingByAttemptId($attemptId) as $subscription) {
            $this->update($subscription->id, [
                'id_parent_order' => $parentOrderId,
                'status' => pSQL(RecurringSubscriptionStatus::STOPPED->value),
                'next_attempt_at' => null,
                'date_upd' => pSQL($now),
            ]);
        }
    }

    public function advance(
        RecurringSubscription $subscription,
        string $transactionId,
        RecurringDateCalculator $dateCalculator,
    ): void {
        $nextAttemptAt = $dateCalculator
            ->next(new DateTimeImmutable((string) $subscription->nextAttemptAt), $subscription->period)
            ->format('Y-m-d H:i:s');

        $this->update($subscription->id, [
            'next_sequence' => $subscription->nextSequence + 1,
            'next_attempt_at' => pSQL($nextAttemptAt),
            'last_transaction_id' => pSQL($transactionId),
            'date_upd' => pSQL($this->currentDateTime()),
        ]);
    }

    /**
     * @param array<string, float|int|string|null> $data
     */
    private function update(int $subscriptionId, array $data): void
    {
        if ($subscriptionId <= 0) {
            return;
        }

        $condition = sprintf('id_wipop_recurring_subscription = %d', $subscriptionId);
        if (!$this->db->update('wipop_recurring_subscription', $data, $condition, 0, true)) {
            throw new RuntimeException('Unable to update Wipop recurring subscription.');
        }
    }

    /**
     * @return RecurringSubscription[]
     */
    private function findPendingByAttemptId(int $attemptId): array
    {
        if ($attemptId <= 0) {
            return [];
        }

        $sql = sprintf(
            'SELECT *
            FROM `%swipop_recurring_subscription`
            WHERE `id_initial_payment_attempt` = %d
              AND `status` = \'%s\'
            ORDER BY `id_wipop_recurring_subscription` ASC',
            _DB_PREFIX_,
            $attemptId,
            pSQL(RecurringSubscriptionStatus::PENDING_INITIAL_PAYMENT->value)
        );

        return $this->mapRowsWithItems($this->requiredRows($sql));
    }

    /**
     * @return RecurringSubscription[]
     */
    private function mapRowsWithItems(array $rows): array
    {
        $subscriptions = [];
        /** @var list<array<string, mixed>> $rows */
        foreach ($rows as $row) {
            $subscriptions[] = $this->mapRowWithItems($row);
        }

        return $subscriptions;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowWithItems(array $row): RecurringSubscription
    {
        return $this->mapRow($row, $this->findItems((int) $row['id_wipop_recurring_subscription']));
    }

    /**
     * @return RecurringSubscriptionItem[]
     */
    private function findItems(int $subscriptionId): array
    {
        $sql = sprintf(
            'SELECT *
            FROM `%swipop_recurring_subscription_item`
            WHERE `id_wipop_recurring_subscription` = %d
            ORDER BY `id_wipop_recurring_subscription_item` ASC',
            _DB_PREFIX_,
            $subscriptionId
        );
        $items = [];
        foreach ($this->requiredRows($sql) as $row) {
            $items[] = new RecurringSubscriptionItem(
                (int) $row['id_product'],
                (int) $row['id_product_attribute'],
                (int) $row['quantity']
            );
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requiredRows(string $sql): array
    {
        $rows = $this->db->executeS($sql);
        if (!is_array($rows)) {
            throw new RuntimeException('Unable to read Wipop recurring subscriptions.');
        }

        /* @var list<array<string, mixed>> $rows */
        return $rows;
    }

    /**
     * @param RecurringSubscriptionItem[] $items
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row, array $items): RecurringSubscription
    {
        return new RecurringSubscription(
            (int) $row['id_wipop_recurring_subscription'],
            (int) $row['id_initial_payment_attempt'],
            $this->positiveIntOrNull($row['id_parent_order']),
            (int) $row['id_customer'],
            (int) $row['id_shop'],
            (string) $row['merchant_id'],
            (bool) $row['is_live'],
            (int) $row['terminal_id'],
            (int) $row['id_currency'],
            (int) $row['id_lang'],
            (int) $row['id_address_invoice'],
            (int) $row['id_address_delivery'],
            $this->positiveIntOrNull($row['id_wipop_stored_card']),
            RecurringPeriod::from((string) $row['period']),
            RecurringSubscriptionStatus::from((string) $row['status']),
            (float) $row['amount'],
            (string) $row['currency_iso'],
            (int) $row['next_sequence'],
            $this->nullableString($row['next_attempt_at']),
            $this->nullableString($row['last_transaction_id']),
            $items
        );
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
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
