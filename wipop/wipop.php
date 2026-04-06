<?php

/**
 * 2007-2025 PrestaShop
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
 *  @copyright 2007-2025 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/autoload.php';

use PrestaShop\Module\Wipop\Checkout\PaymentOptionFactory;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Install\ModuleInstaller;
use PrestaShop\Module\Wipop\Install\ModuleUninstaller;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class Wipop extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'wipop';
        $this->tab = 'payments_gateways';
        $this->version = '0.2.0';
        $this->author = 'BBVA';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Wipop', [], TranslationDomains::MAIN);
        $this->description = $this->trans(
            'PrestaShop module that integrates the Wipop payment gateway',
            [],
            TranslationDomains::MAIN
        );

        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall Wipop?',
            [],
            TranslationDomains::MAIN
        );

        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => '9.0.0'];
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->trans(
                'You have to enable the cURL extension on your server to install this module',
                [],
                TranslationDomains::MAIN
            );

            return false;
        }

        return parent::install()
            && (new ModuleInstaller($this->settingsRepository()))->run()
            && $this->registerHook('paymentOptions');
    }

    public function uninstall()
    {
        return (new ModuleUninstaller($this->settingsRepository()))->run()
            && parent::uninstall();
    }

    public function getContent()
    {
        Tools::redirectAdmin(
            SymfonyContainer::getInstance()->get('router')->generate('wipop_configuration')
        );

        return '';
    }

    public function hookPaymentOptions($params)
    {
        $cart = is_array($params) ? ($params['cart'] ?? null) : null;

        if (!$cart instanceof Cart) {
            return [];
        }

        return $this->paymentOptionFactory()->buildOptions($this, $cart);
    }

    private function paymentOptionFactory(): PaymentOptionFactory
    {
        return new PaymentOptionFactory($this->settingsRepository());
    }

    private function settingsRepository(): SettingsRepository
    {
        return new SettingsRepository();
    }
}
