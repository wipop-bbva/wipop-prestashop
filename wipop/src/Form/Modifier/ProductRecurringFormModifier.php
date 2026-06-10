<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Form\Modifier;

use PrestaShop\Module\Wipop\Form\Type\ProductRecurringSettingsType;
use PrestaShop\Module\Wipop\Recurring\ProductRecurringSettingsRepository;
use PrestaShopBundle\Form\FormBuilderModifier;
use Symfony\Component\Form\FormBuilderInterface;

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
