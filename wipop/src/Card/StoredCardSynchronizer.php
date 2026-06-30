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

namespace PrestaShop\Module\Wipop\Card;

use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use Wipop\Domain\Transaction;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class StoredCardSynchronizer
{
    public function __construct(private StoredCardRepository $storedCards)
    {
    }

    public function sync(PaymentAttempt $attempt, Transaction $transaction, PaymentAttemptStatus $status): void
    {
        if (!$this->isSuccessfulCardPayment($attempt, $status)) {
            return;
        }

        if ($attempt->cofIntent === CardCofIntent::USE_EXISTING) {
            $this->markExistingCardUsed($attempt);

            return;
        }

        if ($attempt->cofIntent !== CardCofIntent::SAVE || $transaction->card === null) {
            return;
        }

        $details = StoredCardDetails::fromWipopCard($transaction->card);
        if ($details === null) {
            return;
        }

        if ($attempt->customerId <= 0 || $attempt->merchantId === '') {
            return;
        }

        $this->storedCards->store(
            $attempt->customerId,
            $attempt->shopId,
            $attempt->merchantId,
            $attempt->isLive,
            $attempt->terminalId,
            $details,
            $transaction->customerId,
            $attempt->id
        );
    }

    private function markExistingCardUsed(PaymentAttempt $attempt): void
    {
        if ($attempt->storedCardId !== null) {
            $this->storedCards->markUsed($attempt->storedCardId);
        }
    }

    private function isSuccessfulCardPayment(PaymentAttempt $attempt, PaymentAttemptStatus $status): bool
    {
        return Settings::isCardMethod((string) $attempt->paymentMethod)
            && in_array($status, [PaymentAttemptStatus::PAID, PaymentAttemptStatus::AUTHORIZED], true);
    }
}
