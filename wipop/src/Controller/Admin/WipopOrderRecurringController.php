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

namespace PrestaShop\Module\Wipop\Controller\Admin;

use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Order\OrderPrivateNoteWriter;
use PrestaShop\Module\Wipop\Recurring\RecurringChargeRepository;
use PrestaShop\Module\Wipop\Recurring\RecurringSubscription;
use PrestaShop\Module\Wipop\Recurring\RecurringSubscriptionLock;
use PrestaShop\Module\Wipop\Recurring\RecurringSubscriptionRepository;
use PrestaShop\Module\Wipop\Recurring\RecurringSubscriptionStatus;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class WipopOrderRecurringController extends FrameworkBundleAdminController
{
    public function __construct(
        private RecurringSubscriptionRepository $subscriptions,
        private RecurringChargeRepository $charges,
        private RecurringSubscriptionLock $subscriptionLock,
        private OrderPrivateNoteWriter $orderNotes,
        private Logger $logger,
    ) {
    }

    /**
     * @AdminSecurity(
     *     "is_granted('update', 'AdminOrders')",
     *     redirectRoute="admin_orders_view",
     *     redirectQueryParamsToKeep={"orderId"},
     *     message="You do not have permission to stop this Wipop subscription."
     * )
     */
    public function stopAction(int $orderId, int $subscriptionId, Request $request): RedirectResponse
    {
        if (
            !$this->isCsrfTokenValid(
                $this->csrfTokenId($subscriptionId),
                (string) $request->request->get('_csrf_token')
            )
        ) {
            $this->addFlash('error', $this->trans('Invalid security token.', TranslationDomains::ADMIN));

            return $this->redirectToOrder($orderId);
        }

        $subscription = $this->subscriptions->findById($subscriptionId);
        if ($subscription === null || !$this->subscriptionBelongsToOrder($subscription, $orderId)) {
            $this->addFlash(
                'error',
                $this->trans('This order does not have that Wipop subscription.', TranslationDomains::ADMIN)
            );

            return $this->redirectToOrder($orderId);
        }

        if ($subscription->status !== RecurringSubscriptionStatus::ACTIVE) {
            $this->addFlash(
                'warning',
                $this->trans('This Wipop subscription is not active.', TranslationDomains::ADMIN)
            );

            return $this->redirectToOrder($orderId);
        }

        try {
            $stopped = $this->subscriptionLock->run($subscriptionId, function () use ($subscriptionId): bool {
                $subscription = $this->subscriptions->findById($subscriptionId);
                if ($subscription !== null && $subscription->status === RecurringSubscriptionStatus::ACTIVE) {
                    $this->subscriptions->stop($subscriptionId);

                    return true;
                }

                return false;
            });
            if ($stopped) {
                $this->orderNotes->addRecurringStoppedManually($orderId, $this->employeeId());
            }
            $this->addFlash('success', $this->trans('Wipop subscription stopped.', TranslationDomains::ADMIN));
        } catch (Throwable $throwable) {
            $this->logger->error('Unable to stop Wipop recurring subscription.', [
                'order_id' => $orderId,
                'subscription_id' => $subscriptionId,
                'error_class' => $throwable::class,
            ]);
            $this->addFlash(
                'error',
                $this->trans('The Wipop subscription could not be stopped.', TranslationDomains::ADMIN)
            );
        }

        return $this->redirectToOrder($orderId);
    }

    private function subscriptionBelongsToOrder(RecurringSubscription $subscription, int $orderId): bool
    {
        if ($subscription->parentOrderId === $orderId) {
            return true;
        }

        $charge = $this->charges->findByOrderId($orderId);

        return $charge !== null && $charge->subscriptionId === $subscription->id;
    }

    private function csrfTokenId(int $subscriptionId): string
    {
        return sprintf('wipop_order_recurring_%d', $subscriptionId);
    }

    private function redirectToOrder(int $orderId): RedirectResponse
    {
        return $this->redirectToRoute('admin_orders_view', [
            'orderId' => $orderId,
        ]);
    }

    private function employeeId(): int
    {
        return (int) $this->getContext()->employee->id;
    }
}
