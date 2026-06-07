<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

use Wipop\Domain\ChargeMethod;

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
    ): CardCofSelection {
        if (strtoupper(trim($paymentMethod)) !== ChargeMethod::CARD || $customerId === null || $customerId <= 0) {
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

        return $saveCard ? CardCofSelection::save() : CardCofSelection::none();
    }
}
