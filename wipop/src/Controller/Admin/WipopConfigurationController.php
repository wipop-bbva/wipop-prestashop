<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Controller\Admin;

use Context;
use PrestaShop\Module\Wipop\Api\MerchantOperationsService;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Exception\ClientConfigurationException;
use PrestaShop\Module\Wipop\Form\Type\SettingsType;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShop\Module\Wipop\Webhook\WebhookAuth;
use PrestaShop\Module\Wipop\Webhook\WebhookSettingsRepository;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WipopConfigurationController extends FrameworkBundleAdminController
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private MerchantOperationsService $merchantOperationsService,
        private WebhookAuth $webhookAuth,
    ) {
    }

    public function configurationAction(Request $request): Response
    {
        $form = $this->createForm(SettingsType::class, $this->formData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings = $this->buildSettings($form->getData());
            $errors = $this->saveConfiguration($settings);

            if ($errors === []) {
                $this->flashSaveResult($settings);

                return $this->redirectToRoute('wipop_configuration');
            }

            $this->flashErrors($errors);
        }

        return $this->render('@Modules/wipop/views/templates/admin/configuration.html.twig', [
            'configurationForm' => $form->createView(),
            'webhook' => $this->webhookData(),
        ]);
    }

    public function regenerateWebhookCredentialsAction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('wipop_webhook_regenerate', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token.', TranslationDomains::ADMIN));

            return $this->redirectToRoute('wipop_configuration');
        }

        $this->webhookAuth->regenerateCredentialsStored();
        $this->addFlash('success', $this->trans('Webhook credentials regenerated.', TranslationDomains::ADMIN));

        return $this->redirectToRoute('wipop_configuration');
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function formData(): array
    {
        $settings = $this->settingsRepository->get();

        return [
            'live_mode' => $settings->liveMode,
            'merchant_id' => $settings->merchantId,
            'secret_key' => '',
            'public_secret_key' => '',
            'terminal_id' => $settings->terminalId,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildSettings(array $data): Settings
    {
        $currentSettings = $this->settingsRepository->get();

        return new Settings(
            (bool) ($data['live_mode'] ?? false),
            trim((string) ($data['merchant_id'] ?? '')),
            $this->secretValue($data['secret_key'] ?? null, $currentSettings->secretKey),
            $this->secretValue($data['public_secret_key'] ?? null, $currentSettings->publicSecretKey),
            $this->terminalIdValue($data['terminal_id'] ?? null, $currentSettings->terminalId)
        );
    }

    /**
     * @return string[]
     */
    private function saveConfiguration(Settings $settings): array
    {
        if ($this->settingsRepository->save($settings)) {
            return [];
        }

        return [
            $this->trans('Unable to save the Wipop settings.', TranslationDomains::ADMIN),
        ];
    }

    private function flashSaveResult(Settings $settings): void
    {
        if (!$settings->isConfigured()) {
            $this->addFlash('success', $this->trans('Settings saved.', TranslationDomains::ADMIN));

            return;
        }

        try {
            $gateways = $this->merchantOperationsService->getAvailableGateways(true);
        } catch (ClientConfigurationException | ApiCallException) {
            $this->addFlash(
                'warning',
                $this->trans(
                    'Settings saved, but the available payment methods could not be verified.',
                    TranslationDomains::ADMIN,
                ),
            );

            return;
        }

        if ($gateways === []) {
            $this->addFlash(
                'warning',
                $this->trans(
                    'Settings saved, but no supported payment methods were detected.',
                    TranslationDomains::ADMIN,
                ),
            );

            return;
        }

        $this->addFlash('success', $this->trans('Settings saved.', TranslationDomains::ADMIN));
    }

    private function secretValue(mixed $submittedValue, string $currentValue): string
    {
        $value = trim((string) $submittedValue);

        return $value !== '' ? $value : $currentValue;
    }

    private function terminalIdValue(mixed $submittedValue, int $currentValue): int
    {
        if ($submittedValue === null || $submittedValue === '') {
            return $currentValue;
        }

        return (int) $submittedValue;
    }

    /**
     * @return array<string, string>
     */
    private function webhookData(): array
    {
        $settings = $this->webhookAuth->ensureCredentialsStored();
        $state = $this->webhookAuth->state($settings);

        return [
            'url' => $this->webhookUrl(),
            'username' => (string) ($settings[WebhookSettingsRepository::KEY_USERNAME] ?? ''),
            'password' => $this->webhookAuth->decryptedPassword($settings),
            'state_label' => $this->webhookStateLabel($state),
            'verification_code' => (string) ($settings[WebhookSettingsRepository::KEY_VERIFICATION_CODE] ?? ''),
            'verification_received_at' => (string) (
                $settings[WebhookSettingsRepository::KEY_VERIFICATION_RECEIVED_AT] ?? ''
            ),
            'connected_at' => (string) ($settings[WebhookSettingsRepository::KEY_CONNECTED_AT] ?? ''),
        ];
    }

    private function webhookUrl(): string
    {
        return Context::getContext()->link->getModuleLink('wipop', 'webhook', [], true);
    }

    private function webhookStateLabel(string $state): string
    {
        return match ($state) {
            WebhookAuth::STATE_CONNECTED => $this->trans('Connected', TranslationDomains::ADMIN),
            WebhookAuth::STATE_PENDING_VERIFICATION => $this->trans('Not verified', TranslationDomains::ADMIN),
            default => $this->trans('Disconnected', TranslationDomains::ADMIN),
        };
    }
}
