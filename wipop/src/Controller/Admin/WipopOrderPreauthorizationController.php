<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Controller\Admin;

use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptLock;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptLockException;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationException;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationOutcome;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationService;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class WipopOrderPreauthorizationController extends FrameworkBundleAdminController
{
    public function __construct(
        private PaymentAttemptRepository $paymentAttemptRepository,
        private PreauthorizationService $preauthorizationService,
        private PaymentAttemptLock $paymentAttemptLock,
        private Logger $logger,
    ) {
    }

    /**
     * @AdminSecurity(
     *     "is_granted('update', 'AdminOrders')",
     *     redirectRoute="admin_orders_view",
     *     redirectQueryParamsToKeep={"orderId"},
     *     message="You do not have permission to capture this Wipop preauthorization."
     * )
     */
    public function captureAction(int $orderId, Request $request): RedirectResponse
    {
        return $this->runAction(
            $orderId,
            $request,
            fn (PaymentAttemptSummary $summary) => $this->preauthorizationService->capture(
                $orderId,
                $summary,
                $this->employeeId()
            ),
            'Wipop: Preauthorization of %amount% captured.',
            'We could not capture the preauthorization in Wipop',
        );
    }

    /**
     * @AdminSecurity(
     *     "is_granted('update', 'AdminOrders')",
     *     redirectRoute="admin_orders_view",
     *     redirectQueryParamsToKeep={"orderId"},
     *     message="You do not have permission to void this Wipop preauthorization."
     * )
     */
    public function reverseAction(int $orderId, Request $request): RedirectResponse
    {
        return $this->runAction(
            $orderId,
            $request,
            fn (PaymentAttemptSummary $summary) => $this->preauthorizationService->reverse(
                $orderId,
                $summary,
                $this->employeeId()
            ),
            'Preauthorization manually voided in Wipop.',
            'We could not void the preauthorization in Wipop.',
        );
    }

    /**
     * @param callable(PaymentAttemptSummary): PreauthorizationOutcome $action
     */
    private function runAction(
        int $orderId,
        Request $request,
        callable $action,
        string $successMessage,
        string $genericErrorMessage,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid($this->csrfTokenId($orderId), (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token.', TranslationDomains::ADMIN));

            return $this->redirectToOrder($orderId);
        }

        $summaryForLock = $this->paymentAttemptRepository->findSummaryByOrderId($orderId);
        if ($summaryForLock === null) {
            $this->addFlash(
                'error',
                $this->trans('This order does not have a Wipop payment.', TranslationDomains::ADMIN)
            );

            return $this->redirectToOrder($orderId);
        }

        try {
            $outcome = $this->paymentAttemptLock->run(
                $summaryForLock->id,
                fn (): PreauthorizationOutcome => $action($this->requireSummary($orderId))
            );
            $this->flashOutcome($outcome, $successMessage);
        } catch (PaymentAttemptLockException) {
            $this->addFlash('error', $this->trans($genericErrorMessage, TranslationDomains::ADMIN));
        } catch (PreauthorizationException $exception) {
            $this->addFlash('error', $this->trans($exception->getMessage(), TranslationDomains::ADMIN));
        } catch (Throwable $throwable) {
            $this->logger->error('Unexpected Wipop preauthorization error.', [
                'order_id' => $orderId,
                'error_class' => $throwable::class,
            ]);
            $this->addFlash('error', $this->trans($genericErrorMessage, TranslationDomains::ADMIN));
        }

        return $this->redirectToOrder($orderId);
    }

    /**
     * @throws PreauthorizationException
     */
    private function requireSummary(int $orderId): PaymentAttemptSummary
    {
        $summary = $this->paymentAttemptRepository->findSummaryByOrderId($orderId);
        if ($summary === null) {
            throw new PreauthorizationException('This order does not have a Wipop payment.');
        }

        return $summary;
    }

    private function flashOutcome(PreauthorizationOutcome $outcome, string $message): void
    {
        $this->addFlash('success', $this->trans($message, TranslationDomains::ADMIN, [
            '%amount%' => $this->formatAmount($outcome->amount, $outcome->currencyIso),
        ]));

        if ($outcome->localWarning !== null) {
            $this->addFlash('warning', $this->trans($outcome->localWarning, TranslationDomains::ADMIN));
        }
    }

    private function csrfTokenId(int $orderId): string
    {
        return sprintf('wipop_order_preauthorization_%d', $orderId);
    }

    private function formatAmount(float $amount, string $currencyIso): string
    {
        return sprintf('%s %s', number_format($amount, 2, '.', ''), strtoupper($currencyIso));
    }

    private function employeeId(): int
    {
        return (int) $this->getContext()->employee->id;
    }

    private function redirectToOrder(int $orderId): RedirectResponse
    {
        return $this->redirectToRoute('admin_orders_view', [
            'orderId' => $orderId,
        ]);
    }
}
