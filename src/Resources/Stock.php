<?php

namespace EcomAlo\Resources;

class Stock extends Resource
{
    /**
     * Reports your shop's own stock counts to EcomAlo (shown next to each linked product).
     * Rows as Products::sync(): external_product_id, external_variant_id, sku, title, stock.
     */
    public function push(iterable $rows): int
    {
        return (new Products($this->site, $this->merchant))->sync($rows);
    }

    /**
     * Available stock in the store's default EcomAlo warehouse (needs merchant_key with stock:read).
     * Returns ['items' => [['uid', 'sku', 'available'], ...], 'pagination' => [...]].
     */
    public function levels(array $skus = [], int $page = 1, int $perPage = 50): array
    {
        return $this->merchant()->request('GET', 'merchant/stock', array_filter(['sku' => $skus ?: null, 'page' => $page, 'per_page' => $perPage]));
    }

    /**
     * Changes EcomAlo's stock by a delta: +5 found, -2 written off (needs merchant_key with stock:write).
     * Never retried once it may have reached EcomAlo, so a timeout cannot count twice.
     */
    public function adjust(string $sku, float|int $qty, ?string $note = null): array
    {
        return $this->merchant()->request('POST', 'merchant/stock/adjust', body: array_filter(['sku' => $sku, 'qty' => $qty, 'note' => $note], fn ($value) => $value !== null), idempotent: false);
    }
}
