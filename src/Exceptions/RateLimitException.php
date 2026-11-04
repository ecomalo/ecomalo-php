<?php

namespace EcomAlo\Exceptions;

// 429: too many calls a minute; slow down and retry
class RateLimitException extends ApiException
{
}
