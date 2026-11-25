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

namespace PrestaShop\Module\Wipop\Form\Modifier;

use PrestaShop\Module\Wipop\Form\Type\ProductRecurringSettingsType;
use PrestaShop\Module\Wipop\Recurring\ProductRecurringSettingsRepository;
use PrestaShopBundle\Form\FormBuilderModifier;
use Symfony\Component\Form\FormBuilderInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ProductRecurringFormModifier
{
    public const FIELD_NAME = 'wipop_recurring';

    public function __construct(
        private ProductRecurringSettingsRepository $settingsRepository,
        private FormBuilderModifier $formBuilderModifier,
    ) {
    }

    public function modify(?int $productId, int $shopId, FormBuilderInterface $productFormBuilder): void
    {
        if ($productId === null || !$this->isVirtualProduct($productId)) {
            return;
        }

        $period = $this->settingsRepository->findPeriod($productId, $shopId);

        $this->formBuilderModifier->addAfter(
            $productFormBuilder,
            'pricing',
            self::FIELD_NAME,
            ProductRecurringSettingsType::class,
            [
                'data' => [
                    'enabled' => $period !== null,
                    'period' => $period?->value,
                ],
            ]
        );
    }

    private function isVirtualProduct(int $productId): bool
    {
        $product = new \Product($productId);

        return \Validate::isLoadedObject($product) && (bool) $product->is_virtual;
    }
}
