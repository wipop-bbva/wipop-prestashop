<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

use PrestaShop\Module\Wipop\Checkout\PaymentMethod;
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
        PaymentMethod $paymentMethod,
        Settings $settings,
        string $wipopOrderId,
    ): int {
        $currency = new \Currency((int) $cart->id_currency);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $data = [
            'id_cart' => (int) $cart->id,
            'id_shop' => (int) $cart->id_shop,
            'wipop_order_id' => pSQL($wipopOrderId),
            'payment_method' => pSQL($paymentMethod->value),
            'status' => pSQL(PaymentAttemptStatus::INITIALIZED->value),
            'amount' => (float) $cart->getOrderTotal(true, \Cart::BOTH),
            'currency_iso' => pSQL((string) $currency->iso_code),
            'is_live' => (int) $settings->liveMode,
            'terminal_id' => (int) $settings->terminalId,
            'date_add' => pSQL($now),
            'date_upd' => pSQL($now),
        ];

        $this->db->insert('wipop_payment_attempt', $data);

        return (int) $this->db->Insert_ID();
    }
}
