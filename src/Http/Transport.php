<?php

namespace EcomAlo\Http;

use EcomAlo\Exceptions\ApiException;
use EcomAlo\Exceptions\ConnectionException;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;

// one JSON call to EcomAlo with the bearer token, retried on lost connections, 429 and 5xx.
// EcomAlo answers {remark, status, message: {success|error: []}, data}; this returns data
class Transport
{
    public const VERSION = '1.0.0';

    private ClientInterface $http;

    public function __construct(
        private string $baseUrl,
        private ?string $token,
        private float $timeout = 15.0,
        private float $connectTimeout = 5.0,
        private int $retries = 2,
        private int $retryDelayMs = 500,
        ?ClientInterface $http = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http = $http ?? new Guzzle(['http_errors' => false]);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function hasToken(): bool
    {
        return (string) $this->token !== '';
    }

    public function withToken(?string $token): self
    {
        $copy = clone $this;
        $copy->token = $token;

        return $copy;
    }

    /**
     * @param  bool  $idempotent  false for calls that must never run twice (a stock change), so only a refused connection is retried
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, bool $idempotent = true): array
    {
        $attempt = 0;

        while (true) {
            try {
                $response = $this->http->request($method, $this->baseUrl.'/'.ltrim($path, '/'), $this->options($query, $body));
            } catch (TransferException $e) {
                if ($attempt < $this->retries && ($idempotent || $this->neverSent($e))) {
                    $this->pause($attempt++);

                    continue;
                }

                throw new ConnectionException('EcomAlo could not be reached: '.$e->getMessage(), 0, $e);
            } catch (GuzzleException $e) {
                throw new ConnectionException('EcomAlo could not be reached: '.$e->getMessage(), 0, $e);
            }

            $status = $response->getStatusCode();
            $json = $this->decode($response);

            if ($status >= 200 && $status < 300) {
                return (array) ($json['data'] ?? []);
            }

            $exception = ApiException::fromResponse($status, $json);

            if ($idempotent && $exception->isRetryable() && $attempt < $this->retries) {
                $this->pause($attempt++, $this->retryAfter($response));

                continue;
            }

            throw $exception;
        }
    }

    private function options(array $query, ?array $body): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'ecomalo-php/'.self::VERSION.' PHP/'.PHP_VERSION,
        ];

        if ((string) $this->token !== '') {
            $headers['Authorization'] = 'Bearer '.$this->token;
        }

        $options = [
            'headers' => $headers,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'http_errors' => false,
        ];

        if ($query) {
            $options['query'] = $query;
        }

        if ($body !== null) {
            $options['json'] = $body;
        }

        return $options;
    }

    private function decode(ResponseInterface $response): array
    {
        $json = json_decode((string) $response->getBody(), true);

        return is_array($json) ? $json : [];
    }

    // failed before a byte was sent, so even a stock change is safe to repeat. Guzzle 8 keeps ConnectException for exactly that;
    // Guzzle 7 also uses it for timeouts mid-transfer, so there only cURL 6 (DNS) and 7 (refused) count
    private function neverSent(TransferException $e): bool
    {
        if (class_exists(\GuzzleHttp\Exception\ConnectTimeoutException::class)) {
            return $e instanceof \GuzzleHttp\Exception\ConnectException;
        }

        return (bool) preg_match('/cURL error (6|7):/', $e->getMessage());
    }

    private function retryAfter(ResponseInterface $response): ?int
    {
        $header = $response->getHeaderLine('Retry-After');

        return is_numeric($header) ? min((int) $header, 30) * 1000 : null;
    }

    // 0.5s, 1s, 2s... with a little jitter, or what Retry-After asked for
    private function pause(int $attempt, ?int $ms = null): void
    {
        $ms ??= (int) ($this->retryDelayMs * (2 ** $attempt) + random_int(0, 100));

        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
