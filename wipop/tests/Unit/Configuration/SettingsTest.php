<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Configuration;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Configuration\Settings;
use Wipop\Domain\ChargeMethod;

final class SettingsTest extends TestCase
{
    public function testDefaultSettingsEnableSupportedGateways(): void
    {
        $settings = $this->settings();

        self::assertSame(
            [ChargeMethod::CARD, ChargeMethod::BIZUM],
            $settings->filterGateways([ChargeMethod::CARD, ChargeMethod::BIZUM])
        );
    }

    public function testManualSelectionFiltersAvailableGateways(): void
    {
        $settings = $this->settings(enabledGateways: [ChargeMethod::CARD]);

        self::assertSame(
            [ChargeMethod::CARD],
            $settings->filterGateways([ChargeMethod::CARD, ChargeMethod::BIZUM])
        );
    }

    public function testManualSelectionIgnoresUnsupportedGateways(): void
    {
        $settings = $this->settings(
            enabledGateways: [ChargeMethod::CARD, 'UNSUPPORTED']
        );

        self::assertSame(
            [ChargeMethod::CARD],
            $settings->filterGateways([ChargeMethod::CARD, ChargeMethod::BIZUM, 'UNSUPPORTED'])
        );
    }

    public function testEmptySelectionFallsBackToSupportedGateways(): void
    {
        $settings = $this->settings(enabledGateways: []);

        self::assertSame(
            [ChargeMethod::CARD, ChargeMethod::BIZUM],
            $settings->filterGateways([ChargeMethod::CARD, ChargeMethod::BIZUM])
        );
    }

    public function testImmediateModeCapturesCardAndBizumAtCheckout(): void
    {
        $settings = $this->settings(captureMode: CaptureMode::IMMEDIATE);

        self::assertTrue($settings->shouldCaptureImmediately(ChargeMethod::CARD));
        self::assertTrue($settings->shouldCaptureImmediately(ChargeMethod::BIZUM));
    }

    public function testManualModeOnlyPreauthorizesCardPayments(): void
    {
        $settings = $this->settings(captureMode: CaptureMode::MANUAL);

        self::assertFalse($settings->shouldCaptureImmediately(ChargeMethod::CARD));
        self::assertTrue($settings->shouldCaptureImmediately(ChargeMethod::BIZUM));
    }

    public function testPublicSecretKeyDoesNotBlockConfiguration(): void
    {
        $settings = $this->settings(publicSecretKey: '');

        self::assertTrue($settings->isConfigured());
    }

    /**
     * @param string[] $enabledGateways
     */
    private function settings(
        array $enabledGateways = [],
        CaptureMode $captureMode = CaptureMode::IMMEDIATE,
        string $publicSecretKey = 'public',
    ): Settings {
        return new Settings(
            liveMode: false,
            merchantId: 'merchant',
            secretKey: 'secret',
            publicSecretKey: $publicSecretKey,
            terminalId: Settings::DEFAULT_TERMINAL_ID,
            enabledGateways: $enabledGateways,
            captureMode: $captureMode,
        );
    }
}
