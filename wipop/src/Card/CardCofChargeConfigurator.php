<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

use Wipop\Operations\Charge\Params\CreateChargeParams;

final class CardCofChargeConfigurator
{
    public function apply(CreateChargeParams $params, CardCofSelection $selection): void
    {
        if (!$selection->requiresUseCof()) {
            return;
        }

        $params->useCof(true);

        if ($selection->storedCard !== null) {
            $params->sourceId($selection->storedCard->sourceId);
        }
    }
}
