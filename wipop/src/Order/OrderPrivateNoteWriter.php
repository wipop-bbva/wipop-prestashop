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

namespace PrestaShop\Module\Wipop\Order;

use Message;
use Order;
use PrestaShop\Module\Wipop\Log\LoggerInterface;
use PrestaShop\Module\Wipop\TranslationDomains;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Validate;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class OrderPrivateNoteWriter
{
    public function __construct(
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
    ) {
    }

    public function addPaymentConfirmed(int $orderId, string $transactionId): void
    {
        $this->addTranslated($orderId, 'Wipop: transaction created. Transaction: %transaction_id%.', [
            '%transaction_id%' => $transactionId,
        ]);
    }

    public function addManualRefundCompleted(
        int $orderId,
        float $amount,
        string $currencyIso,
        string $transactionId,
        int $employeeId,
    ): void {
        $this->addTranslated($orderId, 'Wipop refund completed for %amount%. Transaction: %transaction_id%.', [
            '%amount%' => $this->formatAmount($amount, $currencyIso),
            '%transaction_id%' => $transactionId,
        ], $employeeId);
    }

    public function addWebhookRefundCompleted(
        int $orderId,
        float $amount,
        string $currencyIso,
        string $transactionId,
    ): void {
        $this->addTranslated($orderId, 'Wipop: refund of %amount%. Transaction: %transaction_id%.', [
            '%amount%' => $this->formatAmount($amount, $currencyIso),
            '%transaction_id%' => $transactionId,
        ]);
    }

    public function addPreauthorizationCaptured(
        int $orderId,
        float $amount,
        string $currencyIso,
        string $transactionId,
        int $employeeId,
    ): void {
        $this->addTranslated(
            $orderId,
            'Wipop: preauthorization captured for %amount%. Transaction: %transaction_id%.',
            [
                '%amount%' => $this->formatAmount($amount, $currencyIso),
                '%transaction_id%' => $transactionId,
            ],
            $employeeId
        );
    }

    public function addPreauthorizationVoided(int $orderId, string $transactionId, ?int $employeeId = null): void
    {
        $this->addTranslated($orderId, 'Wipop: preauthorization voided. Transaction: %transaction_id%.', [
            '%transaction_id%' => $transactionId,
        ], $employeeId);
    }

    public function addRecurringCancelledByOrderStatus(int $orderId): void
    {
        $this->addTranslated($orderId, 'Wipop: recurring charges cancelled because the order changed status.');
    }

    public function addRecurringCancelledByFullRefund(int $orderId): void
    {
        $this->addTranslated(
            $orderId,
            'Wipop: recurring charges cancelled because the parent payment was fully refunded.'
        );
    }

    public function addRecurringStoppedManually(int $orderId, int $employeeId): void
    {
        $this->addTranslated($orderId, 'Wipop: recurring charges stopped manually.', [], $employeeId);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function addTranslated(
        int $orderId,
        string $message,
        array $parameters = [],
        ?int $employeeId = null,
    ): void {
        $this->add($orderId, $this->translator->trans($message, $parameters, TranslationDomains::ADMIN), $employeeId);
    }

    private function add(int $orderId, string $note, ?int $employeeId = null): void
    {
        $note = trim(strip_tags($note));
        if ($orderId <= 0 || $note === '') {
            return;
        }

        try {
            $order = new Order($orderId);
            if (!Validate::isLoadedObject($order)) {
                $this->logger->error('Unable to add Wipop order note because the order could not be loaded.', [
                    'order_id' => $orderId,
                ]);

                return;
            }

            $message = new Message();
            $message->message = $note;
            $message->id_order = $orderId;
            $message->id_customer = (int) $order->id_customer;
            $message->private = true;

            if ($employeeId !== null && $employeeId > 0) {
                $message->id_employee = $employeeId;
            }

            if (!$message->add()) {
                $this->logger->error('Unable to add Wipop order note.', [
                    'order_id' => $orderId,
                ]);
            }
        } catch (Throwable $throwable) {
            $this->logger->error('Unable to add Wipop order note.', [
                'order_id' => $orderId,
                'error_class' => $throwable::class,
            ]);
        }
    }

    private function formatAmount(float $amount, string $currencyIso): string
    {
        return sprintf('%s %s', number_format($amount, 2, '.', ''), strtoupper($currencyIso));
    }
}
