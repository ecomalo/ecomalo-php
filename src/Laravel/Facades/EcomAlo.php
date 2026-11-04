<?php

namespace EcomAlo\Laravel\Facades;

use EcomAlo\Client;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \EcomAlo\Resources\Orders orders()
 * @method static \EcomAlo\Resources\Products products()
 * @method static \EcomAlo\Resources\Stock stock()
 * @method static array status()
 * @method static array fraudCheck(string $phone)
 * @method static array tracking(string $code)
 * @method static void disconnect()
 *
 * @see Client
 */
class EcomAlo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
