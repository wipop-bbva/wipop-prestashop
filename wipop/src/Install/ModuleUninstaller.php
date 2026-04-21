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
        $sql = sprintf('DROP TABLE IF EXISTS `%swipop_payment_attempt`', $this->dbPrefix());

        return \Db::getInstance()->execute($sql);
    }

    private function dbPrefix(): string
    {
        $dbPrefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';

        return is_string($dbPrefix) ? $dbPrefix : 'ps_';
    }
}
