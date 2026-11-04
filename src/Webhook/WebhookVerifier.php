<?php

namespace EcomAlo\Webhook;

use EcomAlo\Exceptions\InvalidSignatureException;

/**
 * Checks a webhook from EcomAlo. Each one carries:
 *   X-EcomOS-Event      order.status_changed, parcel.booked, ...
 *   X-EcomOS-Delivery   the delivery id; a retry of the same delivery keeps it
 *   X-EcomOS-Timestamp  unix seconds
 *   X-EcomOS-Signature  "sha256=" . hex HMAC-SHA256 of "<timestamp>.<raw body>" with the webhook secret
 * Older than 5 minutes is refused, so a captured call cannot be replayed later.
 */
class WebhookVerifier
{
    public const MAX_AGE_SECONDS = 300;

    public function __construct(private string $secret, private int $maxAge = self::MAX_AGE_SECONDS)
    {
    }

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function isValid(string $body, ?string $signature, int|string|null $timestamp, ?int $now = null): bool
    {
        $now ??= time();

        if ($this->secret === '' || ! $signature || ! is_numeric($timestamp) || abs($now - (int) $timestamp) > $this->maxAge) {
            return false;
        }

        return hash_equals(static::sign($this->secret, (int) $timestamp, $body), $signature);
    }

    /**
     * Verifies and parses in one step. $headers is any header array (case does not matter, values may be arrays).
     *
     * @throws InvalidSignatureException
     */
    public function verify(string $body, array $headers, ?int $now = null): WebhookEvent
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        $header = function (string $name) use ($headers): ?string {
            $value = $headers[$name] ?? null;

            return is_array($value) ? (isset($value[0]) ? (string) $value[0] : null) : ($value === null ? null : (string) $value);
        };

        if (! $this->isValid($body, $header('x-ecomos-signature'), $header('x-ecomos-timestamp'), $now)) {
            throw new InvalidSignatureException('EcomAlo webhook signature is missing, wrong or too old.');
        }

        $json = json_decode($body, true);

        if (! is_array($json)) {
            throw new InvalidSignatureException('EcomAlo webhook body is not JSON.');
        }

        return WebhookEvent::fromArray($json, $header('x-ecomos-delivery'));
    }

    // plain PHP: reads php://input and the server headers of the current request
    public function verifyCurrentRequest(?int $now = null): WebhookEvent
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }

        return $this->verify((string) file_get_contents('php://input'), $headers, $now);
    }
}
