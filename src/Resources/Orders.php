<?php

namespace EcomAlo\Resources;

use InvalidArgumentException;

class Orders extends Resource
{
    // what EcomAlo accepts on plugin/orders; anything else is dropped before sending
    private const FIELDS = [
        'external_id', 'external_number', 'customer_name', 'phone', 'alt_phone', 'email', 'address', 'district', 'thana',
        'delivery_charge', 'discount', 'customer_note', 'paid_amount', 'payment_method', 'items', 'tracking',
    ];

    private const ITEM_FIELDS = ['external_product_id', 'external_variant_id', 'sku', 'name', 'qty', 'unit_price'];

    private const TRACKING_FIELDS = ['fbp', 'fbc', 'ip', 'user_agent', 'utm_source', 'utm_medium', 'utm_campaign', 'landing_url'];

    /**
     * Sends one order. EcomAlo queues it and answers at once with ['external_id' => ...].
     * The same external_id again never makes a second order; sending it again with a higher paid_amount records the new payment.
     *
     * Required: external_id, customer_name, phone, address, items[] (name, qty, unit_price).
     * unit_price is the price before the order discount; the discount and delivery_charge go on the order.
     */
    public function send(array $order): array
    {
        return $this->site->request('POST', 'plugin/orders', body: $this->payload($order));
    }

    // cancelled on your shop; an order already handed to a courier stays and this throws ValidationException (remark cannot_cancel)
    public function cancel(string|int $externalId): void
    {
        $this->site->request('POST', 'plugin/orders/cancel', body: ['external_id' => (string) $externalId]);
    }

    // the EcomAlo order for your order id, or null while it is still queued (needs merchant_key with orders:read)
    public function find(string|int $externalId): ?array
    {
        $page = $this->merchant()->request('GET', 'merchant/orders', ['external_id' => (string) $externalId, 'per_page' => 1]);

        return $page['items'][0] ?? null;
    }

    // one order by EcomAlo's uid (needs merchant_key with orders:read)
    public function get(string $uid): array
    {
        return (array) ($this->merchant()->request('GET', 'merchant/orders/'.rawurlencode($uid))['order'] ?? []);
    }

    /**
     * Filters: status (pending, confirmed, shipped, delivered, ...), external_id, updated_since (ISO date), per_page (max 50), page.
     * Returns ['items' => [...], 'pagination' => ['current_page', 'last_page', 'total']].
     */
    public function list(array $filters = []): array
    {
        return $this->merchant()->request('GET', 'merchant/orders', $filters);
    }

    /** @internal */
    public function payload(array $order): array
    {
        $order = array_intersect_key($order, array_flip(self::FIELDS));

        foreach (['external_id', 'customer_name', 'phone', 'address'] as $field) {
            if (! isset($order[$field]) || trim((string) $order[$field]) === '') {
                throw new InvalidArgumentException("EcomAlo order needs {$field}.");
            }
        }

        if (empty($order['items']) || ! is_array($order['items'])) {
            throw new InvalidArgumentException('EcomAlo order needs at least one item.');
        }

        foreach (['external_id', 'external_number', 'phone', 'alt_phone'] as $field) {
            if (isset($order[$field])) {
                $order[$field] = (string) $order[$field];
            }
        }

        foreach (['delivery_charge', 'discount', 'paid_amount'] as $field) {
            if (isset($order[$field])) {
                $order[$field] = round((float) $order[$field], 2);
            }
        }

        $order['items'] = array_map(function ($item) {
            $item = array_intersect_key((array) $item, array_flip(self::ITEM_FIELDS));

            foreach (['external_product_id', 'external_variant_id', 'sku'] as $field) {
                if (isset($item[$field])) {
                    $item[$field] = (string) $item[$field];
                }
            }

            if (isset($item['unit_price'])) {
                $item['unit_price'] = round((float) $item['unit_price'], 2);
            }

            return $item;
        }, array_values($order['items']));

        if (isset($order['tracking'])) {
            $order['tracking'] = array_filter(array_intersect_key((array) $order['tracking'], array_flip(self::TRACKING_FIELDS)), fn ($value) => $value !== null && $value !== '');
        }

        return array_filter($order, fn ($value) => $value !== null);
    }
}
