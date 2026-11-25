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

namespace PrestaShop\Module\Wipop\Checkout;

use Cart;
use PrestaShop\Module\Wipop\Api\ClientFactory;
use PrestaShop\Module\Wipop\Api\SdkCaller;
use PrestaShop\Module\Wipop\Card\CardCofSelection;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use Throwable;
use Wipop\Domain\Charge;
use Wipop\Domain\PostType;
use Wipop\Domain\PostTypeMode;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ChargeStarter
{
    public function __construct(
        private ClientFactory $clientFactory,
        private SdkCaller $sdkCaller,
        private ChargeRequestFactory $chargeRequestFactory,
        private PaymentAttemptRepository $paymentAttemptRepository,
        private Logger $logger,
    ) {
    }

    /**
     * @throws ApiCallException
     */
    public function start(
        Cart $cart,
        string $method,
        string $returnUrl,
        Settings $settings,
        string $wipopOrderId,
        int $attemptId,
        ?CardCofSelection $cofSelection = null,
        bool $captureImmediately = true,
        bool $useRecurringPostType = false,
    ): string {
        $cofSelection ??= CardCofSelection::none();

        try {
            $client = $this->clientFactory->create($settings);
            $params = $this->chargeRequestFactory->build(
                $cart,
                $method,
                $returnUrl,
                $settings,
                $wipopOrderId,
                $captureImmediately,
                $cofSelection,
            );
            if ($useRecurringPostType) {
                $params->postType(new PostType(PostTypeMode::RECURRENT));
            }

            $charge = $this->sdkCaller->call(
                'checkout creation',
                static fn () => $client->chargeOperation()->create($params, $cofSelection->wipopCustomerId()),
            );
        } catch (Throwable $throwable) {
            $this->markAttemptAsFailed($attemptId);

            if ($throwable instanceof ApiCallException) {
                throw $throwable;
            }

            throw new ApiCallException('Unable to start the Wipop checkout.', 0, $throwable);
        }

        $redirectUrl = $this->extractRedirectUrl($charge);
        $transactionId = $this->extractTransactionId($charge);
        if ($redirectUrl === null && $cofSelection->requiresRedirectUrl()) {
            $this->markAttemptAsFailed($attemptId, $transactionId);

            throw new ApiCallException('Wipop did not return a checkout redirect URL.');
        }

        $this->paymentAttemptRepository->markCheckoutPending($attemptId, $transactionId);
        $this->logger->info('Wipop checkout started.', [
            'attempt_id' => $attemptId,
            'cart_id' => (int) $cart->id,
            'payment_method' => $method,
            'status' => PaymentAttemptStatus::CHECKOUT_PENDING->value,
            'transaction_id' => $transactionId ?? '',
        ]);

        return $redirectUrl ?? $returnUrl;
    }

    private function extractRedirectUrl(Charge $charge): ?string
    {
        $paymentMethod = $charge->paymentMethod;
        $redirectUrl = $paymentMethod === null ? '' : trim((string) $paymentMethod->url);

        return $redirectUrl === '' ? null : $redirectUrl;
    }

    private function extractTransactionId(Charge $charge): ?string
    {
        $transactionId = trim((string) $charge->id);

        return $transactionId === '' ? null : $transactionId;
    }

    private function markAttemptAsFailed(int $attemptId, ?string $transactionId = null): void
    {
        try {
            $this->paymentAttemptRepository->markFailed($attemptId, $transactionId);
        } catch (Throwable $throwable) {
            $this->logger->error('Unable to mark Wipop payment attempt as failed.', [
                'attempt_id' => $attemptId,
            ]);
        }
    }
}
