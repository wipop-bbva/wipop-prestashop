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

class Wipop extends PaymentModule
{
    protected $config_form = false;

    private const CONFIG_KEYS = [
        'WIPOP_LIVE_MODE',
        'WIPOP_MERCHANT_ID',
        'WIPOP_SECRET_KEY',
        'WIPOP_PUBLIC_SECRET_KEY',
        'WIPOP_TERMINAL_ID',
    ];

    public function __construct()
    {
        $this->name = 'wipop';
        $this->tab = 'payments_gateways';
        $this->version = '0.1.0';
        $this->author = 'BBVA';
        $this->need_instance = 1;

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Wipöp');
        $this->description = $this->l('PrestaShop module that integrates the Wipöp payment gateway, enabling your store to accept payments with card and Bizum.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Wipöp?');

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => '9.0'];
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');

            return false;
        }

        Configuration::updateValue('WIPOP_LIVE_MODE', false);
        Configuration::updateValue('WIPOP_MERCHANT_ID', '');
        Configuration::updateValue('WIPOP_SECRET_KEY', '');
        Configuration::updateValue('WIPOP_PUBLIC_SECRET_KEY', '');
        Configuration::updateValue('WIPOP_TERMINAL_ID', 1);

        include dirname(__FILE__) . '/sql/install.php';

        return parent::install()
            && $this->registerHook('header')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayAdminOrder')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('displayPayment')
            && $this->registerHook('displayPaymentReturn')
            && $this->registerHook('displayPaymentTop');
    }

    public function uninstall()
    {
        foreach (self::CONFIG_KEYS as $key) {
            Configuration::deleteByName($key);
        }

        include dirname(__FILE__) . '/sql/uninstall.php';

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /*
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submitWipopModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitWipopModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'WIPOP_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in production (live) mode'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'name' => 'WIPOP_MERCHANT_ID',
                        'label' => $this->l('Merchant ID'),
                        'desc' => $this->l('Wipöp merchant identifier (m...).'),
                    ],
                    [
                        'type' => 'password',
                        'name' => 'WIPOP_SECRET_KEY',
                        'label' => $this->l('Secret key'),
                        'desc' => $this->l('Server secret key (sk_...).'),
                    ],
                    [
                        'type' => 'password',
                        'name' => 'WIPOP_PUBLIC_SECRET_KEY',
                        'label' => $this->l('Public secret key'),
                        'desc' => $this->l('Public key (pk_...).'),
                    ],
                    [
                        'type' => 'text',
                        'col' => 2,
                        'name' => 'WIPOP_TERMINAL_ID',
                        'label' => $this->l('Terminal ID'),
                        'desc' => $this->l('Terminal identifier.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return [
            'WIPOP_LIVE_MODE' => (bool) Configuration::get('WIPOP_LIVE_MODE', false),
            'WIPOP_MERCHANT_ID' => (string) Configuration::get('WIPOP_MERCHANT_ID', ''),
            'WIPOP_SECRET_KEY' => (string) Configuration::get('WIPOP_SECRET_KEY', ''),
            'WIPOP_PUBLIC_SECRET_KEY' => (string) Configuration::get('WIPOP_PUBLIC_SECRET_KEY', ''),
            'WIPOP_TERMINAL_ID' => (string) Configuration::get('WIPOP_TERMINAL_ID', '1'),
        ];
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();
        $secretKeys = ['WIPOP_SECRET_KEY', 'WIPOP_PUBLIC_SECRET_KEY'];

        foreach (array_keys($form_values) as $key) {
            $value = Tools::getValue($key);
            if (in_array($key, $secretKeys, true) && $value === '') {
                continue;
            }

            Configuration::updateValue($key, $value);
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l('Pay with Wipöp'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true));

        return [
            $option,
        ];
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hookDisplayAdminOrder()
    {
        /* Place your code here. */
    }

    public function hookDisplayOrderConfirmation()
    {
        /* Place your code here. */
    }

    public function hookDisplayPayment()
    {
        /* Place your code here. */
    }

    public function hookDisplayPaymentReturn()
    {
        /* Place your code here. */
    }

    public function hookDisplayPaymentTop()
    {
        /* Place your code here. */
    }
}
