<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Install;

use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Webhook\WebhookSettingsRepository;

final class ModuleUninstaller
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private WebhookSettingsRepository $webhookSettingsRepository,
    ) {
    }

    public function run(): bool
    {
        return $this->settingsRepository->deleteAll()
            && $this->webhookSettingsRepository->deleteAll()
            && $this->dropTables();
    }

    private function dropTables(): bool
    {
        $db = \Db::getInstance();

        return $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_payment_event`', _DB_PREFIX_))
            && $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_payment_attempt`', _DB_PREFIX_));
    }
}
