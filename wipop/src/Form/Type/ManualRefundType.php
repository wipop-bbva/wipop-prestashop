<?php

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
