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

namespace PrestaShop\Module\Wipop\Card;

use PrestaShop\Module\Wipop\Configuration\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class CardCofSelectionResolver
{
    public function __construct(private StoredCardProviderInterface $storedCards)
    {
    }

    /**
     * @throws CardCofException
     */
    public function resolve(
        string $paymentMethod,
        ?int $customerId,
        int $shopId,
        string $merchantId,
        bool $isLive,
        int $terminalId,
        ?int $selectedCardId,
        bool $saveCard,
        bool $requiresReusableCard = false,
    ): CardCofSelection {
        if (!Settings::isCardMethod($paymentMethod)) {
            if ($requiresReusableCard) {
                throw new CardCofException('Recurring payments require card payment.');
            }

            return CardCofSelection::none();
        }

        if ($customerId === null || $customerId <= 0) {
            if ($requiresReusableCard) {
                throw new CardCofException('Log in to pay for recurring products with Wipop.');
            }

            return CardCofSelection::none();
        }

        if ($selectedCardId !== null) {
            $storedCard = $this->storedCards->findActiveByIdForCustomer(
                $selectedCardId,
                $customerId,
                $shopId,
                $merchantId,
                $isLive,
                $terminalId
            );

            if ($storedCard === null) {
                throw new CardCofException('The selected saved card is no longer available.');
            }

            return CardCofSelection::useExisting($storedCard);
        }

        return ($saveCard || $requiresReusableCard) ? CardCofSelection::save() : CardCofSelection::none();
    }
}
