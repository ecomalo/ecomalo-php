<?php

namespace EcomAlo\Exceptions;

use RuntimeException;

// every exception this package throws extends this one, so one catch covers them all
class EcomAloException extends RuntimeException
{
}
