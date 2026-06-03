<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Install;

use Configuration;
use Context;
use Db;
use Language;
use OrderState;
use PrestaShop\Module\Wipop\Configuration\ConfigKeys;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\TranslationDomains;
use Validate;

final class ModuleInstaller
{
    private const MODULE_NAME = 'wipop';
    private const AUTHORIZED_ORDER_STATE_LABEL = 'Wipop authorized';

    public function __construct(private SettingsRepository $settingsRepository)
    {
    }

    public function run(): bool
    {
        return $this->installSettings()
            && $this->installTables()
            && $this->installAuthorizedOrderState();
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
                `capture_mode` VARCHAR(16) NOT NULL DEFAULT \'immediate\',
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

    private function installAuthorizedOrderState(): bool
    {
        $configuredStateId = (int) Configuration::get(ConfigKeys::AUTHORIZED_ORDER_STATE_ID);
        if ($configuredStateId > 0) {
            $configuredState = new OrderState($configuredStateId);
            if (Validate::isLoadedObject($configuredState) && $configuredState->module_name === self::MODULE_NAME) {
                return true;
            }
        }

        $orderState = new OrderState();
        $orderState->module_name = self::MODULE_NAME;
        $orderState->name = $this->authorizedOrderStateNames();
        $orderState->color = '#ffc107';
        $orderState->logable = false;
        /* Authorization is not payment; the order becomes paid only after capture. */
        $orderState->paid = false;
        $orderState->invoice = false;
        $orderState->send_email = false;
        $orderState->hidden = false;
        $orderState->unremovable = true;
        $orderState->delivery = false;
        $orderState->shipped = false;
        $orderState->pdf_delivery = false;
        $orderState->pdf_invoice = false;
        $orderState->deleted = false;

        return (bool) $orderState->add()
            && Configuration::updateValue(ConfigKeys::AUTHORIZED_ORDER_STATE_ID, (int) $orderState->id);
    }

    /**
     * @return array<int, string>
     */
    private function authorizedOrderStateNames(): array
    {
        $translator = Context::getContext()->getTranslator();
        $names = [];
        /** @var array<int, array{id_lang: int|string, locale: string}> $languages */
        $languages = Language::getLanguages(false);

        foreach ($languages as $language) {
            $names[(int) $language['id_lang']] = $translator->trans(
                self::AUTHORIZED_ORDER_STATE_LABEL,
                [],
                TranslationDomains::ADMIN,
                $language['locale']
            );
        }

        return $names;
    }
}
