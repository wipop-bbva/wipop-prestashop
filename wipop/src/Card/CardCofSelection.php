<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

final class CardCofSelection
{
    private function __construct(
        public readonly CardCofIntent $intent,
        public readonly ?StoredCard $storedCard = null,
    ) {
    }

    public static function none(): self
    {
        return new self(CardCofIntent::NONE);
    }

    public static function save(): self
    {
        return new self(CardCofIntent::SAVE);
    }

    public static function useExisting(StoredCard $storedCard): self
    {
        return new self(CardCofIntent::USE_EXISTING, $storedCard);
    }

    public function requiresUseCof(): bool
    {
        return $this->intent !== CardCofIntent::NONE;
    }

    public function requiresRedirectUrl(): bool
    {
        return $this->storedCard === null;
    }

    public function storedCardId(): ?int
    {
        return $this->storedCard?->id;
    }

    public function wipopCustomerId(): ?string
    {
        return $this->storedCard?->wipopCustomerId;
    }
}
