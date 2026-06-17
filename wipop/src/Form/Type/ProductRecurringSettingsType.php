<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Form\Type;

use PrestaShop\Module\Wipop\Recurring\RecurringPeriod;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductRecurringSettingsType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', SwitchType::class, [
                'label' => $this->trans('Enable recurring payment', TranslationDomains::ADMIN),
                'required' => false,
            ])
            ->add('period', ChoiceType::class, [
                'label' => $this->trans('Periodicity', TranslationDomains::ADMIN),
                'required' => false,
                'placeholder' => $this->trans('Choose a period', TranslationDomains::ADMIN),
                'choice_translation_domain' => TranslationDomains::ADMIN,
                'choices' => [
                    'Monthly' => RecurringPeriod::MONTHLY->value,
                    'Yearly' => RecurringPeriod::YEARLY->value,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => TranslationDomains::ADMIN,
        ]);
    }
}
