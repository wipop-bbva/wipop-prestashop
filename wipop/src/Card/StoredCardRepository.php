<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

use DateTimeImmutable;
use Db;
use PrestaShop\Module\Wipop\Security\SecretCipher;
use RuntimeException;

use function pSQL;
use function sprintf;
use function trim;

final class StoredCardRepository implements StoredCardProviderInterface
{
    public function __construct(
        private Db $db,
        private SecretCipher $cipher,
    ) {
    }

    public static function createDefault(): self
    {
        return new self(Db::getInstance(), new SecretCipher());
    }

    /**
     * @return StoredCardSummary[]
     */
    public function findActiveByCustomer(
        int $customerId,
        int $shopId,
        string $merchantId,
        bool $isLive,
        int $terminalId,
    ): array {
        if ($customerId <= 0) {
            return [];
        }
        $merchantId = trim($merchantId);
        if ($merchantId === '') {
            return [];
        }
        $scopeWhere = $this->activeCustomerCardScopeWhere($customerId, $shopId, $merchantId, $isLive, $terminalId);

        $sql = sprintf(
            'SELECT
                `id_wipop_stored_card`,
                `brand`,
                `last4`,
                `expiration_month`,
                `expiration_year`
            FROM `%swipop_stored_card`
            WHERE %s
            ORDER BY `last_used_at` DESC, `id_wipop_stored_card` DESC',
            _DB_PREFIX_,
            $scopeWhere
        );

        $rows = $this->db->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $cards = [];
        foreach ($rows as $row) {
            $cards[] = $this->mapSummaryRow($row);
        }

        return $cards;
    }

    public function findActiveByIdForCustomer(
        int $cardId,
        int $customerId,
        int $shopId,
        string $merchantId,
        bool $isLive,
        int $terminalId,
    ): ?StoredCard {
        if ($cardId <= 0 || $customerId <= 0) {
            return null;
        }
        $merchantId = trim($merchantId);
        if ($merchantId === '') {
            return null;
        }
        $scopeWhere = $this->activeCustomerCardScopeWhere($customerId, $shopId, $merchantId, $isLive, $terminalId);

        $sql = sprintf(
            'SELECT *
            FROM `%swipop_stored_card`
            WHERE `id_wipop_stored_card` = %d
              AND %s',
            _DB_PREFIX_,
            $cardId,
            $scopeWhere
        );

        $row = $this->db->getRow($sql);

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function store(
        int $customerId,
        int $shopId,
        string $merchantId,
        bool $isLive,
        int $terminalId,
        StoredCardDetails $details,
        ?string $wipopCustomerId,
        int $saveAttemptId,
    ): void {
        $merchantId = trim($merchantId);
        if ($customerId <= 0 || $merchantId === '' || $saveAttemptId <= 0) {
            return;
        }

        $now = $this->currentDateTime();
        $sourceIdHash = $this->cipher->fingerprint($details->sourceId);

        $sql = sprintf(
            'INSERT INTO `%swipop_stored_card` (
                `id_customer`,
                `id_shop`,
                `merchant_id`,
                `is_live`,
                `terminal_id`,
                `source_id_encrypted`,
                `source_id_hash`,
                `wipop_customer_id`,
                `brand`,
                `last4`,
                `expiration_month`,
                `expiration_year`,
                `active`,
                `last_save_attempt_id`,
                `forgotten_after_attempt_id`,
                `last_used_at`,
                `date_add`,
                `date_upd`
            ) VALUES (
                %d,
                %d,
                \'%s\',
                %d,
                %d,
                \'%s\',
                \'%s\',
                %s,
                %s,
                %s,
                %s,
                %s,
                1,
                %d,
                0,
                \'%s\',
                \'%s\',
                \'%s\'
            ) ON DUPLICATE KEY UPDATE
                `source_id_encrypted` = VALUES(`source_id_encrypted`),
                `wipop_customer_id` = COALESCE(VALUES(`wipop_customer_id`), `wipop_customer_id`),
                `brand` = COALESCE(VALUES(`brand`), `brand`),
                `last4` = COALESCE(VALUES(`last4`), `last4`),
                `expiration_month` = COALESCE(VALUES(`expiration_month`), `expiration_month`),
                `expiration_year` = COALESCE(VALUES(`expiration_year`), `expiration_year`),
                `active` = IF(
                    VALUES(`last_save_attempt_id`) <= `forgotten_after_attempt_id`,
                    `active`,
                    VALUES(`active`)
                ),
                `last_used_at` = IF(
                    VALUES(`last_save_attempt_id`) >= `last_save_attempt_id`,
                    VALUES(`last_used_at`),
                    `last_used_at`
                ),
                `last_save_attempt_id` = GREATEST(`last_save_attempt_id`, VALUES(`last_save_attempt_id`)),
                `date_upd` = VALUES(`date_upd`)',
            _DB_PREFIX_,
            $customerId,
            $shopId,
            pSQL($merchantId),
            $isLive ? 1 : 0,
            $terminalId,
            pSQL($this->cipher->encrypt($details->sourceId)),
            pSQL($sourceIdHash),
            $this->sqlNullableString($wipopCustomerId),
            $this->sqlNullableString($details->brand),
            $this->sqlNullableString($details->last4),
            $this->sqlNullableString($details->expirationMonth),
            $this->sqlNullableString($details->expirationYear),
            $saveAttemptId,
            pSQL($now),
            pSQL($now),
            pSQL($now)
        );

        if (!$this->db->execute($sql)) {
            throw new RuntimeException('Unable to save Wipop stored card.');
        }
    }

    public function markUsed(int $cardId): void
    {
        if ($cardId <= 0) {
            return;
        }

        $now = $this->currentDateTime();
        $data = [
            'last_used_at' => pSQL($now),
            'date_upd' => pSQL($now),
        ];
        $condition = sprintf('id_wipop_stored_card = %d', $cardId);

        if (!$this->db->update('wipop_stored_card', $data, $condition, 0, true)) {
            throw new RuntimeException('Unable to update Wipop stored card.');
        }
    }

    public function findActiveBySaveAttemptId(int $attemptId): ?StoredCard
    {
        if ($attemptId <= 0) {
            return null;
        }

        $sql = sprintf(
            'SELECT *
            FROM `%swipop_stored_card`
            WHERE `last_save_attempt_id` = %d
              AND `active` = 1
            ORDER BY `id_wipop_stored_card` DESC',
            _DB_PREFIX_,
            $attemptId
        );

        $row = $this->db->getRow($sql);

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function deactivate(int $cardId, int $customerId, int $shopId): void
    {
        if ($cardId <= 0 || $customerId <= 0) {
            return;
        }

        $now = $this->currentDateTime();
        $sql = sprintf(
            'UPDATE `%swipop_stored_card`
            SET
                `active` = 0,
                `forgotten_after_attempt_id` = `last_save_attempt_id`,
                `date_upd` = \'%s\'
            WHERE `id_wipop_stored_card` = %d
              AND `id_customer` = %d
              AND `id_shop` = %d',
            _DB_PREFIX_,
            pSQL($now),
            $cardId,
            $customerId,
            $shopId
        );

        if (!$this->db->execute($sql)) {
            throw new RuntimeException('Unable to forget Wipop stored card.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): StoredCard
    {
        return new StoredCard(
            id: (int) $row['id_wipop_stored_card'],
            customerId: (int) $row['id_customer'],
            shopId: (int) $row['id_shop'],
            merchantId: (string) $row['merchant_id'],
            isLive: (bool) $row['is_live'],
            terminalId: (int) $row['terminal_id'],
            sourceId: $this->cipher->decrypt((string) $row['source_id_encrypted']),
            wipopCustomerId: $this->nullableString($row['wipop_customer_id']),
            brand: $this->nullableString($row['brand']),
            last4: $this->nullableString($row['last4']),
            expirationMonth: $this->nullableString($row['expiration_month']),
            expirationYear: $this->nullableString($row['expiration_year']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapSummaryRow(array $row): StoredCardSummary
    {
        return new StoredCardSummary(
            id: (int) $row['id_wipop_stored_card'],
            brand: $this->nullableString($row['brand']),
            last4: $this->nullableString($row['last4']),
            expirationMonth: $this->nullableString($row['expiration_month']),
            expirationYear: $this->nullableString($row['expiration_year']),
        );
    }

    private function activeCustomerCardScopeWhere(
        int $customerId,
        int $shopId,
        string $merchantId,
        bool $isLive,
        int $terminalId,
    ): string {
        return sprintf(
            '`id_customer` = %d
              AND `id_shop` = %d
              AND `merchant_id` = \'%s\'
              AND `is_live` = %d
              AND `terminal_id` = %d
              AND `active` = 1',
            $customerId,
            $shopId,
            pSQL($merchantId),
            $isLive ? 1 : 0,
            $terminalId
        );
    }

    private function sqlNullableString(?string $value): string
    {
        $value = $this->nullableString($value);

        return $value === null ? 'NULL' : sprintf('\'%s\'', pSQL($value));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function currentDateTime(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
