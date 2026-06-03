<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Controller\Admin;

use Exception;
use PrestaShop\Module\Wipop\Form\Type\ManualRefundType;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAmount;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptLock;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptLockException;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;
use PrestaShop\Module\Wipop\Refund\ManualRefundException;
use PrestaShop\Module\Wipop\Refund\ManualRefundFormDataProvider;
use PrestaShop\Module\Wipop\Refund\ManualRefundOutcome;
use PrestaShop\Module\Wipop\Refund\ManualRefundRequestFactory;
use PrestaShop\Module\Wipop\Refund\ManualRefundService;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WipopOrderRefundController extends FrameworkBundleAdminController
{
    public function __construct(
        private PaymentAttemptRepository $paymentAttemptRepository,
        private ManualRefundFormDataProvider $formDataProvider,
        private ManualRefundRequestFactory $requestFactory,
        private ManualRefundService $manualRefundService,
        private PaymentAttemptLock $paymentAttemptLock,
        private Logger $logger,
    ) {
    }

    /**
     * @AdminSecurity(
     *     "is_granted('update', 'AdminOrders') && is_granted('delete', 'AdminOrders')",
     *     redirectRoute="admin_orders_view",
     *     redirectQueryParamsToKeep={"orderId"},
     *     message="You do not have permission to refund this order."
     * )
     */
    public function refundAction(int $orderId, Request $request): Response
    {
        $summary = $this->paymentAttemptRepository->findSummaryByOrderId($orderId);
        if ($summary === null) {
            $this->addFlash(
                'error',
                $this->trans('This order does not have a Wipop payment.', TranslationDomains::ADMIN)
            );

            return $this->redirectToOrder($orderId);
        }

        try {
            $formData = $this->formDataProvider->getForOrder($orderId);
        } catch (Exception $exception) {
            $this->logger->error('Unable to load Wipop refund form data.', [
                'order_id' => $orderId,
                'error_class' => $exception::class,
            ]);
            $this->addFlash('error', $this->trans('Refund data could not be loaded.', TranslationDomains::ADMIN));

            return $this->redirectToOrder($orderId);
        }

        if ($formData->lines === [] && PaymentAmount::toCents($formData->shippingRefundableAmount) <= 0) {
            $this->addFlash(
                'warning',
                $this->trans('This order has no refundable amount.', TranslationDomains::ADMIN)
            );

            return $this->redirectToOrder($orderId);
        }

        $form = $this->createForm(ManualRefundType::class, [], [
            'refund_lines' => $formData->lines,
            'shipping_refundable_amount' => $formData->shippingRefundableAmount,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var array<string, mixed> $submittedData */
                $submittedData = $form->getData();
                $outcome = $this->processSubmittedRefund($orderId, $summary->id, $submittedData);
                $this->flashOutcome($outcome);

                return $this->redirectToOrder($orderId);
            } catch (PaymentAttemptLockException) {
                $this->addFlash(
                    'error',
                    $this->trans('A Wipop refund is already being processed for this order.', TranslationDomains::ADMIN)
                );
            } catch (ManualRefundException $exception) {
                $this->addFlash('error', $this->trans($exception->getMessage(), TranslationDomains::ADMIN));
            } catch (Exception $exception) {
                $this->logger->error('Unexpected Wipop manual refund error.', [
                    'order_id' => $orderId,
                    'error_class' => $exception::class,
                ]);
                $this->addFlash(
                    'error',
                    $this->trans('The Wipop refund could not be completed.', TranslationDomains::ADMIN)
                );
            }
        }

        return $this->render('@Modules/wipop/views/templates/admin/refund/manual_refund.html.twig', [
            'refundForm' => $form->createView(),
            'refundData' => $formData,
            'orderId' => $orderId,
            'wipopPayment' => $summary,
            'remainingAmount' => $this->remainingAmount($summary),
        ]);
    }

    /**
     * @param array<string, mixed> $submittedData
     */
    private function processSubmittedRefund(int $orderId, int $attemptId, array $submittedData): ManualRefundOutcome
    {
        return $this->paymentAttemptLock->run($attemptId, function () use ($orderId, $submittedData) {
            $summary = $this->paymentAttemptRepository->findSummaryByOrderId($orderId);
            if ($summary === null) {
                throw new ManualRefundException('This order does not have a Wipop payment.');
            }

            $formData = $this->formDataProvider->getForOrder($orderId);

            return $this->manualRefundService->refund(
                $orderId,
                $summary,
                $this->requestFactory->fromSubmittedData($submittedData, $formData),
                $formData,
                $this->employeeId(),
            );
        });
    }

    private function flashOutcome(ManualRefundOutcome $outcome): void
    {
        $message = sprintf(
            'Wipop refund completed for %s.',
            $this->formatAmount($outcome->amount, $outcome->currencyIso),
        );

        if ($outcome->orderSlipId !== null) {
            $message .= sprintf(' Credit slip: #%d.', $outcome->orderSlipId);
        }

        $this->addFlash('success', $this->trans($message, TranslationDomains::ADMIN));

        if ($outcome->localWarning !== null) {
            $this->addFlash('warning', $this->trans($outcome->localWarning, TranslationDomains::ADMIN));
        }
    }

    private function remainingAmount(PaymentAttemptSummary $summary): string
    {
        $refunded = $summary->refundSummary->refundedAmount ?? 0.0;
        $remainingCents = max(0, PaymentAmount::toCents($summary->amount) - PaymentAmount::toCents($refunded));

        return $this->formatAmount(PaymentAmount::fromCents($remainingCents), $summary->currencyIso);
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
