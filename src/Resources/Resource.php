<?php

namespace EcomAlo\Resources;

use EcomAlo\Exceptions\EcomAloException;
use EcomAlo\Http\Transport;

abstract class Resource
{
    public function __construct(protected Transport $site, protected ?Transport $merchant = null)
    {
    }

    protected function merchant(): Transport
    {
        if (! $this->merchant) {
            throw new EcomAloException('This call uses the merchant API: set merchant_key (ECOMALO_MERCHANT_KEY) to an API key from the API keys page.');
        }

        return $this->merchant;
    }
}
