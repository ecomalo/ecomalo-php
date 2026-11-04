<?php

namespace EcomAlo\Exceptions;

// 403: the merchant API key lacks the scope, or the subscription is inactive
class PermissionException extends ApiException
{
}
