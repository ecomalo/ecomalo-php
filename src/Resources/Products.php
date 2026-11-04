<?php

namespace EcomAlo\Resources;

class Products extends Resource
{
    // EcomAlo takes up to 500 rows per call
    private const CHUNK = 500;

    /**
     * Sends your catalogue, so the owner can link each row to an EcomAlo product (auto by SKU, or by hand).
     * One row per sellable item: external_product_id, external_variant_id (null for simple products), sku, title, stock (your count, or null).
     * Sending a row again updates it. Returns how many rows EcomAlo saved.
     */
    public function sync(iterable $rows): int
    {
        $saved = 0;
        $chunk = [];

        foreach ($rows as $row) {
            $chunk[] = $this->row((array) $row);

            if (count($chunk) === self::CHUNK) {
                $saved += $this->post($chunk);
                $chunk = [];
            }
        }

        return $chunk ? $saved + $this->post($chunk) : $saved;
    }

    // EcomAlo's own products (needs merchant_key with products:read); filters: sku, per_page, page
    public function list(array $filters = []): array
    {
        return $this->merchant()->request('GET', 'merchant/products', $filters);
    }

    private function post(array $rows): int
    {
        return (int) ($this->site->request('POST', 'plugin/products', body: ['products' => $rows])['count'] ?? 0);
    }

    private function row(array $row): array
    {
        return [
            'external_product_id' => (string) $row['external_product_id'],
            'external_variant_id' => isset($row['external_variant_id']) && $row['external_variant_id'] !== '' ? (string) $row['external_variant_id'] : null,
            'sku' => isset($row['sku']) && $row['sku'] !== '' ? (string) $row['sku'] : null,
            'title' => mb_substr((string) ($row['title'] ?? $row['name'] ?? ''), 0, 250),
            'stock' => isset($row['stock']) ? (int) $row['stock'] : null,
        ];
    }
}
