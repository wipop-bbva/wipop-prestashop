<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Form\Type;

use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Range;

final class SettingsType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('live_mode', SwitchType::class, [
                'label' => $this->trans('Live mode', \Wipop::TRANSLATION_DOMAIN_ADMIN),
                'required' => false,
            ])
            ->add('merchant_id', TextType::class, [
                'label' => $this->trans('Merchant ID', \Wipop::TRANSLATION_DOMAIN_ADMIN),
                'required' => false,
            ])
            ->add('secret_key', PasswordType::class, [
                'label' => $this->trans('Secret key', \Wipop::TRANSLATION_DOMAIN_ADMIN),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('public_secret_key', PasswordType::class, [
                'label' => $this->trans('Public secret key', \Wipop::TRANSLATION_DOMAIN_ADMIN),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('terminal_id', IntegerType::class, [
                'label' => $this->trans('Terminal ID', \Wipop::TRANSLATION_DOMAIN_ADMIN),
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'min' => Settings::MIN_TERMINAL_ID,
                    'max' => Settings::MAX_TERMINAL_ID,
                    'step' => 1,
                ],
                'constraints' => [
                    new Range([
                        'min' => Settings::MIN_TERMINAL_ID,
                        'max' => Settings::MAX_TERMINAL_ID,
                    ]),
                ],
            ]);
    }
}
