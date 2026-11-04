<?php

namespace EcomAlo\Exceptions;

// EcomAlo could not be reached at all (DNS, refused, timeout) after every retry; safe to try again later
class ConnectionException extends EcomAloException
{
}
