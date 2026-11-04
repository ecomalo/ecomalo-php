<?php

namespace EcomAlo;

// what plugin/connect hands back once: store these two in your .env as ECOMALO_API_KEY and ECOMALO_API_SECRET
final class Connection
{
    public function __construct(
        public readonly string $apiKey,
        public readonly ?string $webhookSecret,
        public readonly ?string $storeUid,
        public readonly ?string $storeName,
        public readonly array $settings,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['access_token'] ?? ''),
            isset($data['webhook_secret']) ? (string) $data['webhook_secret'] : null,
            $data['store']['uid'] ?? null,
            $data['store']['name'] ?? null,
            (array) ($data['settings'] ?? []),
        );
    }
}
