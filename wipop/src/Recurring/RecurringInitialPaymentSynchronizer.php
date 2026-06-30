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

use DateTimeImmutable;
use PrestaShop\Module\Wipop\Card\CardCofIntent;
use PrestaShop\Module\Wipop\Card\StoredCardRepository;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use Wipop\Domain\Transaction;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class RecurringInitialPaymentSynchronizer
{
    public function __construct(
        private RecurringSubscriptionRepository $subscriptions,
        private StoredCardRepository $storedCards,
        private RecurringDateCalculator $dateCalculator,
        private Logger $logger,
    ) {
    }

    public function sync(PaymentAttempt $attempt, Transaction $transaction, PaymentAttemptStatus $status): void
    {
        if ($status !== PaymentAttemptStatus::PAID || !$this->subscriptions->hasPendingForAttempt($attempt->id)) {
            return;
        }

        if ($attempt->orderId === null) {
            $this->logger->warning('Wipop recurring subscription pending without parent order.', [
                'attempt_id' => $attempt->id,
            ]);

            return;
        }

        $storedCardId = $attempt->storedCardId;
        if ($storedCardId === null && $attempt->cofIntent === CardCofIntent::SAVE) {
            $storedCardId = $this->storedCards->findActiveBySaveAttemptId($attempt->id)?->id;
        }

        if ($storedCardId === null) {
            $this->subscriptions->stopPendingForAttempt($attempt->id, $attempt->orderId);
            $this->logger->warning('Wipop recurring subscription could not be activated without stored card.', [
                'attempt_id' => $attempt->id,
                'order_id' => $attempt->orderId,
            ]);

            return;
        }

        $this->subscriptions->activateForAttempt(
            $attempt->id,
            $attempt->orderId,
            $storedCardId,
            $this->paidAt($transaction),
            $this->dateCalculator
        );
    }

    private function paidAt(Transaction $transaction): DateTimeImmutable
    {
        return $transaction->operationDate
            ?? $transaction->creationDate
            ?? new DateTimeImmutable();
    }
}
