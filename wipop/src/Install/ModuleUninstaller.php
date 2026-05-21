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
        $dbPrefix = $this->dbPrefix();

        return $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_payment_event`', $dbPrefix))
            && $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_payment_attempt`', $dbPrefix));
    }

    private function dbPrefix(): string
    {
        $dbPrefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';

        return is_string($dbPrefix) ? $dbPrefix : 'ps_';
    }
}
