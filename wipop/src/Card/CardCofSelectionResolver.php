<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

use PrestaShop\Module\Wipop\Configuration\Settings;

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
