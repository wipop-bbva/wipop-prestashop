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

namespace PrestaShop\Module\Wipop\Form\Type;

use PrestaShop\Module\Wipop\Refund\ManualRefundFormLine;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ManualRefundType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ManualRefundFormLine[] $refundLines */
        $refundLines = $options['refund_lines'];
        foreach ($refundLines as $line) {
            $builder
                ->add('quantity_' . $line->orderDetailId, IntegerType::class, [
                    'label' => $this->trans('Quantity', 'Admin.Global'),
                    'required' => false,
                    'empty_data' => '0',
                    'attr' => [
                        'min' => 0,
                        'max' => $line->quantityRefundable,
                    ],
                ])
                ->add('amount_' . $line->orderDetailId, NumberType::class, [
                    'label' => $this->trans('Amount', 'Admin.Global'),
                    'required' => false,
                    'empty_data' => '0',
                    'scale' => 2,
                    'html5' => true,
                    'attr' => [
                        'min' => 0,
                        'max' => $line->amountRefundable,
                        'step' => '0.01',
                    ],
                ]);
        }

        $builder
            ->add('shipping_amount', NumberType::class, [
                'label' => $this->trans('Shipping', 'Admin.Catalog.Feature'),
                'required' => false,
                'empty_data' => '0',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'max' => $options['shipping_refundable_amount'],
                    'step' => '0.01',
                ],
            ])
            ->add('restock', SwitchType::class, [
                'label' => $this->trans('Re-stock products', 'Admin.Orderscustomers.Feature'),
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'refund_lines' => [],
            'shipping_refundable_amount' => 0.0,
            'csrf_token_id' => 'wipop_manual_refund',
            'translation_domain' => TranslationDomains::ADMIN,
        ]);

        $resolver->setAllowedTypes('refund_lines', 'array');
        $resolver->setAllowedTypes('shipping_refundable_amount', ['int', 'float']);
        $resolver->setAllowedValues('refund_lines', static function (array $lines): bool {
            foreach ($lines as $line) {
                if (!$line instanceof ManualRefundFormLine) {
                    return false;
                }
            }

            return true;
        });
    }
}
