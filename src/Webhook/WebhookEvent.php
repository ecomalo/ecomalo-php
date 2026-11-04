<?php

namespace EcomAlo\Webhook;

/**
 * One verified webhook: {id, event, created_at, data}.
 *
 * order.*  data: order {uid, number, external_id, status, grand_total, cod_amount}, happened_at;
 *          status_changed and cancelled add from, to, to_label
 * parcel.* data: tracking_code, order_uid, order_number, external_id, courier, consignment_id, status, cod_amount, updated_at
 * stock.changed data: variants [{uid, sku}], happened_at; read the new counts with $client->stock()->levels()
 */
final class WebhookEvent
{
    public const ORDER_CREATED = 'order.created';

    public const ORDER_STATUS_CHANGED = 'order.status_changed';

    public const ORDER_CANCELLED = 'order.cancelled';

    public const PARCEL_BOOKED = 'parcel.booked';

    public const PARCEL_STATUS_CHANGED = 'parcel.status_changed';

    public const PARCEL_DELIVERED = 'parcel.delivered';

    public const PARCEL_RETURNED = 'parcel.returned';

    public const STOCK_CHANGED = 'stock.changed';

    public function __construct(
        public readonly string $id,
        public readonly string $event,
        public readonly ?string $createdAt,
        public readonly array $data,
    ) {
    }

    public static function fromArray(array $json, ?string $deliveryId = null): self
    {
        return new self(
            (string) ($json['id'] ?? $deliveryId ?? ''),
            (string) ($json['event'] ?? ''),
            isset($json['created_at']) ? (string) $json['created_at'] : null,
            (array) ($json['data'] ?? []),
        );
    }

    public function is(string ...$events): bool
    {
        return in_array($this->event, $events, true);
    }

    // your own order id, on order and parcel events alike
    public function externalId(): ?string
    {
        $id = $this->data['order']['external_id'] ?? $this->data['external_id'] ?? null;

        return $id === null ? null : (string) $id;
    }

    // order events: the new EcomAlo status (pending, confirmed, booked, shipped, delivered, returned, cancelled, ...)
    public function orderStatus(): ?string
    {
        return $this->data['to'] ?? $this->data['order']['status'] ?? null;
    }

    public function trackingCode(): ?string
    {
        return $this->data['tracking_code'] ?? null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'event' => $this->event, 'created_at' => $this->createdAt, 'data' => $this->data];
    }
}
