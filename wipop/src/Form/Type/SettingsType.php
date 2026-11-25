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

use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\TranslationDomains;
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

if (!defined('_PS_VERSION_')) {
    exit;
}

final class SettingsType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('merchant_id', TextType::class, [
                'label' => $this->trans('Merchant ID', TranslationDomains::ADMIN),
                'required' => false,
            ])
            ->add('live_mode', ChoiceType::class, [
                'label' => $this->trans('Environment', TranslationDomains::ADMIN),
                'required' => true,
                'expanded' => false,
                'multiple' => false,
                'choice_translation_domain' => false,
                'choices' => [
                    $this->trans('Sandbox', TranslationDomains::ADMIN) => false,
                    $this->trans('Production', TranslationDomains::ADMIN) => true,
                ],
            ])
            ->add('capture_mode', ChoiceType::class, [
                'label' => $this->trans('Preauthorizations', TranslationDomains::ADMIN),
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'choice_translation_domain' => false,
                'choices' => [
                    $this->trans('Charge at checkout', TranslationDomains::ADMIN) => CaptureMode::IMMEDIATE->value,
                    $this->trans(
                        'Reserve the amount to charge it later',
                        TranslationDomains::ADMIN
                    ) => CaptureMode::MANUAL->value,
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
            ->add('public_secret_key', PasswordType::class, [
                'label' => $this->trans('Public Key', TranslationDomains::ADMIN),
                'required' => false,
                'empty_data' => '',
                'always_empty' => true,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => $options['public_secret_key_configured'] ? '****' : '',
                ],
            ])
            ->add('secret_key', PasswordType::class, [
                'label' => $this->trans('Private Key', TranslationDomains::ADMIN),
                'required' => false,
                'empty_data' => '',
                'always_empty' => true,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => $options['secret_key_configured'] ? '****' : '',
                ],
            ])
            ->add('enabled_gateways', ChoiceType::class, [
                'label' => $this->trans('Payment methods', TranslationDomains::ADMIN),
                'required' => false,
                'expanded' => true,
                'multiple' => true,
                'choice_translation_domain' => false,
                'choices' => [
                    $this->trans('Card', TranslationDomains::ADMIN) => ChargeMethod::CARD,
                    $this->trans('Bizum', TranslationDomains::ADMIN) => ChargeMethod::BIZUM,
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
