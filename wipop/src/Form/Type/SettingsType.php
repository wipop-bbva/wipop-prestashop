<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Form\Type;

use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;
use Wipop\Domain\ChargeMethod;

final class SettingsType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('live_mode', SwitchType::class, [
                'label' => $this->trans('Live mode', TranslationDomains::ADMIN),
                'required' => false,
            ])
            ->add('merchant_id', TextType::class, [
                'label' => $this->trans('Merchant ID', TranslationDomains::ADMIN),
                'required' => false,
            ])
            ->add('secret_key', PasswordType::class, [
                'label' => $this->trans('Secret key', TranslationDomains::ADMIN),
                'required' => false,
                'empty_data' => '',
                'always_empty' => true,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => $options['secret_key_configured'] ? '****' : '',
                ],
            ])
            ->add('public_secret_key', PasswordType::class, [
                'label' => $this->trans('Public secret key', TranslationDomains::ADMIN),
                'required' => false,
                'empty_data' => '',
                'always_empty' => true,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => $options['public_secret_key_configured'] ? '****' : '',
                ],
            ])
            ->add('terminal_id', IntegerType::class, [
                'label' => $this->trans('Terminal ID', TranslationDomains::ADMIN),
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
            ])
            ->add('capture_mode', ChoiceType::class, [
                'label' => $this->trans('Preauthorizations', TranslationDomains::ADMIN),
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'choice_translation_domain' => TranslationDomains::ADMIN,
                'help' => $this->trans(
                    'If you choose to reserve the amount, you will need to confirm or cancel the charge manually'
                    . ' from the order details. Only applies to card payments.',
                    TranslationDomains::ADMIN
                ),
                'choices' => [
                    'Charge at checkout' => CaptureMode::IMMEDIATE->value,
                    'Reserve the amount to charge it later' => CaptureMode::MANUAL->value,
                ],
            ])
            ->add('enabled_gateways', ChoiceType::class, [
                'label' => $this->trans('Payment methods', TranslationDomains::ADMIN),
                'required' => false,
                'expanded' => true,
                'multiple' => true,
                'choice_translation_domain' => TranslationDomains::ADMIN,
                'choices' => [
                    'Card' => ChargeMethod::CARD,
                    'Bizum' => ChargeMethod::BIZUM,
                ],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $enabledGateways = $event->getData()['enabled_gateways'];
            if ($enabledGateways !== []) {
                return;
            }

            $event->getForm()->get('enabled_gateways')->addError(new FormError(
                $this->trans(
                    'Choose at least one Wipop method.',
                    TranslationDomains::ADMIN
                )
            ));
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'secret_key_configured' => false,
            'public_secret_key_configured' => false,
        ]);
        $resolver->setAllowedTypes('secret_key_configured', 'bool');
        $resolver->setAllowedTypes('public_secret_key_configured', 'bool');
    }
}
