<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Install;

use Db;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;

final class ModuleInstaller
{
    public function __construct(private SettingsRepository $settingsRepository)
    {
    }

    public function run(): bool
    {
        return $this->installSettings()
            && $this->installTables();
    }

    public function installSettings(): bool
    {
        return $this->settingsRepository->installDefaults();
    }

    public function installTables(): bool
    {
        return $this->installPaymentAttemptTable()
            && $this->installPaymentEventTable();
    }

    private function installPaymentAttemptTable(): bool
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%swipop_payment_attempt` (
                `id_wipop_payment_attempt` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_cart` INT UNSIGNED NOT NULL,
                `id_order` INT UNSIGNED DEFAULT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `wipop_order_id` VARCHAR(12) DEFAULT NULL,
                `wipop_transaction_id` VARCHAR(64) DEFAULT NULL,
                `payment_method` VARCHAR(32) DEFAULT NULL,
                `status` VARCHAR(32) NOT NULL,
                `amount` DECIMAL(20, 6) NOT NULL,
                `currency_iso` CHAR(3) NOT NULL,
                `is_live` TINYINT(1) NOT NULL DEFAULT 0,
                `terminal_id` INT UNSIGNED NOT NULL,
                `remote_status` VARCHAR(32) DEFAULT NULL,
                `remote_error_code` VARCHAR(64) DEFAULT NULL,
                `remote_error_message` TEXT DEFAULT NULL,
                `webhook_received_at` DATETIME DEFAULT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_wipop_payment_attempt`),
                KEY `wipop_payment_attempt_cart_idx` (`id_cart`),
                KEY `wipop_payment_attempt_order_idx` (`id_order`),
                UNIQUE KEY `wipop_payment_attempt_order_id_uidx` (`wipop_order_id`),
                UNIQUE KEY `wipop_payment_attempt_transaction_uidx` (`wipop_transaction_id`)
            ) ENGINE=%s DEFAULT CHARSET=utf8;',
            _DB_PREFIX_,
            _MYSQL_ENGINE_
        );

        return Db::getInstance()->execute($sql);
    }

    private function installPaymentEventTable(): bool
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%swipop_payment_event` (
                `id_wipop_payment_event` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_wipop_payment_attempt` INT UNSIGNED NOT NULL,
                `id_order` INT UNSIGNED DEFAULT NULL,
                `id_order_slip` INT UNSIGNED DEFAULT NULL,
                `id_employee` INT UNSIGNED DEFAULT NULL,
                `remote_transaction_id` VARCHAR(64) NOT NULL,
                `parent_transaction_id` VARCHAR(64) DEFAULT NULL,
                `wipop_order_id` VARCHAR(12) DEFAULT NULL,
                `transaction_type` VARCHAR(32) NOT NULL,
                `operation_type` VARCHAR(32) DEFAULT NULL,
                `status` VARCHAR(32) DEFAULT NULL,
                `amount` DECIMAL(20, 6) DEFAULT NULL,
                `currency_iso` CHAR(3) DEFAULT NULL,
                `remote_error_code` VARCHAR(64) DEFAULT NULL,
                `remote_error_message` TEXT DEFAULT NULL,
                `received_at` DATETIME NOT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_wipop_payment_event`),
                KEY `wipop_payment_event_attempt_type_idx` (`id_wipop_payment_attempt`, `transaction_type`),
                KEY `wipop_payment_event_order_idx` (`id_order`),
                KEY `wipop_payment_event_order_slip_idx` (`id_order_slip`),
                UNIQUE KEY `wipop_payment_event_remote_uidx` (`remote_transaction_id`, `transaction_type`)
            ) ENGINE=%s DEFAULT CHARSET=utf8;',
            _DB_PREFIX_,
            _MYSQL_ENGINE_
        );

        return Db::getInstance()->execute($sql);
    }
}
