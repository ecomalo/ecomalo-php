<?php

namespace EcomAlo\Exceptions;

// a webhook whose signature or timestamp did not check out; answer it with 401 and do nothing
class InvalidSignatureException extends EcomAloException
{
}
