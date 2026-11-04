# EcomAlo PHP

Connect your own Laravel or plain PHP shop to [EcomAlo](https://ecomalo.com), the same way the EcomAlo WooCommerce plugin does:

- send each new order to EcomAlo for confirmation, fraud check and courier booking
- receive order status and parcel/tracking updates back as signed webhooks
- send your product list (with your stock counts) so the owner can link products in EcomAlo
- optionally read and change EcomAlo stock, look up orders and parcels (with a merchant API key)

Requires PHP 8.1+ and Guzzle 7. Laravel 10, 11, 12 and 13 are supported through auto-discovery.

## 1. Get a connection code

In the EcomAlo panel: **Integrations > Connect site**, type **Laravel** (or **Custom** for plain PHP), enter your shop address, and press connect. EcomAlo shows a one-time connection code. It works once and expires after 24 hours.

## 2. Install

```bash
composer require ecomalo/ecomalo-php
```

---

## Laravel

### Connect

```bash
php artisan ecomalo:connect <code> --write
```

This trades the code for your site's credentials and writes them to `.env`:

```dotenv
ECOMALO_API_KEY=...       # the site token
ECOMALO_API_SECRET=...    # the webhook secret
```

It also tells EcomAlo where to send updates: `APP_URL/ecomalo/webhook` by default (change it with `--webhook-url=`, and the shop address with `--site-url=`). Without `--write` it only prints the two lines; they are shown once.

Optional settings:

```dotenv
ECOMALO_BASE_URL=https://api.ecomalo.com/v1   # default
ECOMALO_MERCHANT_KEY=                         # merchant API key, see "Merchant API" below
ECOMALO_TIMEOUT=15
ECOMALO_RETRIES=2
ECOMALO_WEBHOOK_PATH=ecomalo/webhook
ECOMALO_WEBHOOK_ENABLED=true
```

To change more, publish the config file:

```bash
php artisan vendor:publish --tag=ecomalo-config
```

If you cache config, run `php artisan config:cache` after editing `.env`.

### Send an order

Send orders from a queued job, never inside the checkout request, so a slow network can never hold up a buyer.

```php
use EcomAlo\Laravel\Facades\EcomAlo;

EcomAlo::orders()->send([
    'external_id'     => (string) $order->id,     // required, your order id
    'external_number' => $order->number,
    'customer_name'   => $order->name,            // required
    'phone'           => $order->phone,           // required
    'alt_phone'       => null,
    'email'           => $order->email,
    'address'         => $order->address,         // required
    'district'        => 'Dhaka',
    'thana'           => 'Dhanmondi',
    'delivery_charge' => 60,
    'discount'        => 0,
    'customer_note'   => $order->note,
    'paid_amount'     => 0,                       // 0 for cash on delivery
    'payment_method'  => null,                    // e.g. "bKash" when paid_amount > 0
    'items' => [
        [
            'external_product_id' => '12',
            'external_variant_id' => '34',        // null for a simple product
            'sku'        => 'TSHIRT-RED-L',
            'name'       => 'T-shirt Red L',      // required
            'qty'        => 2,                    // required
            'unit_price' => 450,                  // required, price before the order discount
        ],
    ],
    'tracking' => [                               // optional, for Meta conversion tracking
        'fbp' => $request->cookie('_fbp'),
        'fbc' => $request->cookie('_fbc'),
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null, 'landing_url' => null,
    ],
]);
```

EcomAlo queues the order and answers at once. The same `external_id` never makes a second order, so retries are safe. To record a payment made later (for example after an online payment), send the same order again with the new, higher `paid_amount`; nothing else on the order changes.

Cancelled on your side:

```php
EcomAlo::orders()->cancel($order->id);
```

An order already handed to a courier is not cancelled; this throws `ValidationException` with remark `cannot_cancel`.

### Receive updates (webhooks)

The package registers `POST /ecomalo/webhook`, checks the signature, and fires Laravel events. Listen to the ones you need:

| Event class (`EcomAlo\Laravel\Events\…`) | EcomAlo event | Sent automatically |
|---|---|---|
| `WebhookReceived` | every event | |
| `OrderStatusChanged` | `order.status_changed` | yes |
| `OrderCancelled` | `order.cancelled` | yes |
| `ParcelBooked` | `parcel.booked` | yes |
| `ParcelStatusChanged` | `parcel.status_changed` | yes |
| `ParcelDelivered` | `parcel.delivered` | yes |
| `ParcelReturned` | `parcel.returned` | yes |
| `OrderCreated` | `order.created` | add on the Webhooks page |
| `StockChanged` | `stock.changed` | add on the Webhooks page |

```php
use EcomAlo\Laravel\Events\OrderStatusChanged;
use EcomAlo\Laravel\Events\ParcelBooked;
use Illuminate\Support\Facades\Event;

Event::listen(function (OrderStatusChanged $e) {
    Order::where('id', $e->webhook->externalId())->update(['ecomalo_status' => $e->webhook->orderStatus()]);
});

Event::listen(function (ParcelBooked $e) {
    Order::where('id', $e->webhook->externalId())->update([
        'courier'       => $e->webhook->get('courier'),        // steadfast, pathao, redx, ...
        'tracking_code' => $e->webhook->trackingCode(),
    ]);
});
```

Order statuses: `pending`, `hold`, `confirmed`, `ready`, `booked`, `shipped`, `delivered`, `partial`, `returned`, `cancelled`, `lost`.

Payloads (`$e->webhook->data`):

- `order.*`: `order` {uid, number, external_id, status, grand_total, cod_amount}, `happened_at`; status changes add `from`, `to`, `to_label`
- `parcel.*`: tracking_code, order_uid, order_number, external_id, courier, consignment_id, status, cod_amount, updated_at
- `stock.changed`: `variants` [{uid, sku}], `happened_at`

EcomAlo waits 10 seconds for a 2xx answer and retries a failed delivery later with the same delivery id. The controller answers a repeated id without firing events again (for `ECOMALO_WEBHOOK_DEDUPE_MINUTES`, default one day, using your cache). Put slow work in listeners that implement `ShouldQueue`. If a listener throws, the delivery is not marked as seen, so EcomAlo's retry runs it again.

The route has no `web` middleware, so no CSRF token is needed. To use your own route instead, set `ECOMALO_WEBHOOK_ENABLED=false` and call `WebhookVerifier` yourself (see plain PHP below).

### Products and stock

Send your catalogue once, and again when products change. One row per sellable item:

```php
EcomAlo::products()->sync(
    Product::with('variants')->cursor()->flatMap(fn ($p) => $p->variants->map(fn ($v) => [
        'external_product_id' => (string) $p->id,
        'external_variant_id' => (string) $v->id,   // null for a simple product
        'sku'   => $v->sku,
        'title' => $p->name.' '.$v->name,
        'stock' => $v->stock,                       // your own count, or null
    ]))
); // returns the number of rows saved; sent 500 per call
```

The owner then links each row to an EcomAlo product (automatically by SKU, or by hand). `EcomAlo::stock()->push($rows)` is the same call, for when only the counts changed.

### Other calls

```php
EcomAlo::status();                 // connection status, store, last_order_at, panel settings
EcomAlo::fraudCheck('01711000000'); // ['checking' => true] the first time, then the score
EcomAlo::disconnect();             // revokes the token; imported orders stay
```

---

## Plain PHP

```php
require 'vendor/autoload.php';

use EcomAlo\Client;

// once: trade the code, then keep both values somewhere safe (they are shown only once)
$connection = Client::connect('<code>', 'https://myshop.com', 'https://myshop.com/ecomalo-webhook.php');
// $connection->apiKey, $connection->webhookSecret, $connection->storeName, $connection->settings

$ecomalo = new Client([
    'api_key'      => getenv('ECOMALO_API_KEY'),
    'merchant_key' => getenv('ECOMALO_MERCHANT_KEY') ?: null, // optional
    'base_url'     => 'https://api.ecomalo.com/v1',            // default
    'timeout'      => 15,
    'connect_timeout' => 5,
    'retries'      => 2,
]);

$ecomalo->orders()->send([...]);   // same fields as the Laravel example
$ecomalo->orders()->cancel('1001');
$ecomalo->products()->sync([...]);
```

Webhook endpoint (`ecomalo-webhook.php`):

```php
require 'vendor/autoload.php';

use EcomAlo\Exceptions\InvalidSignatureException;
use EcomAlo\Webhook\WebhookVerifier;

try {
    $event = (new WebhookVerifier(getenv('ECOMALO_API_SECRET')))->verifyCurrentRequest();
} catch (InvalidSignatureException $e) {
    http_response_code(401);
    exit;
}

if ($event->is('order.status_changed')) {
    // $event->externalId(), $event->orderStatus()
}

if ($event->is('parcel.booked')) {
    // $event->trackingCode(), $event->get('courier')
}

echo '{"ok":true}';
```

With a framework request object, pass the raw body and headers instead: `$verifier->verify($rawBody, $headers)`.

### How the signature works

Each webhook carries `X-EcomOS-Event`, `X-EcomOS-Delivery`, `X-EcomOS-Timestamp` and `X-EcomOS-Signature`. The signature is `sha256=` followed by the hex HMAC-SHA256 of `<timestamp>.<raw body>`, keyed with your webhook secret. Calls older than 5 minutes are refused, so make sure your server clock is right.

---

## Merchant API (optional)

Some calls use a merchant API key instead of the site token. Create one in the **API keys** page of the EcomAlo panel for the same store, with the scopes you need, and set `merchant_key` / `ECOMALO_MERCHANT_KEY`.

```php
EcomAlo::orders()->find('1001');        // EcomAlo's order for your id, or null while still queued (orders:read)
EcomAlo::orders()->get($uid);           // by EcomAlo uid (orders:read)
EcomAlo::orders()->list(['status' => 'shipped', 'updated_since' => '2026-11-01', 'per_page' => 50]);
EcomAlo::products()->list(['sku' => 'TSHIRT-RED-L']);   // products:read
EcomAlo::stock()->levels(['TSHIRT-RED-L']);             // available in the store's warehouse (stock:read)
EcomAlo::stock()->adjust('TSHIRT-RED-L', -2, 'damaged'); // change EcomAlo stock by a delta (stock:write)
EcomAlo::tracking('DH123456');          // parcel status and events (tracking:read)
```

To keep your shop's stock in step with EcomAlo, add a `stock.changed` webhook on the Webhooks page, listen to `StockChanged`, and read the new counts with `stock()->levels()` for the SKUs in `$e->webhook->get('variants')`.

A call that needs the merchant key throws `EcomAloException` when it is not set.

---

## Errors

Everything thrown extends `EcomAlo\Exceptions\EcomAloException`.

| Exception | When |
|---|---|
| `ConnectionException` | EcomAlo could not be reached after all retries; try again later |
| `AuthenticationException` (401) | wrong or revoked token, or the site was disconnected in the panel |
| `PermissionException` (403) | merchant key lacks the scope, or the subscription is inactive |
| `NotFoundException` (404) | no such order, SKU or parcel |
| `ValidationException` (422) | the payload was refused; `getErrors()` lists why |
| `RateLimitException` (429) | too many calls (300 a minute per site, 120 per merchant key) |
| `ServerException` (5xx) | EcomAlo failed on its side |
| `InvalidSignatureException` | a webhook did not verify |

All API exceptions have `getStatus()`, `getRemark()`, `getErrors()`, `getBody()` and `isRetryable()`. A missing required order field throws `InvalidArgumentException` before any network call.

Lost connections, 429 and 5xx answers are retried (`retries`, default 2, with 0.5s, 1s... back-off and `Retry-After`). `stock()->adjust()` is a change, not a total, so it is only retried when the connection was refused before anything was sent.

## License

MIT
