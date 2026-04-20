<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

use PrestaShop\Module\Wipop\Configuration\Settings;

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
            'amount' => (float) $cart->getOrderTotal(true, \Cart::BOTH),
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
     * @param array<string, int|string> $data
     */
    private function update(int $attemptId, array $data): void
    {
        $condition = sprintf('id_wipop_payment_attempt = %d', $attemptId);

        if (!$this->db->update('wipop_payment_attempt', $data, $condition)) {
            throw new \RuntimeException('Unable to update Wipop payment attempt.');
        }
    }

    private function currentDateTime(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
