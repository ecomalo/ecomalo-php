<?php

namespace EcomAlo\Exceptions;

use Throwable;

// EcomAlo answered with an error. remark is the machine-readable reason (validation_error, not_found, ...),
// errors the human messages from message.error[]
class ApiException extends EcomAloException
{
    public function __construct(
        string $message,
        protected int $status,
        protected string $remark = '',
        protected array $errors = [],
        protected array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(int $status, array $body, ?Throwable $previous = null): self
    {
        $errors = array_values(array_map('strval', (array) ($body['message']['error'] ?? [])));
        $remark = (string) ($body['remark'] ?? '');
        $message = $errors ? implode(' ', $errors) : ($remark !== '' ? $remark : 'EcomAlo answered with HTTP '.$status);

        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 403 => PermissionException::class,
            $status === 404 => NotFoundException::class,
            $status === 422 => ValidationException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($message, $status, $remark, $errors, $body, $previous);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getRemark(): string
    {
        return $this->remark;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    // worth sending again later: rate limited or EcomAlo itself failed
    public function isRetryable(): bool
    {
        return $this->status === 429 || $this->status >= 500;
    }
}
