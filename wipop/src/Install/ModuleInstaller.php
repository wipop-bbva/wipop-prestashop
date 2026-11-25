<?php
/**
 * 2007-2026 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2026 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

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

if (!defined('_PS_VERSION_')) {
    exit;
}

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
            && $this->installPaymentEventTable()
            && $this->installStoredCardTable()
            && $this->installRecurringProductTable()
            && $this->installRecurringSubscriptionTable()
            && $this->installRecurringSubscriptionItemTable()
            && $this->installRecurringChargeTable();
    }

    private function installPaymentAttemptTable(): bool
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%swipop_payment_attempt` (
                `id_wipop_payment_attempt` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_cart` INT UNSIGNED NOT NULL,
                `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_order` INT UNSIGNED DEFAULT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `merchant_id` VARCHAR(64) NOT NULL,
                `wipop_order_id` VARCHAR(12) DEFAULT NULL,
                `wipop_transaction_id` VARCHAR(64) DEFAULT NULL,
                `payment_method` VARCHAR(32) DEFAULT NULL,
                `capture_mode` VARCHAR(16) NOT NULL DEFAULT \'immediate\',
                `cof_intent` VARCHAR(16) NOT NULL DEFAULT \'none\',
                `id_wipop_stored_card` INT UNSIGNED DEFAULT NULL,
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
                KEY `wipop_payment_attempt_customer_idx` (`id_customer`),
                KEY `wipop_payment_attempt_order_idx` (`id_order`),
                KEY `wipop_payment_attempt_stored_card_idx` (`id_wipop_stored_card`),
                UNIQUE KEY `wipop_payment_attempt_order_id_uidx` (`wipop_order_id`),
                UNIQUE KEY `wipop_payment_attempt_transaction_uidx` (`wipop_transaction_id`)
            ) ENGINE=%s DEFAULT CHARSET=utf8;',
            _DB_PREFIX_,
            _MYSQL_ENGINE_
        );

        return Db::getInstance()->execute($sql);
    }

    private function installStoredCardTable(): bool
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%swipop_stored_card` (
                `id_wipop_stored_card` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_customer` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `merchant_id` VARCHAR(64) NOT NULL,
                `is_live` TINYINT(1) NOT NULL DEFAULT 0,
                `terminal_id` INT UNSIGNED NOT NULL,
                `source_id_encrypted` TEXT NOT NULL,
                `source_id_hash` CHAR(64) NOT NULL,
                `wipop_customer_id` VARCHAR(64) DEFAULT NULL,
                `brand` VARCHAR(32) DEFAULT NULL,
                `last4` CHAR(4) DEFAULT NULL,
                `expiration_month` CHAR(2) DEFAULT NULL,
                `expiration_year` CHAR(4) DEFAULT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_save_attempt_id` INT UNSIGNED NOT NULL,
                `forgotten_after_attempt_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_used_at` DATETIME DEFAULT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_wipop_stored_card`),
                KEY `wipop_stored_card_customer_idx` (
                    `id_customer`,
                    `id_shop`,
                    `merchant_id`,
                    `is_live`,
                    `terminal_id`,
                    `active`
                ),
                UNIQUE KEY `wipop_stored_card_source_uidx` (
                    `id_customer`,
                    `id_shop`,
                    `merchant_id`,
                    `is_live`,
                    `terminal_id`,
                    `source_id_hash`
                )
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

    private function installRecurringProductTable(): bool
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%swipop_recurring_product` (
                `id_wipop_recurring_product` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `period` VARCHAR(16) NOT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_wipop_recurring_product`),
                UNIQUE KEY `wipop_recurring_product_uidx` (`id_product`, `id_shop`)
            ) ENGINE=%s DEFAULT CHARSET=utf8;',
            _DB_PREFIX_,
            _MYSQL_ENGINE_
        );

        return Db::getInstance()->execute($sql);
    }

    private function installRecurringSubscriptionTable(): bool
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%swipop_recurring_subscription` (
                `id_wipop_recurring_subscription` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_initial_payment_attempt` INT UNSIGNED NOT NULL,
                `id_parent_order` INT UNSIGNED DEFAULT NULL,
                `id_customer` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `merchant_id` VARCHAR(64) NOT NULL,
                `is_live` TINYINT(1) NOT NULL DEFAULT 0,
                `terminal_id` INT UNSIGNED NOT NULL,
                `id_currency` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `id_address_invoice` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_address_delivery` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_wipop_stored_card` INT UNSIGNED DEFAULT NULL,
                `period` VARCHAR(16) NOT NULL,
                `status` VARCHAR(24) NOT NULL,
                `amount` DECIMAL(20, 6) NOT NULL,
                `currency_iso` CHAR(3) NOT NULL,
                `next_sequence` INT UNSIGNED NOT NULL DEFAULT 2,
                `next_attempt_at` DATETIME DEFAULT NULL,
                `last_transaction_id` VARCHAR(64) DEFAULT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_wipop_recurring_subscription`),
                KEY `wipop_recurring_subscription_due_idx` (`status`, `next_attempt_at`),
                KEY `wipop_recurring_subscription_attempt_idx` (`id_initial_payment_attempt`),
                KEY `wipop_recurring_subscription_parent_order_idx` (`id_parent_order`)
            ) ENGINE=%s DEFAULT CHARSET=utf8;',
            _DB_PREFIX_,
            _MYSQL_ENGINE_
        );

        return Db::getInstance()->execute($sql);
    }

    private function installRecurringSubscriptionItemTable(): bool
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%swipop_recurring_subscription_item` (
                `id_wipop_recurring_subscription_item` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_wipop_recurring_subscription` INT UNSIGNED NOT NULL,
                `id_product` INT UNSIGNED NOT NULL,
                `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
                `quantity` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_wipop_recurring_subscription_item`),
                KEY `wipop_recurring_subscription_item_subscription_idx` (`id_wipop_recurring_subscription`)
            ) ENGINE=%s DEFAULT CHARSET=utf8;',
            _DB_PREFIX_,
            _MYSQL_ENGINE_
        );

        return Db::getInstance()->execute($sql);
    }

    private function installRecurringChargeTable(): bool
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%swipop_recurring_charge` (
                `id_wipop_recurring_charge` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_wipop_recurring_subscription` INT UNSIGNED NOT NULL,
                `sequence` INT UNSIGNED NOT NULL,
                `id_wipop_payment_attempt` INT UNSIGNED DEFAULT NULL,
                `id_cart` INT UNSIGNED DEFAULT NULL,
                `id_order` INT UNSIGNED DEFAULT NULL,
                `wipop_order_id` VARCHAR(12) NOT NULL,
                `wipop_transaction_id` VARCHAR(64) DEFAULT NULL,
                `status` VARCHAR(24) NOT NULL,
                `remote_error_code` VARCHAR(64) DEFAULT NULL,
                `remote_error_message` TEXT DEFAULT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_wipop_recurring_charge`),
                UNIQUE KEY `wipop_recurring_charge_sequence_uidx` (`id_wipop_recurring_subscription`, `sequence`),
                UNIQUE KEY `wipop_recurring_charge_order_uidx` (`wipop_order_id`),
                KEY `wipop_recurring_charge_attempt_idx` (`id_wipop_payment_attempt`),
                KEY `wipop_recurring_charge_order_idx` (`id_order`)
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
                $this->configureAuthorizedOrderState($configuredState);

                return (bool) $configuredState->save();
            }
        }

        $orderState = $this->existingAuthorizedOrderState() ?? new OrderState();
        $this->configureAuthorizedOrderState($orderState);

        return (bool) (Validate::isLoadedObject($orderState) ? $orderState->save() : $orderState->add())
            && Configuration::updateValue(ConfigKeys::AUTHORIZED_ORDER_STATE_ID, (int) $orderState->id);
    }

    private function configureAuthorizedOrderState(OrderState $orderState): void
    {
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
    }

    private function existingAuthorizedOrderState(): ?OrderState
    {
        $stateId = (int) Db::getInstance()->getValue(sprintf(
            "SELECT `id_order_state` FROM `%sorder_state` WHERE `module_name` = '%s' "
            . 'AND `deleted` = 0 ORDER BY `id_order_state` ASC',
            _DB_PREFIX_,
            pSQL(self::MODULE_NAME)
        ));
        if ($stateId <= 0) {
            return null;
        }

        $orderState = new OrderState($stateId);

        return Validate::isLoadedObject($orderState) ? $orderState : null;
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
