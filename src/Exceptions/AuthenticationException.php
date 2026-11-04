<?php

namespace EcomAlo\Exceptions;

// 401: the token is wrong, revoked, or the site was disconnected in the panel
class AuthenticationException extends ApiException
{
}
