<?php

namespace EcomAlo;

use EcomAlo\Exceptions\EcomAloException;
use EcomAlo\Http\Transport;
use EcomAlo\Resources\Orders;
use EcomAlo\Resources\Products;
use EcomAlo\Resources\Stock;
use GuzzleHttp\ClientInterface;

/**
 * The EcomAlo client for one connected site.
 *
 *   api_key       the site token from connecting (ECOMALO_API_KEY): orders, products, cancel, status, fraud check
 *   merchant_key  optional merchant API key from the API keys page: order lookup, stock levels and changes, tracking
 *   base_url      https://api.ecomalo.com/v1 unless you run EcomAlo yourself
 */
class Client
{
    public const DEFAULT_BASE_URL = 'https://api.ecomalo.com/v1';

    private Transport $site;

    private ?Transport $merchant = null;

    public function __construct(array $config)
    {
        $transport = static::transport($config, $config['api_key'] ?? null);
        $this->site = $transport;

        if ((string) ($config['merchant_key'] ?? '') !== '') {
            $this->merchant = $transport->withToken($config['merchant_key']);
        }
    }

    /**
     * Trades the one-time connection code from the EcomAlo panel (Integrations > Connect site > Laravel) for this site's
     * token and webhook secret. Run it once and keep both: the code stops working after this call.
     *
     * @param  string|null  $webhookUrl  where EcomAlo sends order and parcel updates, e.g. https://shop.com/ecomalo/webhook
     */
    public static function connect(string $code, ?string $siteUrl = null, ?string $webhookUrl = null, array $config = []): Connection
    {
        $data = static::transport($config, null)->request('POST', 'plugin/connect', body: array_filter([
            'code' => trim($code),
            'site_url' => $siteUrl,
            'webhook_url' => $webhookUrl,
        ], fn ($value) => $value !== null && $value !== ''));

        return Connection::fromArray($data);
    }

    public function orders(): Orders
    {
        return new Orders($this->site, $this->merchant);
    }

    public function products(): Products
    {
        return new Products($this->site, $this->merchant);
    }

    public function stock(): Stock
    {
        return new Stock($this->site, $this->merchant);
    }

    // the connection as EcomAlo sees it: status, store, last_order_at and the panel's settings
    public function status(): array
    {
        return $this->site->request('GET', 'plugin/status');
    }

    // from EcomAlo's cache only; a first-time number answers ['checking' => true] and is looked up in the background
    public function fraudCheck(string $phone): array
    {
        return $this->site->request('GET', 'plugin/fraud-check', ['phone' => $phone]);
    }

    // courier, status and events of one parcel, by tracking code or consignment id (needs merchant_key with tracking:read)
    public function tracking(string $code): array
    {
        return $this->merchant()->request('GET', 'merchant/tracking/'.rawurlencode($code));
    }

    // revokes this site's token; imported orders stay in EcomAlo
    public function disconnect(): void
    {
        $this->site->request('POST', 'plugin/disconnect');
    }

    private function merchant(): Transport
    {
        if (! $this->merchant) {
            throw new EcomAloException('This call uses the merchant API: set merchant_key (ECOMALO_MERCHANT_KEY) to an API key from the API keys page.');
        }

        return $this->merchant;
    }

    private static function transport(array $config, ?string $token): Transport
    {
        $http = $config['http'] ?? null;

        return new Transport(
            (string) (($config['base_url'] ?? '') ?: self::DEFAULT_BASE_URL),
            $token,
            (float) ($config['timeout'] ?? 15),
            (float) ($config['connect_timeout'] ?? 5),
            (int) ($config['retries'] ?? 2),
            (int) ($config['retry_delay_ms'] ?? 500),
            $http instanceof ClientInterface ? $http : null,
        );
    }
}
