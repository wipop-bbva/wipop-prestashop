<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Controller\Admin;

use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Form\Type\SettingsType;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WipopConfigurationController extends FrameworkBundleAdminController
{
    public function __construct(private SettingsRepository $settingsRepository)
    {
    }

    public function configurationAction(Request $request): Response
    {
        $form = $this->createForm(SettingsType::class, $this->formData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $this->saveConfiguration($form->getData());

            if ($errors === []) {
                $this->addFlash('success', $this->trans('Successful update', 'Admin.Notifications.Success'));

                return $this->redirectToRoute('wipop_configuration');
            }

            $this->flashErrors($errors);
        }

        return $this->render('@Modules/wipop/views/templates/admin/configuration.html.twig', [
            'configurationForm' => $form->createView(),
        ]);
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
     *
     * @return string[]
     */
    private function saveConfiguration(array $data): array
    {
        $currentSettings = $this->settingsRepository->get();
        $settings = new Settings(
            (bool) ($data['live_mode'] ?? false),
            trim((string) ($data['merchant_id'] ?? '')),
            $this->secretValue($data['secret_key'] ?? null, $currentSettings->secretKey),
            $this->secretValue($data['public_secret_key'] ?? null, $currentSettings->publicSecretKey),
            $this->terminalIdValue($data['terminal_id'] ?? null, $currentSettings->terminalId)
        );

        if ($this->settingsRepository->save($settings)) {
            return [];
        }

        return [
            $this->trans('Unable to save the Wipöp settings.', TranslationDomains::ADMIN),
        ];
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
}
