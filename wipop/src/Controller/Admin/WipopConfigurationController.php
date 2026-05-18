<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Controller\Admin;

use Context;
use PrestaShop\Module\Wipop\Api\MerchantOperationsService;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Exception\ClientConfigurationException;
use PrestaShop\Module\Wipop\Exception\SettingsReadException;
use PrestaShop\Module\Wipop\Exception\SettingsStorageException;
use PrestaShop\Module\Wipop\Exception\SettingsWriteException;
use PrestaShop\Module\Wipop\Exception\WebhookCredentialsException;
use PrestaShop\Module\Wipop\Form\Type\SettingsType;
use PrestaShop\Module\Wipop\Log\Logger;
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
        private Logger $logger,
    ) {
    }

    public function configurationAction(Request $request): Response
    {
        $currentSettings = null;

        try {
            $currentSettings = $this->settingsRepository->get();
            $formData = $this->formData($currentSettings);
        } catch (SettingsReadException $exception) {
            $this->logger->error('Unable to load Wipop settings data: ' . $exception->getMessage(), [
                'error_class' => $exception::class,
            ]);
            $this->addFlash(
                'error',
                $this->trans('Stored Wipop settings could not be read.', TranslationDomains::ADMIN),
            );
            $formData = $this->emptyFormData();
        }

        $form = $this->createForm(SettingsType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings = null;

            $settings = $this->buildSettings($form->getData(), $currentSettings ?? $this->defaultSettings());
            $errors = $this->saveConfiguration($settings);

            if ($errors === []) {
                $this->flashSaveResult($settings);

                return $this->redirectToRoute('wipop_configuration');
            }

            $this->flashErrors($errors);
        }

        try {
            $webhookData = $this->webhookData();
        } catch (WebhookCredentialsException | SettingsStorageException $exception) {
            $this->logger->error('Unable to load webhook credentials data: ' . $exception->getMessage(), [
                'error_class' => $exception::class,
            ]);
            $this->addFlash(
                'error',
                $this->trans(
                    'Webhook credentials are unavailable. Please regenerate them.',
                    TranslationDomains::ADMIN,
                ),
            );
            $webhookData = $this->emptyWebhookData();
        }

        return $this->render('@Modules/wipop/views/templates/admin/configuration.html.twig', [
            'configurationForm' => $form->createView(),
            'webhook' => $webhookData,
        ]);
    }

    public function regenerateWebhookCredentialsAction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('wipop_webhook_regenerate', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token.', TranslationDomains::ADMIN));

            return $this->redirectToRoute('wipop_configuration');
        }

        try {
            $this->webhookAuth->regenerateCredentialsStored();
        } catch (WebhookCredentialsException | SettingsStorageException $exception) {
            $this->logger->error('Unable to regenerate webhook credentials: ' . $exception->getMessage(), [
                'error_class' => $exception::class,
            ]);
            $this->addFlash(
                'error',
                $this->trans(
                    'Webhook credentials could not be regenerated. Please check module logs.',
                    TranslationDomains::ADMIN,
                ),
            );

            return $this->redirectToRoute('wipop_configuration');
        }

        $this->addFlash('success', $this->trans('Webhook credentials regenerated.', TranslationDomains::ADMIN));

        return $this->redirectToRoute('wipop_configuration');
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function formData(Settings $settings): array
    {
        return [
            'live_mode' => $settings->liveMode,
            'merchant_id' => $settings->merchantId,
            'secret_key' => '',
            'public_secret_key' => '',
            'terminal_id' => $settings->terminalId,
        ];
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function emptyFormData(): array
    {
        $settings = $this->defaultSettings();

        return [
            'live_mode' => $settings->liveMode,
            'merchant_id' => '',
            'secret_key' => '',
            'public_secret_key' => '',
            'terminal_id' => $settings->terminalId,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildSettings(array $data, Settings $currentSettings): Settings
    {
        return new Settings(
            (bool) ($data['live_mode'] ?? false),
            trim((string) ($data['merchant_id'] ?? '')),
            $this->secretValue($data['secret_key'] ?? null, $currentSettings->secretKey),
            $this->secretValue($data['public_secret_key'] ?? null, $currentSettings->publicSecretKey),
            $this->terminalIdValue($data['terminal_id'] ?? null, $currentSettings->terminalId)
        );
    }

    private function defaultSettings(): Settings
    {
        return new Settings(false, '', '', '', Settings::DEFAULT_TERMINAL_ID);
    }

    /**
     * @return string[]
     */
    private function saveConfiguration(Settings $settings): array
    {
        try {
            $this->settingsRepository->save($settings);

            return [];
        } catch (SettingsWriteException $exception) {
            $this->logger->error('Unable to save Wipop settings: ' . $exception->getMessage(), [
                'error_class' => $exception::class,
            ]);
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
        } catch (SettingsReadException | ClientConfigurationException | ApiCallException $exception) {
            $this->logger->warning('Unable to verify Wipop payment methods after saving settings.', [
                'error_class' => $exception::class,
            ]);
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

    /**
     * @return array<string, string>
     */
    private function emptyWebhookData(): array
    {
        return [
            'url' => $this->webhookUrl(),
            'username' => '',
            'password' => '',
            'state_label' => $this->webhookStateLabel(WebhookAuth::STATE_DISCONNECTED),
            'verification_code' => '',
            'verification_received_at' => '',
            'connected_at' => '',
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
