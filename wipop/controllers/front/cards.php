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

require_once dirname(__DIR__, 2) . '/autoload.php';

use PrestaShop\Module\Wipop\Card\StoredCardPresenter;
use PrestaShop\Module\Wipop\Card\StoredCardRepository;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Exception\SettingsReadException;
use PrestaShop\Module\Wipop\TranslationDomains;

class WipopCardsModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $guestAllowed = false;
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        if (Tools::isSubmit('forget_wipop_card')) {
            $this->forgetCard();
        }

        $this->context->smarty->assign([
            'wipopSavedCards' => [
                'action' => $this->cardsUrl(),
                'token' => Tools::getToken(false),
                'cards' => $this->cardsForTemplate(),
            ],
        ]);

        $this->setTemplate('module:wipop/views/templates/front/cards.tpl');
    }

    public function getBreadcrumbLinks()
    {
        /** @var array{links: array<int, array<string, string>>} $breadcrumb */
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = $this->addMyAccountToBreadcrumb();
        $breadcrumb['links'][] = [
            'title' => $this->translateMessage('Saved cards'),
            'url' => $this->cardsUrl(),
        ];

        return $breadcrumb;
    }

    private function forgetCard(): void
    {
        if (!$this->isTokenValid()) {
            return;
        }

        $cardId = Tools::getValue('id_wipop_stored_card');
        if (!is_numeric($cardId)) {
            return;
        }

        $cardId = (int) $cardId;
        if ($cardId <= 0) {
            return;
        }

        StoredCardRepository::createDefault()->deactivate(
            $cardId,
            (int) $this->context->customer->id,
            (int) $this->context->shop->id
        );
    }

    /**
     * @return array<int, array{id: int, brand: string, last4: string, expiry: ?string, label: string}>
     */
    private function cardsForTemplate(): array
    {
        try {
            $settings = (new SettingsRepository())->get();
        } catch (SettingsReadException) {
            return [];
        }

        $presenter = new StoredCardPresenter();
        $cards = [];
        $storedCards = StoredCardRepository::createDefault()->findActiveByCustomer(
            (int) $this->context->customer->id,
            (int) $this->context->shop->id,
            $settings->merchantId,
            $settings->liveMode,
            $settings->terminalId
        );

        foreach ($storedCards as $card) {
            $cards[] = $presenter->present($card);
        }

        return $cards;
    }

    private function cardsUrl(): string
    {
        return $this->context->link->getModuleLink('wipop', 'cards', [], true);
    }

    private function translateMessage(string $message, array $parameters = []): string
    {
        return $this->context->getTranslator()->trans($message, $parameters, TranslationDomains::SHOP);
    }
}
