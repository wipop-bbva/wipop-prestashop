<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

use function sprintf;
use function strtoupper;
use function trim;

final class StoredCardPresenter
{
    /**
     * @return array{id: int, brand: string, last4: string, expiry: ?string, label: string}
     */
    public function present(StoredCard|StoredCardSummary $card): array
    {
        $brand = strtoupper(trim((string) $card->brand));
        if ($brand === '') {
            $brand = 'Card';
        }

        $last4 = trim((string) $card->last4);
        $label = $last4 === '' ? $brand : sprintf('%s •••• %s', $brand, $last4);

        return [
            'id' => $card->id,
            'brand' => $brand,
            'last4' => $last4,
            'expiry' => $this->expiry($card),
            'label' => $label,
        ];
    }

    private function expiry(StoredCard|StoredCardSummary $card): ?string
    {
        $month = trim((string) $card->expirationMonth);
        $year = trim((string) $card->expirationYear);

        return $month === '' || $year === '' ? null : sprintf('%s/%s', $month, $year);
    }
}
