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

use Context;
use GuzzleHttp\Exception\RequestException;
use Order;
use PrestaShop\Module\Wipop\Card\CardCofSelection;
use PrestaShop\Module\Wipop\Card\StoredCard;
use PrestaShop\Module\Wipop\Card\StoredCardRepository;
use PrestaShop\Module\Wipop\Checkout\CartOrderIdFactory;
use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Exception\SettingsReadException;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use Throwable;
use Validate;
use Wipop\Client\Exception\HttpTransportException;
use Wipop\Client\Exception\WipopApiTimeoutException;
use Wipop\Domain\Charge;
use Wipop\Domain\ChargeMethod;
use Wipop\Domain\TransactionStatus;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class RecurringChargeRunner implements RecurringDueChargeRunnerInterface
{
    public function __construct(
        private RecurringSubscriptionRepository $subscriptions,
        private RecurringChargeRepository $charges,
        private StoredCardRepository $storedCards,
        private SettingsRepository $settingsRepository,
        private PaymentAttemptRepository $paymentAttempts,
        private RecurringSubscriptionLock $subscriptionLock,
        private RecurringCartFactory $cartFactory,
        private WipopRecurringChargeClientInterface $wipopClient,
        private CartOrderIdFactory $orderIdFactory,
        private Logger $logger,
        private RecurringSubscriptionStopPolicy $stopPolicy,
    ) {
    }

    public function runDue(int $limit): int
    {
        $context = Context::getContext();
        $previousContext = [
            'cart' => $context->cart,
            'customer' => $context->customer,
            'shop' => $context->shop,
            'currency' => $context->currency,
            'language' => $context->language,
        ];

        try {
            return $this->processDue($limit);
        } finally {
            foreach ($previousContext as $property => $value) {
                $context->{$property} = $value;
            }
        }
    }

    private function processDue(int $limit): int
    {
        $processed = 0;

        foreach ($this->subscriptions->findDueIds($limit) as $subscriptionId) {
            try {
                if (
                    $this->subscriptionLock->run(
                        $subscriptionId,
                        fn (): bool => $this->processLockedSubscription($subscriptionId)
                    )
                ) {
                    ++$processed;
                }
            } catch (RecurringLockUnavailableException) {
                continue;
            } catch (Throwable $throwable) {
                $this->logger->error('Unexpected Wipop recurring charge error.', [
                    'subscription_id' => $subscriptionId,
                    'error_class' => $throwable::class,
                ]);
            }
        }

        return $processed;
    }

    private function processLockedSubscription(int $subscriptionId): bool
    {
        $subscription = $this->subscriptions->findById($subscriptionId);
        if ($subscription === null || $subscription->status !== RecurringSubscriptionStatus::ACTIVE) {
            return false;
        }

        if ($this->stopIfParentCannotRenew($subscription)) {
            return false;
        }

        $existingCharge = $this->charges->findBySubscriptionSequence($subscription->id, $subscription->nextSequence);
        if ($existingCharge !== null) {
            $this->stopBlockedSubscription($subscription, $existingCharge);

            return false;
        }

        $settings = $this->currentSettings();
        if ($settings === null || !$settings->isConfigured()) {
            $this->logger->warning('Wipop recurring charge skipped because settings are incomplete.', [
                'subscription_id' => $subscription->id,
            ]);

            return false;
        }

        if (!$this->settingsMatchSubscription($settings, $subscription)) {
            $this->stopSubscription(
                $subscription,
                'Wipop recurring subscription stopped after merchant settings changed.'
            );

            return false;
        }

        $storedCard = $this->storedCardFor($subscription);
        if ($storedCard === null) {
            $this->stopSubscription(
                $subscription,
                'Wipop recurring subscription stopped because its stored card is unavailable.'
            );

            return false;
        }

        try {
            $cart = $this->cartFactory->create($subscription);
        } catch (Throwable $throwable) {
            $this->stopSubscription(
                $subscription,
                'Wipop recurring subscription stopped because its renewal cart could not be created.'
            );
            $this->logger->error('Wipop recurring renewal cart creation failed.', [
                'subscription_id' => $subscription->id,
                'sequence' => $subscription->nextSequence,
                'error_class' => $throwable::class,
            ]);

            return false;
        }

        $attemptId = null;
        $charge = null;
        try {
            $cofSelection = CardCofSelection::useExisting($storedCard);
            $attemptId = $this->paymentAttempts->createForCart(
                $cart,
                ChargeMethod::CARD,
                $settings,
                $cofSelection,
                CaptureMode::IMMEDIATE,
            );
            $wipopOrderId = $this->orderIdFactory->fromCartAttempt($cart, $attemptId);
            $this->paymentAttempts->assignWipopOrderId($attemptId, $wipopOrderId);
            $charge = $this->charges->createInitialized(
                $subscription->id,
                $subscription->nextSequence,
                $attemptId,
                (int) $cart->id,
                $wipopOrderId,
            );
            $response = $this->wipopClient->create($cart, $settings, $wipopOrderId, $storedCard);
        } catch (Throwable $throwable) {
            if ($charge !== null && $attemptId !== null) {
                if ($this->submissionMayHaveReachedWipop($throwable)) {
                    $this->paymentAttempts->markCheckoutPending($attemptId, null);
                    $this->charges->markSubmitted($charge->id, null);
                } else {
                    $this->paymentAttempts->markFailed($attemptId);
                    $this->charges->markFailed(
                        $charge->id,
                        null,
                        'Submission failed before Wipop returned a transaction.'
                    );
                    $this->stopSubscription(
                        $subscription,
                        'Wipop recurring subscription stopped after renewal charge submission failed.'
                    );
                }
            } elseif ($attemptId !== null) {
                $this->paymentAttempts->markFailed($attemptId);
            }

            $this->logger->error('Wipop recurring charge submission failed.', [
                'subscription_id' => $subscription->id,
                'sequence' => $subscription->nextSequence,
                'error_class' => $throwable::class,
            ]);

            return false;
        }

        $transactionId = $this->transactionId($response);
        if ($this->isFailedResponse($response)) {
            $this->paymentAttempts->markFailed($attemptId, $transactionId);
            $this->charges->markFailed($charge->id, $response->errorCode, $response->errorMessage, $transactionId);
            $this->stopSubscription(
                $subscription,
                'Wipop recurring subscription stopped after a failed renewal charge.'
            );

            return false;
        }

        $this->paymentAttempts->markCheckoutPending($attemptId, $transactionId);
        $this->charges->markSubmitted($charge->id, $transactionId);
        $this->logger->info('Wipop recurring charge submitted.', [
            'subscription_id' => $subscription->id,
            'sequence' => $subscription->nextSequence,
            'attempt_id' => $attemptId,
            'cart_id' => (int) $cart->id,
            'wipop_order_id' => $wipopOrderId,
            'transaction_id' => $transactionId ?? '',
        ]);

        return true;
    }

    private function currentSettings(): ?Settings
    {
        try {
            return $this->settingsRepository->get();
        } catch (SettingsReadException $exception) {
            $this->logger->error('Unable to read Wipop settings for recurring payments.', [
                'error_class' => $exception::class,
            ]);

            return null;
        }
    }

    private function settingsMatchSubscription(Settings $settings, RecurringSubscription $subscription): bool
    {
        return $settings->merchantId === $subscription->merchantId
            && $settings->liveMode === $subscription->isLive
            && $settings->terminalId === $subscription->terminalId;
    }

    private function storedCardFor(RecurringSubscription $subscription): ?StoredCard
    {
        if ($subscription->storedCardId === null) {
            return null;
        }

        return $this->storedCards->findActiveByIdForCustomer(
            $subscription->storedCardId,
            $subscription->customerId,
            $subscription->shopId,
            $subscription->merchantId,
            $subscription->isLive,
            $subscription->terminalId,
        );
    }

    private function stopIfParentCannotRenew(RecurringSubscription $subscription): bool
    {
        if ($subscription->parentOrderId === null) {
            $this->stopSubscription(
                $subscription,
                'Wipop recurring subscription stopped because its parent order is missing.'
            );

            return true;
        }

        $order = new Order($subscription->parentOrderId);
        if (!Validate::isLoadedObject($order)) {
            $this->stopSubscription(
                $subscription,
                'Wipop recurring subscription stopped because its parent order is unavailable.',
                ['parent_order_id' => $subscription->parentOrderId]
            );

            return true;
        }

        if ($this->stopPolicy->shouldStopForParentOrderState((int) $order->current_state)) {
            $this->stopSubscription(
                $subscription,
                'Wipop recurring subscription stopped because its parent order is closed.',
                [
                    'parent_order_id' => $subscription->parentOrderId,
                    'order_state_id' => (int) $order->current_state,
                ]
            );

            return true;
        }

        $initialAttempt = $this->paymentAttempts->findById($subscription->initialPaymentAttemptId);
        if (
            $initialAttempt === null
            || $this->stopPolicy->shouldStopForInitialAttemptStatus($initialAttempt->status)
        ) {
            $this->stopSubscription(
                $subscription,
                'Wipop recurring subscription stopped because its initial payment is closed.',
                [
                    'parent_order_id' => $subscription->parentOrderId,
                    'initial_attempt_id' => $subscription->initialPaymentAttemptId,
                ]
            );

            return true;
        }

        return false;
    }

    private function isFailedResponse(Charge $charge): bool
    {
        return in_array($charge->status, [TransactionStatus::FAILED, TransactionStatus::ERROR], true);
    }

    private function transactionId(Charge $charge): ?string
    {
        $transactionId = trim((string) $charge->id);

        return $transactionId === '' ? null : $transactionId;
    }

    private function stopBlockedSubscription(RecurringSubscription $subscription, RecurringCharge $charge): void
    {
        if ($charge->status === RecurringChargeStatus::FAILED) {
            $this->stopSubscription(
                $subscription,
                'Wipop recurring subscription stopped after a failed renewal charge.'
            );

            return;
        }

        if ($charge->status === RecurringChargeStatus::INITIALIZED) {
            if ($charge->paymentAttemptId !== null) {
                $this->paymentAttempts->markFailed($charge->paymentAttemptId);
            }
            $this->charges->markFailed(
                $charge->id,
                null,
                'Renewal charge did not reach Wipop submission.'
            );
            $this->stopSubscription(
                $subscription,
                'Wipop recurring subscription stopped after an unfinished renewal charge.'
            );
        }
    }

    private function submissionMayHaveReachedWipop(Throwable $throwable): bool
    {
        if (!$throwable instanceof ApiCallException) {
            return false;
        }

        $previous = $throwable->getPrevious();
        $mayHaveReachedWipop = false;
        while ($previous !== null) {
            if ($previous instanceof RequestException && $previous->hasResponse()) {
                $statusCode = $previous->getResponse()->getStatusCode();
                if ($statusCode >= 400 && $statusCode < 500) {
                    return false;
                }
            }

            if ($previous instanceof HttpTransportException || $previous instanceof WipopApiTimeoutException) {
                $mayHaveReachedWipop = true;
            }

            $previous = $previous->getPrevious();
        }

        return $mayHaveReachedWipop;
    }

    /**
     * @param array<string, int|string> $context
     */
    private function stopSubscription(RecurringSubscription $subscription, string $message, array $context = []): void
    {
        $this->subscriptions->stop($subscription->id);
        $this->logger->warning($message, [
            ...$context,
            'subscription_id' => $subscription->id,
        ]);
    }
}
