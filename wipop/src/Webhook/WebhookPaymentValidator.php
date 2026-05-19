<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use Cart;
use Currency;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use Wipop\Domain\Transaction;

final class WebhookPaymentValidator
{
    private const EUR_MINOR_UNITS = 100;

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
        if ($transactionCurrency === '') {
            throw new WebhookException('Missing transaction currency for completed payment.', 400);
        }

        if ($transactionCurrency !== $attemptCurrency) {
            throw new WebhookException('Webhook currency does not match the local Wipop payment attempt.', 400);
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
        return $this->toEurCents($expected) === $this->toEurCents($actual);
    }

    private function toEurCents(float $amount): int
    {
        return (int) round($amount * self::EUR_MINOR_UNITS);
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
