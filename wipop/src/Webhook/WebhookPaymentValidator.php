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

use Cart;
use Currency;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Payment\PaymentAmount;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use Wipop\Domain\Transaction;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class WebhookPaymentValidator
{
    /**
     * @throws WebhookException
     */
    public function assertTransactionMatches(PaymentAttempt $attempt, Transaction $transaction): void
    {
        $transactionAmount = $transaction->amount;
        if ($transactionAmount === null) {
            throw new WebhookException('Missing transaction amount for completed payment.', 400);
        }

        if (!$this->amountsMatch($attempt->amount, $transactionAmount)) {
            throw new WebhookException('Webhook amount does not match the local Wipop payment attempt.', 400);
        }

        $attemptCurrency = $this->normalizeCurrency($attempt->currencyIso);
        if ($attemptCurrency === '') {
            throw new WebhookException('Missing currency on the local Wipop payment attempt.', 400);
        }

        $transactionCurrency = $this->normalizeCurrency($transaction->currency);
        if ($transactionCurrency !== '' && $transactionCurrency !== $attemptCurrency) {
            throw new WebhookException('Webhook currency does not match the local Wipop payment attempt.', 400);
        }
    }

    /**
     * @throws WebhookException
     */
    public function assertRefundMatches(PaymentAttempt $attempt, PaymentEventData $refund): void
    {
        if ($refund->amount === null || PaymentAmount::toCents($refund->amount) <= 0) {
            throw new WebhookException('Missing refund amount for completed refund.', 400);
        }

        $attemptCurrency = $this->normalizeCurrency($attempt->currencyIso);
        if ($attemptCurrency === '') {
            throw new WebhookException('Missing currency on the local Wipop payment attempt.', 400);
        }

        $refundCurrency = $this->normalizeCurrency($refund->currencyIso);
        if ($refundCurrency === '') {
            throw new WebhookException('Missing refund currency for completed refund.', 400);
        }

        if ($refundCurrency !== $attemptCurrency) {
            throw new WebhookException('Refund currency does not match the local Wipop payment attempt.', 400);
        }
    }

    /**
     * @throws WebhookException
     */
    public function assertCartMatches(PaymentAttempt $attempt, Cart $cart): void
    {
        $attemptCurrency = $this->normalizeCurrency($attempt->currencyIso);
        if ($attemptCurrency === '') {
            throw new WebhookException('Missing currency on the local Wipop payment attempt.', 400);
        }

        $cartAmount = (float) $cart->getOrderTotal(true, Cart::BOTH);
        if (!$this->amountsMatch($attempt->amount, $cartAmount)) {
            throw new WebhookException('Cart amount no longer matches the Wipop payment attempt.', 400);
        }

        $cartCurrency = $this->cartCurrencyIso($cart);
        if ($cartCurrency !== $attemptCurrency) {
            throw new WebhookException('Cart currency no longer matches the Wipop payment attempt.', 400);
        }
    }

    private function amountsMatch(float $expected, float $actual): bool
    {
        return PaymentAmount::matches($expected, $actual);
    }

    private function cartCurrencyIso(Cart $cart): string
    {
        $currency = new Currency((int) $cart->id_currency);

        return $this->normalizeCurrency($currency->iso_code);
    }

    private function normalizeCurrency(?string $currency): string
    {
        return strtoupper(trim((string) $currency));
    }
}
