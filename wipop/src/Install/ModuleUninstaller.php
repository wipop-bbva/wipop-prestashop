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

use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Webhook\WebhookSettingsRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

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

        return $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_recurring_charge`', _DB_PREFIX_))
            && $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_recurring_subscription_item`', _DB_PREFIX_))
            && $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_recurring_subscription`', _DB_PREFIX_))
            && $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_recurring_product`', _DB_PREFIX_))
            && $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_payment_event`', _DB_PREFIX_))
            && $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_payment_attempt`', _DB_PREFIX_))
            && $db->execute(sprintf('DROP TABLE IF EXISTS `%swipop_stored_card`', _DB_PREFIX_));
    }
}
